# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 02 avanzada (`ISSUE-F02-T01`, `ISSUE-F02-T02` e `ISSUE-F02-T03` completados)  
> **Próxima Tarea Inmediata:** `ISSUE-F02-T04` (Modelos de base de datos para solicitudes HITL y eventos de auditoría)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Modelos de Persistencia Activos
- `App\Models\User`: Usuarios administradores y operadores, RBAC (`admin`, `operator`, `viewer`), contraseñas hasheadas.
- `App\Models\Agent`: Agentes registrados (`dashboard_agents`), UUIDs, estados (`PENDING`, `ACTIVE`, `OFFLINE`), secret hash protegido, heartbeats, capacidades y relación `bootstrapTokens()`.
- `App\Models\BootstrapToken`: Tokens de aprovisionamiento de un solo uso con TTL 24h (`dashboard_bootstrap_tokens`), SHA-256 hash oculto, control de consumo atómico y relación `agent()`.
- `App\Models\AgentTask`: Historial de ejecuciones y tareas HITL.
- `database/migrations/`:
  - `0001_01_01_000000_create_users_table.php`
  - `2026_09_05_000001_create_dashboard_agents_table.php`
  - `2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php`

### 2.2 Estructura del Proyecto
```text
jg_dashboard/
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_users_table.php
│       ├── 2026_09_05_000001_create_dashboard_agents_table.php
│       └── 2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php
├── app/
│   ├── Models/
│   │   ├── Agent.php               # Eloquent Agent (dashboard_agents)
│   │   ├── BootstrapToken.php      # Eloquent BootstrapToken (dashboard_bootstrap_tokens)
│   │   ├── User.php                # Eloquent User (users + RBAC)
│   │   └── AgentTask.php
├── tests/
│   └── Unit/
│       ├── AgentModelTest.php          # Tests unitarios del modelo Agent
│       ├── BootstrapTokenModelTest.php # Tests unitarios del modelo BootstrapToken
│       └── ...
└── docs/
    └── issues/
        ├── ISSUE-F01-*
        ├── ISSUE-F02-T01-*
        ├── ISSUE-F02-T02-*
        └── ISSUE-F02-T03-*
```

---

## 3. Estado de la Fase 02 (Persistencia y Base de Datos)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F02-T01` | Modelos de base de datos para usuarios y roles RBAC | **COMPLETADO** |
| `ISSUE-F02-T02` | Modelos de base de datos para agentes registrados | **COMPLETADO** |
| `ISSUE-F02-T03` | Modelos de base de datos para tokens de aprovisionamiento (bootstrap_tokens) | **COMPLETADO** |
| `ISSUE-F02-T04` | Modelos de base de datos para solicitudes HITL y eventos de auditoría | Pendiente |
