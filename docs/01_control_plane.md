# 01. Arquitectura del Plano de Control (`jg_dashboard`)

[← Volver al README](../README.md) | [02. Control de Acceso (RBAC) →](02_rbac_tenancy.md)

---

## 1. Visión General

`jg_dashboard` es el componente central de administración, observabilidad y gobernanza del ecosistema jG. Está desarrollado sobre **Laravel 11**, **Filament v3**, **Livewire 3** y **Pest PHP**.

Opera como la **única pasarela expuesta** al exterior (red corporativa o Internet pública), blindando a los agentes de ejecución (`jg_agente`) para que residan de forma estricta en redes privadas sin acceso directo no autenticado.

```text
  USUARIOS & OPERADORES (Web Browser / REST)
                     │
                     │ HTTPS / JWT
                     ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_dashboard (Control Plane)                      │
│                                                                        │
│  ┌───────────────────────┐         ┌────────────────────────────────┐  │
│  │    Web UI & Console   │         │      REST API (Laravel 11)     │  │
│  │   (Filament / Blade)  │         │   (JWT Auth / Rate Limiting)   │  │
│  └───────────┬───────────┘         └───────────────┬────────────────┘  │
│              │                                     │                   │
│              ▼                                     ▼                   │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                     Servicios del Dashboard                      │  │
│  │  - Auth & RBAC (Admin/Operator/Viewer)                           │  │
│  │  - Agent Registration Service (Bootstrap Token 24h)              │  │
│  │  - HitlApprovalService (lockForUpdate pesimista)                 │  │
│  │  - AgentHealthMonitorService (Schedule everyMinute)              │  │
│  │  - AgentHttpClient (Firmas canónicas HMAC-SHA256)                │  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
│                                     │                                  │
│                                     ▼                                  │
│                    ┌─────────────────────────────────┐                 │
│                    │     PostgreSQL del Dashboard    │                 │
│                    │  (users, agents, tasks, audit)  │                 │
│                    └─────────────────────────────────┘                 │
└──────────────────────────────────────┬─────────────────────────────────┘
                                       │ HTTP / HMAC-SHA256 (Red Privada)
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_agente (Execution Plane)                       │
│                         (FastAPI / LangGraph)                          │
└────────────────────────────────────────────────────────────────────────┘
```

## 2. Responsabilidades Críticas del Control Plane

1. **Gestión de Identidad y Acceso (RBAC):**
   - Autenticación de usuarios basada en JWT (`app/Services/Auth/JwtService.php`) con expiración determinista (por defecto 60 minutos).
   - Roles jerárquicos: `admin > operator > viewer`.
2. **Aprovisionamiento Zero-Trust de Agentes:**
   - Emisión administrativa de tokens temporales de arranque (`bootstrap_token` con validez de 24 horas y un solo uso).
   - Canje seguro por un secreto criptográfico permanente (`permanent_secret`).
3. **Orquestación y Gobernanza HITL:**
   - Consola web reactiva y panel Filament para auditar planes detenidos por alto riesgo (`RiskLevel >= HIGH`).
   - Prevención de condiciones de carrera y doble aprobación mediante transacciones de base de datos con bloqueo pesimista (`lockForUpdate`).
4. **Proxy Firmado hacia el Plano de Ejecución (`AgentHttpClient`):**
   - Firma criptográfica canónica en cada petición saliente dirigida a los agentes (`X-Signature`, `X-Timestamp`).
   - Reintentos exponenciales automáticos ante errores de conectividad o respuestas HTTP 5xx.
   - Mapeo determinista de errores: timeouts a `504 Gateway Timeout` y fallos de transporte a `503 Service Unavailable`.
5. **Observabilidad y Resiliencia Activa:**
   - Patrón Circuit Breaker (`CircuitBreakerState`) para aislar servicios en degradación.
   - Deduplicación de alertas operacionales (`NotifiedIncident`).
   - Visualización de la topología en grafo (`TopologyMap`) con fallback automático a modelos Eloquent.
6. **Independencia Estricta de Bases de Datos:**
   - El Dashboard posee su propio esquema relacional en PostgreSQL (`jg_dashboard`).
   - **Bajo ninguna circunstancia consulta ni se conecta a la base de datos interna de los agentes.**

---

## 3. Doble Arquitectura de Interfaz Web: Filament vs Consola Web

El plano de control implementa dos capas de interfaz de usuario complementarias:

### 3.1 Panel Administrativo Filament v3 (`/admin`)
- **Acceso:** Restringido mediante el contrato `FilamentUser::canAccessPanel()` a usuarios con roles `admin` y `operator`.
- **Propósito:** Gestión CRUD integral de recursos del sistema:
  - `AgentResource`: inventario, estado de conectividad, capacidades reportadas y secretos.
  - `AgentTaskResource`: historial de tareas, prompts, duraciones y trazas cognitivas.
  - `CircuitBreakerResource`: supervisión de interruptores de circuito, filtros de estado y visualización JSON de fallos.
  - `NotifiedIncidentResource`: registro de alertas emitidas y prevención de duplicados.
  - `UserResource`: gestión de cuentas, roles RBAC y credenciales de operadores.
- **Páginas Personalizadas:** `TopologyMap` para la inspección visual de la infraestructura.
- **Widgets en Tiempo Real:** `AgentHealthWidget` (polling 10s con fallback a `/`), `ActiveTasksOverview` (polling 4s) y `CircuitBreakersStatus`.

### 3.2 Consola Web Interactiva (`/console` — `WebConsoleController`)
- **Ruta Base:** `/console` (con alias `/dashboard`).
- **Controlador:** `App\Http\Controllers\WebConsoleController` (desarrollado con Blade, Tailwind y Alpine.js/Vanilla JS).
- **Capacidades Operativas Directas:**
  - **KPIs en Vivo:** Métricas inmediatas de agentes totales, activos, fuera de línea y solicitudes HITL en espera.
  - **Auto-Reconciliación de Tareas:** Detección proactiva de tareas huérfanas en `WAITING_APPROVAL` para asegurar que dispongan de registro HITL activo.
  - **Sincronización en Background:** Consulta asíncrona de estado de tareas en `PROCESSING` o `QUEUED` directamente contra el agente.
  - **Aprovisionamiento Dinámico:** Emisión de agentes y bootstrap tokens desde modal interactivo (`POST /console/agents`).
  - **Disparo y Monitoreo:** Envío de prompts (`POST /console/executions/trigger`) e inspección en vivo de trazas de hilos.
  - **Gestión de Perfil:** Consulta y actualización del perfil del operador (`GET|PUT /console/profile`), incluyendo cambio seguro de contraseña con validación de contraseña actual y longitud mínima.

---

## 4. Comandos de Operación y Mantenimiento CLI

El Control Plane ofrece comandos Artisan para despliegue automatizado y supervisión continua:

```bash
# 1. Aprovisionamiento desatendido e idempotente del Administrador inicial (CWE-521)
php artisan dashboard:init-admin [--email=...] [--password=...] [--name=...] [--force]

# 2. Monitorización periódica de latidos y pase automático a OFFLINE de agentes inactivos
php artisan agents:check-health [--timeout=90]
```

---

## 5. Navegación

- [← Volver al README Principal](../README.md)
- [02. Control de Acceso y Roles (RBAC) →](02_rbac_tenancy.md)
- [06. Observabilidad y Resiliencia →](06_observability_resilience.md)
