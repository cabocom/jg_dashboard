# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 02 en progreso (`ISSUE-F02-T01` completado)  
> **Próxima Tarea Inmediata:** `ISSUE-F02-T02` (Modelos de base de datos para agentes registrados en dashboard_agents)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Modelos de Persistencia Activos
- `App\Models\User`: Gestión de usuarios del panel, roles RBAC (`admin`, `operator`, `viewer`), contraseñas hasheadas y autorización con Filament.
- `database/migrations/0001_01_01_000000_create_users_table.php`: Migración base de usuarios y sesiones.
- `database/seeders/DatabaseSeeder.php`: Seeder de administrador con control estricto de contraseñas.

### 2.2 Estructura del Proyecto
```text
jg_dashboard/
├── database/
│   ├── migrations/                 # Migraciones nativas Laravel (0001_01_01_create_users_table...)
│   └── seeders/                    # DatabaseSeeder (Admin user)
├── app/
│   ├── Models/
│   │   ├── User.php                # Eloquent User + FilamentUser + RBAC
│   │   ├── AgentTask.php
│   │   └── ...
│   └── Filament/
├── scripts/
│   └── check.sh                    # Verificación integral Pest PHP
└── docs/
    └── issues/
        ├── ISSUE-F01-*
        └── ISSUE-F02-T01-*
```

---

## 3. Estado de la Fase 02 (Persistencia y Base de Datos)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F02-T01` | Modelos de base de datos para usuarios y roles RBAC | **COMPLETADO** |
| `ISSUE-F02-T02` | Modelos de base de datos para agentes registrados | Pendiente |
| `ISSUE-F02-T03` | Modelos de base de datos para tokens de aprovisionamiento (bootstrap_tokens) | Pendiente |
| `ISSUE-F02-T04` | Modelos de base de datos para solicitudes HITL y eventos de auditoría | Pendiente |
