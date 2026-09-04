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

## 5. Estructura Prevista del Repositorio

```text
jg_dashboard/
├── README.md
├── PROJECT_CONTEXT.md
├── ISSUES.csv
├── pyproject.toml
├── Dockerfile
├── app/
│   ├── main.py                     # Bootstrap FastAPI y ciclo de vida
│   ├── config.py                   # Ajustes de entorno y seguridad
│   ├── models/                     # Modelos SQLAlchemy y Pydantic
│   │   ├── user.py                 # Usuarios y roles RBAC
│   │   ├── agent.py                # Agentes registrados y estados
│   │   ├── token.py                # Bootstrap tokens (TTL 24h)
│   │   └── approval.py             # Historial de solicitudes HITL
│   ├── auth/                       # Autenticación y control de accesos
│   │   ├── jwt.py                  # Emisión y verificación de tokens JWT
│   │   └── permissions.py          # Decoradores de permisos RBAC
│   ├── services/                   # Lógica de negocio
│   │   ├── agent_registry.py       # Generación de tokens y registro de agentes
│   │   ├── heartbeat_monitor.py    # Recolección y chequeo de salud
│   │   ├── hitl_service.py         # Orquestación de aprobaciones y rechazos
│   │   └── agent_client.py         # Cliente HTTP hacia jg_agente (API interna)
│   ├── adapters/                   # Normalización de estímulos externos
│   │   └── base_adapter.py         # Conversión hacia AgentInput / AgentOutput
│   ├── api/                        # Rutas HTTP
│   │   ├── v1/
│   │   │   ├── auth.py             # Login, logout y perfil
│   │   │   ├── agents.py           # CRUD de agentes, /register y /heartbeat
│   │   │   ├── approvals.py        # Listado, /approve y /reject
│   │   │   └── executions.py       # Trigger normalizado de tareas
│   └── ui/                         # Interfaz gráfica web (HTML/CSS/JS o SPA)
│       ├── templates/
│       └── static/
└── tests/                          # Tests unitarios y de integración
    ├── unit/
    └── integration/
```

---

## 6. Estado Actual del Proyecto

- **Fase actual:** Arquitectura de control plane consolidada y backlog de issues preparado.
- **Implementación:** Pendiente de inicio formal mediante ejecución ordenada del backlog.

---

## 7. Roadmap de Alto Nivel

- [ ] **Fase 0:** Bootstrap, tooling, Docker y entorno de desarrollo.
- [ ] **Fase 1:** Persistencia y modelos de base de datos PostgreSQL (`dashboard_*`).
- [ ] **Fase 2:** Autenticación de usuarios y RBAC (Admin, Operator, Viewer).
- [ ] **Fase 3:** Sistema de registro de agentes con `bootstrap_token` de 24h y credencial permanente.
- [ ] **Fase 4:** Monitoreo de agentes y procesamiento de heartbeats periódicos.
- [ ] **Fase 5:** Cliente HTTP proxy y orquestación de ejecuciones hacia `jg_agente`.
- [ ] **Fase 6:** Consola y endpoints de aprobación Human-In-The-Loop (HITL).
- [ ] **Fase 7:** Adaptador normalizador de entrada/salida (`AgentInput` / `AgentOutput`).
- [ ] **Fase 8:** Interfaz gráfica web del Dashboard (visión de agentes, ejecuciones y HITL).
- [ ] **Fase 9:** Integración end-to-end con `jg_agente` (Casos de Éxito A y B).
