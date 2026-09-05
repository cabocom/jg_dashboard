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
```bash
./scripts/check.sh
# o directamente:
./vendor/bin/pest
```

### Ejecución con Docker Compose
```bash
# Levantar contenedor web de jg_dashboard y PostgreSQL propio aislado (puerto 5434)
docker compose up -d

# Verificar estado y logs
docker compose ps
docker compose logs -f
```

---

## 7. Roadmap y Estado

- [x] **Fase 01:** Bootstrap, Documentación y Tooling (100%):
  - [x] `ISSUE-F01-T01`: Estructura base del repositorio jg_dashboard y configuración de entorno (Laravel 11, Filament v3, Livewire 3, Pest PHP).
  - [x] `ISSUE-F01-T02`: Dockerfile multi-stage y Docker Compose con PostgreSQL propio (puerto 5434 y volumen aislado).
  - [x] `ISSUE-F01-T03`: Pipeline de CI/CD para jg_dashboard (.gitlab-ci.yml con stage test y build).
- [x] **Fase 02:** Persistencia y modelos de base de datos PostgreSQL (100%):
  - [x] `ISSUE-F02-T01`: Modelos de base de datos para usuarios y roles RBAC (Eloquent User, FilamentUser, contraseñas hasheadas y migración).
  - [x] `ISSUE-F02-T02`: Modelos de base de datos para agentes registrados (`dashboard_agents`, modelo Eloquent `Agent`, UUIDs, scopes y tests).
  - [x] `ISSUE-F02-T03`: Modelos de base de datos para tokens de aprovisionamiento (`dashboard_bootstrap_tokens`, modelo Eloquent `BootstrapToken`, TTL 24h, flag de uso único).
  - [x] `ISSUE-F02-T04`: Modelos de base de datos para solicitudes HITL y eventos de auditoría (`dashboard_approval_requests`, `dashboard_audit_events`).
- [ ] **Fase 03:** Autenticación de usuarios y RBAC (Admin, Operator, Viewer):
  - [x] `ISSUE-F03-T01`: Servicio de autenticación JWT y hashing de contraseñas (`JwtService`, `AuthService`, `/api/v1/auth/login`).
  - [x] `ISSUE-F03-T02`: Middleware y dependencias de autorización RBAC (`jwt.auth`, `role`, `permission`, 401/403).
  - [x] `ISSUE-F03-T03`: Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me (`/api/v1/auth/me`, `/api/v1/auth/logout`, roles y permisos).
  - [ ] `ISSUE-F03-T04`: Script de inicialización para creación del usuario administrador inicial.
- [ ] **Fase 04:** Sistema de registro de agentes con `bootstrap_token` de 24h y credencial permanente.
- [ ] **Fase 05:** Monitoreo de agentes y procesamiento de heartbeats periódicos.
- [ ] **Fase 06:** Cliente HTTP proxy y orquestación de ejecuciones hacia `jg_agente`.
- [ ] **Fase 07:** Consola y acciones de aprobación Human-In-The-Loop (HITL).
- [ ] **Fase 08:** Adaptador normalizador de entrada/salida (`AgentInput` / `AgentOutput`).
- [ ] **Fase 09:** Integración end-to-end con `jg_agente` (Casos de Éxito A y B).
