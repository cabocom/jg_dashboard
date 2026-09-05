# Arquitectura del Sistema: jg_dashboard (Control Plane)

**Componente:** Plano de Control, Gobernanza, Gestión de Identidad y HITL  
**Stack Tecnológico:** Laravel 11, Filament PHP v3, Livewire 3, PostgreSQL, Pest PHP  

---

## 1. Visión General y Rol en el Ecosistema

`jg_dashboard` es el **Control Plane** (plano de control y administración) del ecosistema jG. Es el **único componente expuesto externamente** hacia usuarios administradores, operadores humanos y fuentes o clientes externos.

```text
                           ┌───────────────────────────────┐
                           │   Usuarios / Operadores       │
                           │   (Navegador / REST API)      │
                           └───────────────┬───────────────┘
                                           │  HTTPS + JWT (RBAC)
                                           ▼
                           ┌───────────────────────────────┐
                           │         jg_dashboard          │
                           │   (Plano de Control Central)  │
                           └───────┬───────────────┬───────┘
                                   │               │
        Base de Datos Propia       │               │ Red Interna Privada
        (PostgreSQL :5434)         │               │ (HMAC-SHA256 Signed HTTP)
        ┌───────────────────┐      │               ▼
        │ dashboard_users   │◄─────┤      ┌─────────────────┐
        │ dashboard_agents  │      │      │    jg_agente    │
        │ dashboard_tokens  │      │      │ (Runtime Grafo) │
        │ dashboard_hitl    │      │      └─────────────────┘
        │ dashboard_audit   │      │
        └───────────────────┘      │
```

---

## 2. Principios Arquitectónicos Fundamentales

### 2.1 Frontispicio de Seguridad Exclusivo
Los agentes cognitivos (`jg_agente`) residen en redes privadas sin salida directa a Internet. Toda interacción humana, administración, monitorización o disparo de tareas pasa obligatoriamente a través del Dashboard.

### 2.2 Aislamiento Estricto de Persistencia (No Direct DB Access)
Queda terminantemente prohibido que `jg_dashboard` acceda directamente a la base de datos de los agentes (`core_checkpoints`, `core_messages`, etc.).
- **Canal Permitido:** REST API HTTP firmado con HMAC-SHA256 (`AgentHttpClient`).
- **Base de Datos Propia:** PostgreSQL independiente (puerto 5434 en local) para gestionar usuarios, agentes registrados, tokens de aprovisionamiento, historial de aprobaciones y auditoría.

### 2.3 Gobernanza Human-In-The-Loop (HITL)
Cuando un agente detecta un plan cognitivo con nivel de riesgo crítico (`RiskLevel >= HIGH`), suspende su grafo en un checkpoint condicional. El Dashboard captura esta solicitud, la persiste de forma idempotente, la expone a operadores autorizados y transmite la resolución (`/approve` o `/reject`) para reanudar o abortar la tarea.

### 2.4 Proxy HTTP Resiliente
La comunicación con los agentes se canaliza mediante `App\Services\Agents\AgentHttpClient`:
- Firma criptográfica HMAC-SHA256 (`X-Signature`, `X-Timestamp`).
- Timeouts estrictos configurables (15s por defecto).
- Reintentos con retroceso exponencial (backoff) ante fallos transitorios de red.
- Respuestas normalizadas estructuradas (502 Bad Gateway, 503 Service Unavailable, 504 Gateway Timeout).

---

## 3. Capas de la Aplicación

```text
jg_dashboard/
├── app/
│   ├── Filament/                # Interfaz Gráfica Administrativa (Filament v3 + Livewire)
│   │   ├── Pages/               # Páginas personalizadas (TopologyMap, etc.)
│   │   └── Resources/           # Recursos CRUD reactivos (AgentTaskResource, etc.)
│   ├── Http/
│   │   ├── Controllers/Api/     # Controladores REST API
│   │   │   ├── AuthController.php            # Autenticación JWT (/auth/login, /auth/me)
│   │   │   ├── AdminAgentController.php      # Aprovisionamiento (/admin/agents)
│   │   │   ├── AgentRegistrationController.php# Handshake Zero-Trust (/agents/register)
│   │   │   ├── AgentHeartbeatController.php   # Ingesta de métricas (/agents/heartbeat)
│   │   │   ├── AgentController.php           # Catálogo y capacidades (/agents)
│   │   │   ├── ExecutionController.php       # Disparo y estado (/executions)
│   │   │   └── HitlApprovalController.php    # Gobernanza HITL (/approvals)
│   │   └── Middleware/          # Seguridad y RBAC (JwtAuthenticate, RequireRole, RequirePermission)
│   ├── Models/                  # Modelos Eloquent ORM
│   ├── Services/                # Lógica de dominio desacoplada
│   │   ├── Agents/              # AgentRegistrationService, AgentHttpClient, AgentHealthMonitorService
│   │   ├── Auth/                # JwtService, AuthService
│   │   └── Hitl/                # HitlApprovalService
│   └── Console/Commands/        # Comandos CLI (InitAdminCommand, CheckAgentHealthCommand)
```
