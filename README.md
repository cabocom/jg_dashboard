# jg_dashboard

**Plano de Control, Administración y Gobernanza Human-In-The-Loop**  
*Control Plane del ecosistema jG*

---

## 1. ¿Qué es `jg_dashboard`?

`jg_dashboard` es el **Control Plane** central del ecosistema de agentes. Proporciona la interfaz administrativa, el sistema de autenticación y autorización (RBAC), el registro y aprovisionamiento seguro de agentes (`jg_agente`), la consola de monitorización y la pasarela de aprobación humana (**Human-In-The-Loop - HITL**) para planes de ejecución críticos.

Es el **único componente del ecosistema expuesto hacia el exterior**. Actúa como escudo de seguridad, asegurando que los motores de ejecución (`jg_agente`) permanezcan completamente aislados en redes internas privadas.

---

## 2. ¿Qué problema resuelve?

1. **Aislamiento de seguridad:** Impide la exposición pública de los motores de ejecución y sus herramientas de infraestructura.
2. **Gobernanza Human-In-The-Loop (HITL):** Proporciona un punto centralizado donde los operadores humanos pueden auditar, autorizar o rechazar planes automatizados de alto riesgo generados por agentes autónomos.
3. **Aprovisionamiento Zero-Trust:** Administra la creación de agentes mediante `bootstrap_tokens` temporales (24h de uso único) y emite credenciales permanentes revocables.
4. **Desacoplamiento de clientes externos:** Centraliza los adaptadores de entrada y salida (Web, REST, mensajería), convirtiendo estímulos diversos en contratos normalizados (`AgentInput` y `AgentOutput`).
5. **Independencia de base de datos:** Opera sobre su propia base de datos PostgreSQL; **nunca se conecta a la base de datos interna de los agentes**.

---

## 3. Arquitectura General y Separación de Planos

```text
  USUARIOS & OPERADORES                     SISTEMAS EXTERNOS (APIs, Webhooks)
           │                                                │
           └───────────────────────┬────────────────────────┘
                                   │ HTTPS
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_dashboard (Control Plane)                      │
│                                                                        │
│  ┌───────────────────────┐         ┌────────────────────────────────┐  │
│  │    Web UI & Static    │         │      API Gateway & Normalizer  │  │
│  │   (Next.js / Vanilla) │         │    (FastAPI / REST External)   │  │
│  └───────────┬───────────┘         └───────────────┬────────────────┘  │
│              │                                     │                   │
│              ▼                                     ▼                   │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                     Servicios del Dashboard                      │  │
│  │  - Auth & RBAC (Admin/Operator/Viewer)                           │  │
│  │  - Agent Registry & Bootstrap Token Service (TTL 24h)            │  │
│  │  - HITL Approval Engine (/approve, /reject)                     │  │
│  │  - Agent Monitor & Heartbeat Collector                           │  │
│  │  - Agent HTTP Client (Proxy hacia red privada)                   │  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
│                                     │                                  │
│                                     ▼                                  │
│                    ┌─────────────────────────────────┐                 │
│                    │     PostgreSQL del Dashboard    │                 │
│                    │   (dashboard_users, agents...)  │                 │
│                    └─────────────────────────────────┘                 │
└──────────────────────────────────────┬─────────────────────────────────┘
                                       │ HTTP (Red Privada Docker)
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_agente (Execution Plane)                       │
│             (LangGraph · Tools · PostgreSQL del Agente)                │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Flujo de Gobernanza y HITL

```text
[Operador/Sistema] ──► Dispara acción vía Dashboard ──► [jg_dashboard]
                                                             │ POST /executions/trigger
                                                             ▼
                                                        [jg_agente]
                                                             │ Evalúa riesgo
                                                             │ ¿Riesgo >= HIGH?
                                                             ▼
                                                    [Pausa en Checkpoint]
                                                             │
                                                             ▼ Emite evento de espera
                                                        [jg_dashboard]
                                                             │
                                                             ▼ (Notificación en UI)
                                                    [Consola HITL Dashboard]
                                                             │ Operador revisa plan
                                                             │ Pulsa "Aprobar"
                                                             ▼
                                                  POST /approve en jg_agente
                                                             │
                                                             ▼
                                                        [jg_agente]
                                                    (Reanuda y ejecuta)
```

---

## 5. Estructura del Repositorio

```text
jg_dashboard/
├── composer.json                   # Dependencias Laravel 11, Filament v3 y Pest
├── composer.lock                   # Lockfile de dependencias PHP
├── phpunit.xml                     # Configuración de pruebas Pest/PHPUnit
├── Dockerfile                      # Contenedor PHP 8.3-FPM + Nginx
├── app/
│   ├── Filament/                   # Recursos y páginas del panel Filament
│   │   ├── Pages/                  # Vistas personalizadas y mapa de topología
│   │   ├── Resources/              # Recursos reactivos (AgentTaskResource, etc.)
│   │   └── Widgets/                # Widgets de métricas y estados
│   ├── Models/                     # Modelos Eloquent ORM (AgentTask, User, etc.)
│   ├── Providers/                  # Service Providers (AppServiceProvider, etc.)
│   └── Services/                   # Clientes de integración con el agente (DevOps60kApiClient / AgentClient)
├── bootstrap/                      # Bootstrap de Laravel
├── config/                         # Configuraciones (app, database, devops60k/agent)
├── database/                       # Migraciones, factories y seeders
├── docker/                         # Configuraciones y entrypoint Docker
├── public/                         # Assets públicos y punto de entrada index.php
├── resources/                      # Vistas Blade y recursos estáticos
├── routes/                         # Rutas web y de consola
├── scripts/
│   └── check.sh                    # Script de verificación integral (Pest)
└── tests/                          # Suite integral de pruebas (Unit + Feature)
    ├── Feature/
    └── Unit/
```

---

## 6. Verificación de Calidad y Despliegue Local

### Pruebas Automatizadas (Pest PHP)
La suite automatizada incluye **35 pruebas de unidad e integración** que validan autenticación JWT, RBAC, registro de agentes Zero-Trust, heartbeats y orquestación HITL:

```bash
# Ejecución en el host con dependencias de desarrollo
./scripts/check.sh
# o directamente:
./vendor/bin/pest
```

> **Nota:** La imagen Docker de producción se construye con `--no-dev` por seguridad y optimización de tamaño, por lo que la suite Pest se ejecuta en el entorno de desarrollo del host o en el pipeline de CI.

### Ejecución con Docker Compose
```bash
# Levantar contenedor web de jg_dashboard y PostgreSQL propio aislado (puerto 5434)
docker compose up -d

# Verificar estado y logs
docker compose ps
docker compose logs -f
```

---

## 7. Documentación y Deuda Técnica

Para profundizar en el diseño del plano de control y sus pendientes técnicos:
- **[Contexto de Proyecto](PROJECT_CONTEXT.md):** Arquitectura de Laravel, RBAC y modelos de persistencia.
- **[Tareas Pendientes y Deuda Técnica](TODO-PENDIENTES.md):** Registro exhaustivo de ítems pendientes para la auditoría técnica.
- **[Documentación Global](../docs/):** Especificaciones de arquitectura, APIs y protocolos del ecosistema.

---

## 8. Roadmap y Estado

- [x] **Fase 01:** Bootstrap, Documentación y Tooling (100%):
  - [x] `ISSUE-F01-T01`: Estructura base del repositorio jg_dashboard y configuración de entorno (Laravel 11, Filament v3, Livewire 3, Pest PHP).
  - [x] `ISSUE-F01-T02`: Dockerfile multi-stage y Docker Compose con PostgreSQL propio (puerto 5434 y volumen aislado).
  - [x] `ISSUE-F01-T03`: Pipeline de CI/CD para jg_dashboard (.gitlab-ci.yml con stage test y build).
- [x] **Fase 02:** Persistencia y modelos de base de datos PostgreSQL (100%):
  - [x] `ISSUE-F02-T01`: Modelos de base de datos para usuarios y roles RBAC (Eloquent User, FilamentUser, contraseñas hasheadas y migración).
  - [x] `ISSUE-F02-T02`: Modelos de base de datos para agentes registrados (`dashboard_agents`, modelo Eloquent `Agent`, UUIDs, scopes y tests).
  - [x] `ISSUE-F02-T03`: Modelos de base de datos para tokens de aprovisionamiento (`dashboard_bootstrap_tokens`, modelo Eloquent `BootstrapToken`, TTL 24h, flag de uso único).
  - [x] `ISSUE-F02-T04`: Modelos de base de datos para solicitudes HITL y eventos de auditoría (`dashboard_approval_requests`, `dashboard_audit_events`).
- [x] **Fase 03:** Autenticación de usuarios y RBAC (Admin, Operator, Viewer) (100%):
  - [x] `ISSUE-F03-T01`: Servicio de autenticación JWT y hashing de contraseñas (`JwtService`, `AuthService`, `/api/v1/auth/login`).
  - [x] `ISSUE-F03-T02`: Middleware y dependencias de autorización RBAC (`jwt.auth`, `role`, `permission`, 401/403).
  - [x] `ISSUE-F03-T03`: Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me (`/api/v1/auth/me`, `/api/v1/auth/logout`, roles y permisos).
  - [x] `ISSUE-F03-T04`: Script de inicialización para creación del usuario administrador inicial (`php artisan dashboard:init-admin`).
- [x] **Fase 04:** Sistema de registro de agentes con `bootstrap_token` de 24h y credencial permanente (100%):
  - [x] `ISSUE-F04-T01`: Servicio de registro de agentes y generación de bootstrap tokens (`AgentRegistrationService`, TTL 24h, Zero-Trust).
  - [x] `ISSUE-F04-T02`: Endpoint administrativo: POST /api/v1/admin/agents (`AdminAgentController`, RBAC `role:admin`, 201 Created con token y agent_id).
  - [x] `ISSUE-F04-T03`: Endpoint de consumo de registro por el agente: POST /api/v1/agents/register (`AgentRegistrationController`, intercambio por `permanent_secret`, activación y un solo uso).
  - [x] `ISSUE-F04-T04`: Validación de expiración y uso único del bootstrap_token (`BootstrapToken@isExpired`, rechazo 401 Unauthorized ante tokens expirados/consumidos y revocación administrativa).
- [x] **Fase 05:** Monitoreo de agentes y procesamiento de heartbeats periódicos (100%):
  - [x] `ISSUE-F05-T01`: Endpoint de recepción de heartbeats: POST /api/v1/agents/heartbeat (`AgentHeartbeatController`, autenticación por `permanent_secret`, métricas y actualización de último latido).
  - [x] `ISSUE-F05-T02`: Servicio en background de detección de agentes caídos (`AgentHealthMonitorService`, Artisan `agents:check-health`, Schedule everyMinute, transición automática a OFFLINE >90s).
  - [x] `ISSUE-F05-T03`: Endpoint de consulta de estado/métricas de salud de agentes (`AgentController`, paginación, filtros de estado, búsqueda y métricas agregadas).
- [x] **Fase 06:** Cliente HTTP proxy y orquestación de ejecuciones hacia `jg_agente` (100%):
  - [x] `ISSUE-F06-T01`: Cliente HTTP interno para comunicación con jg_agente (`AgentHttpClient`, firmado HMAC-SHA256, timeouts, reintentos con backoff y resiliencia de red).
  - [x] `ISSUE-F06-T02`: Pasarela para disparar ejecuciones: POST /api/v1/executions/trigger (`ExecutionController`, RBAC `role:operator`, normalización a `AgentInput`, auditoría y persistencia HITL).
  - [x] `ISSUE-F06-T03`: Consulta de estado de ejecuciones: GET /api/v1/executions/{agent_id}/{thread_id} (`ExecutionController@show`, aislamiento de BD, estado unificado y gobernanza HITL).
  - [x] `ISSUE-F06-T04`: Endpoint de inspección de capacidades: GET /api/v1/agents/{agent_id}/capabilities (`AgentController@capabilities`, consulta en vivo y sincronización en BD).
- [x] **Fase 07:** Consola y acciones de aprobación Human-In-The-Loop (HITL) (100%):
  - [x] `ISSUE-F07-T01`: Gestión de solicitudes de aprobación HITL en Dashboard (`HitlApprovalService`, idempotencia por `thread_id`, planes de alto riesgo, herramientas involucradas y auditoría).
  - [x] `ISSUE-F07-T02`: Endpoint de listado de aprobaciones pendientes: GET /api/v1/approvals/pending (`HitlApprovalController@pending`, RBAC `role:operator`, resumen de acciones, nivel de riesgo y tiempo transcurrido).
  - [x] `ISSUE-F07-T03`: Endpoint de aprobación HITL: POST /api/v1/approvals/{approval_id}/approve (`HitlApprovalController@approve`, delegación hacia `jg_agente`, actualización de estado y auditoría).
  - [x] `ISSUE-F07-T04`: Endpoint de rechazo HITL: POST /api/v1/approvals/{approval_id}/reject (`HitlApprovalController@reject`, justificación obligatoria, cancelación en `jg_agente` y auditoría inmutable).
- [x] **Fase 09:** Interfaz Gráfica Web y Consola HITL (100%):
  - [x] `ISSUE-F09-T01`: Estructura base de la interfaz gráfica web (`/console`, Vanilla CSS `console.css`, Vanilla JS `console.js`, templates Blade y navegación entre Agentes, Aprobaciones HITL y Ejecuciones).
  - [x] `ISSUE-F09-T02`: Panel de agentes con visualización de telemetría y métricas de salud en tiempo real (tarjetas con KPIs, tabla alternativa, búsqueda/filtros, modal de provisión con Bootstrap Token de 24h y endpoint `POST /console/agents`).
  - [x] `ISSUE-F09-T03`: Vista UI: Consola de aprobación Human-In-The-Loop (HITL) (desglose secuencial de pasos, indicadores de riesgo, comandos de compensación, inspector de JSON y acciones directas de aprobación y rechazo).
  - [x] `ISSUE-F09-T04`: Vista UI: Visor de ejecuciones y traza de pasos en tiempo real (prompt de entrada AgentInput, línea de tiempo interactiva de herramientas con estado/latencia, visor de salida AgentOutput con portapapeles, chips rápidos de hilos de sesión y polling en vivo cada 3s).
- [ ] **Fase 08:** Adaptadores de estímulos externos (Webhooks / Mock Chat).
- [ ] **Fase 10:** Integración end-to-end con `jg_agente` (Casos de Éxito A y B).

Consulte [TODO-PENDIENTES.md](TODO-PENDIENTES.md) para el desglose detallado de tareas pendientes y deuda técnica para la siguiente fase.
