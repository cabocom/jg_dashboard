# jg_dashboard

**Plano de Control, Administración y Gobernanza Human-In-The-Loop**  
*Control Plane del ecosistema agéntico jG*

---

## 1. Visión General y Propósito

`jg_dashboard` es el **Control Plane** central del ecosistema de agentes jG. Desarrollado sobre [Laravel 11](https://laravel.com/), [Filament v3](https://filamentphp.com/), [Livewire 3](https://livewire.laravel.com/) y [Pest PHP](https://pestphp.com/), proporciona la interfaz de administración, el sistema de autenticación y autorización (RBAC), el aprovisionamiento Zero-Trust de agentes (`jg_agente`), la consola de monitorización y la pasarela de aprobación humana (**Human-In-The-Loop - HITL**) para planes de ejecución críticos.

Es el **único componente del ecosistema expuesto hacia el exterior**, actuando como escudo perimetral para asegurar que los motores de ejecución (`jg_agente`) permanezcan completamente aislados en redes internas privadas sin acceso directo no autenticado.

### Documentación Técnica Modular
Para profundizar en la arquitectura y gobernanza del Control Plane:
- [01. Arquitectura del Control Plane](docs/01_control_plane.md) — Separación de planos, pipeline de servicios y PostgreSQL aislado.
- [02. Control de Acceso y Roles (RBAC)](docs/02_rbac_tenancy.md) — Modelo de roles `admin`, `operator` y `viewer`, JwtService y auditoría inmutable.
- [03. Gobernanza Human-In-The-Loop (HITL)](docs/03_hitl_governance.md) — Lock pesimista, reconciliación ante fallos 404/409 y rescate de solicitudes huérfanas.
- [04. Protocolo Criptográfico Inter-Servicio](docs/04_hmac_handshake.md) — Firmas HMAC-SHA256, dualidad de almacenamiento de secretos y resiliencia HTTP.
- [05. Esquema de Base de Datos](docs/05_database_schema.md) — Diccionario de datos de las 9 entidades relacionales de `jg_dashboard`.
- [06. Observabilidad y Resiliencia](docs/06_observability_resilience.md) — Circuit Breakers, Mapa Topológico, Deduplicación de Incidentes y CLI SRE.
- [07. Pasarela de Adaptadores y Webhooks](docs/07_adapters_webhooks.md) — Normalizador de estímulos externos (`InputNormalizer`), formateo adaptativo (`ClientOutputFormatter`) y controlador webhook.

---

## 2. Arquitectura de Separación de Planos

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
│  │  - Auth & RBAC (Admin / Operator / Viewer) + JwtService          │  │
│  │  - Agent Registration Service (Bootstrap Token 24h + Dual Secret)│  │
│  │  - HitlApprovalService (lockForUpdate + Reconciliación 404/409)  │  │
│  │  - AgentHealthMonitorService (Schedule everyMinute)              │  │
│  │  - AgentHttpClient (Firmas canónicas HMAC-SHA256 y Retries)      │  │
│  │  - Observabilidad: CircuitBreakerState, InfraTopology, Incidentes│  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
│                                     │                                  │
│                                     ▼                                  │
│                    ┌─────────────────────────────────┐                 │
│                    │     PostgreSQL del Dashboard    │                 │
│                    │  (users, agents, tasks, audit,  │                 │
│                    │   circuit_breaker, topology,    │                 │
│                    │   notified_incidents)           │                 │
│                    └─────────────────────────────────┘                 │
└──────────────────────────────────────┬─────────────────────────────────┘
                                       │ HTTP / HMAC-SHA256 (Red Privada)
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_agente (Execution Plane)                       │
│                         (FastAPI / LangGraph)                          │
└────────────────────────────────────────────────────────────────────────┘
```

### Principios Fundamentales
1. **Aislamiento Físico y de Datos:** Opera sobre su propia base de datos PostgreSQL (`jg_dashboard`). **Bajo ninguna circunstancia consulta ni se conecta a las bases de datos internas de los agentes.**
2. **Desacoplamiento de Clientes Externos:** Centraliza la normalización de peticiones, transformando entradas de APIs o consolas en contratos canónicos `AgentInput` y `AgentOutput`.
3. **Firmado Criptográfico Saliente:** Cada invocación delegada hacia `jg_agente` se firma en tiempo real mediante `AgentHttpClient` con HMAC-SHA256 y marca temporal anti-repetición.
4. **Resiliencia y Supervisión de Topología:** Aísla fallos de dependencias mediante Circuit Breakers y representa visualmente la topología del clúster con fallback dinámico a modelos Eloquent.

---

## 3. Matriz de Control de Acceso Basado en Roles (RBAC)

La jerarquía de roles satisface la relación de orden transitiva: $\text{ADMIN} \succ \text{OPERATOR} \succ \text{VIEWER}$.

| Funcionalidad / Endpoint | ADMIN | OPERATOR | VIEWER |
| :--- | :---: | :---: | :---: |
| **Login / Logout / Auth Me (`/api/v1/auth/*`)** | Sí | Sí | Sí |
| **Acceso a Panel Administrativo Web (`canAccessPanel`)** | Sí | Sí | No |
| **Aprovisionamiento de Agentes (`POST /api/v1/admin/agents`)** | Sí | No | No |
| **Regenerar / Revocar Tokens (`/api/v1/admin/agents/{id}/*`)** | Sí | No | No |
| **Listar Agentes y Estados (`GET /api/v1/agents`)** | Sí | Sí | Sí |
| **Inspección de Capacidades (`GET /api/v1/agents/{id}/capabilities`)** | Sí | Sí | Sí |
| **Consulta de Estado de Ejecución (`GET /api/v1/executions/{id}/{th}`)**| Sí | Sí | Sí |
| **Disparo de Ejecuciones (`POST /api/v1/executions/trigger`)** | Sí | Sí | No |
| **Listar Aprobaciones Pendientes (`GET /api/v1/approvals/pending`)** | Sí | Sí | No |
| **Aprobar Solicitud HITL (`POST /api/v1/approvals/{id}/approve`)** | Sí | Sí | No |
| **Rechazar Solicitud HITL (`POST /api/v1/approvals/{id}/reject`)** | Sí | Sí | No |

---

## 4. Gobernanza Human-In-The-Loop (HITL)

### Concurrencia Segura con Bloqueo Pesimista
Para impedir que dos operadores autoricen o rechacen simultáneamente la misma operación de alto riesgo, `HitlApprovalService` ejecuta la resolución dentro de una transacción de base de datos con bloqueo pesimista a nivel de fila (`lockForUpdate`):
```php
$lockResult = DB::transaction(function () use ($approvalId) {
    $approval = ApprovalRequest::where('id', $approvalId)->lockForUpdate()->first();
    if (!$approval || $approval->status !== 'PENDING') {
        return ['success' => false, 'error' => 'Solicitud ya resuelta previamente.'];
    }
    $approval->status = 'APPROVED';
    $approval->save();
    return ['success' => true, 'approval' => $approval];
});
```

### Alcance de Aprobación
> [!NOTE]
> En la base de código actual, el endpoint `POST /api/v1/approvals/{id}/approve` opera bajo autorización o rechazo determinista del plan formulado por el agente. **No se admite la modificación de argumentos en caliente** (`modified_arguments`). Ante parámetros inadecuados, el operador rechaza la solicitud registrando la justificación técnica (`justification`), lo que aborta la ejecución en el agente con estado `CANCELLED`.

---

## 5. Protocolo Criptográfico Inter-Servicio (HMAC-SHA256)

La comunicación saliente generada por `AgentHttpClient` y la recepción de heartbeats en `AgentHeartbeatController` siguen el contrato canónico compartido:

$$\text{CanonicalString} = \text{METHOD} + \text{"\textbackslash n"} + \text{PATH} + \text{"\textbackslash n"} + \text{TIMESTAMP} + \text{"\textbackslash n"} + \text{SHA256(RAW\_BODY)}$$
$$\text{Signature} = \text{hash\_hmac}('sha256', \text{CanonicalString}, \text{SecretKey})$$

- **Cabeceras exactas en código:** `X-Signature`, `X-Timestamp`, `X-Agent-ID`, `X-Correlation-ID`.
- **Ventana de tolerancia anti-replay:** $\|t_{\text{actual}} - t_{\text{req}}\| \le 300\text{ segundos (5 minutos)}$.
- **Verificación segura:** Comparación en tiempo constante con `hash_equals()`.

---

## 6. Esquema de Persistencia (PostgreSQL)

| Tabla | Propósito y Contenido |
| :--- | :--- |
| **`users`** | Cuentas de operadores y administradores, hash de contraseñas (`bcrypt`/`argon2id`) y roles RBAC. |
| **`dashboard_agents`** | Catálogo de agentes, estado, secreto dual (`hash` inbound + `encrypted` outbound), versión y métricas. |
| **`dashboard_bootstrap_tokens`** | Tokens temporales de aprovisionamiento Zero-Trust (TTL 24h, hash SHA-256, un solo uso). |
| **`dashboard_approval_requests`** | Solicitudes HITL en espera, planes propuestos, trazas cognitivas y justificaciones. |
| **`dashboard_audit_events`** | Pista inmutable de auditoría forense (`const UPDATED_AT = null;`) con IP y User-Agent. |
| **`agent_tasks`** | Historial de tareas, prompts, estados de finalización, duraciones calculadas y trazas intermedias. |
| **`circuit_breaker_state`** | Control de corte de tráfico por servicio dependiente (`is_tripped`, motivo e historial JSON). |
| **`infra_topology`** | Snapshots estructurados en grafo (`nodes_json`, `edges_json`) con contadores de salud. |
| **`notified_incidents`** | Registro y deduplicación de incidentes de observabilidad para mitigar fatiga de alertas. |

---

## 7. Despliegue Local, Comandos CLI y Verificación de Calidad

### Arranque con Docker Compose
```bash
# Levantar contenedor web de jg_dashboard y su PostgreSQL propio aislado (puerto 5434)
docker compose up -d

# Inspeccionar logs de ejecución
docker compose logs -f
```

### Comandos de Administración y Monitorización CLI
```bash
# 1. Aprovisionamiento desatendido e inicialización idempotente del superadministrador
php artisan dashboard:init-admin

# 2. Comprobación y transición a OFFLINE de agentes inactivos sin heartbeat (>90s)
php artisan agents:check-health --timeout=90
```

### Ejecución de Pruebas Automatizadas
La suite automatizada cuenta con **315 pruebas** de unidad e integración (286 Pest PHP + 29 PHPUnit):
```bash
# Ejecutar verificación completa de linters y pruebas
./scripts/check.sh

# O invocar Pest directamente en el entorno con dependencias de desarrollo
./vendor/bin/pest
```

---

## 8. Licencia y Modelo Dual

`jg_dashboard` se distribuye bajo un esquema de **Doble Licenciamiento (Dual Licensing)**:

1. **Código Abierto (GNU AGPL-3.0):** Disponible para uso personal, auditoría y auto-hospedaje comunitario bajo los términos de la [GNU Affero General Public License v3.0](LICENSE).
2. **Licencia Comercial Enterprise:** Exención legal de copyleft de red para despliegues corporativos cerrados, marca blanca, integraciones con Active Directory/SAML o plataformas SaaS propietarias.

Para consultas sobre licencias comerciales: visita [https://javiergalvez.com](https://javiergalvez.com).
