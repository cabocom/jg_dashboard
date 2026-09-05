# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 completada al 100% (`ISSUE-F01-T01`, `ISSUE-F01-T02` e `ISSUE-F01-T03` completados)  
> **Próxima Tarea Inmediata:** Fase 02 — Persistencia y modelos de base de datos PostgreSQL (`dashboard_*`)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas sin acceso directo a Internet.

---

## 2. Estado Actual del Repositorio

### 2.1 Stack Tecnológico y Tooling
- **Framework Web:** Laravel 11.x
- **Panel Administrativo:** Filament PHP v3.2 (Livewire 3 + Alpine.js + Tailwind CSS)
- **Base de Datos Local:** PostgreSQL 16 dedicada (puerto 5434, volumen `jg_dashboard_postgres_data`)
- **Testing:** Pest PHP v3 (25 tests pasando)
- **CI/CD:** `.gitlab-ci.yml` con jobs `test:pest` y `build:docker`

### 2.2 Estructura del Proyecto
```text
jg_dashboard/
├── .gitlab-ci.yml                  # Pipeline CI/CD (Pest PHP + Docker Build)
├── Dockerfile                      # Multi-stage build (PHP 8.3-FPM + Nginx)
├── docker-compose.yml              # Orquestación con PostgreSQL aislado (5434)
├── composer.json                   # Dependencias Laravel 11 y Filament v3
├── app/
│   ├── Filament/                   # Panel Filament y recursos reactivos
│   ├── Models/                     # Modelos Eloquent ORM
│   └── Services/                   # Cliente REST hacia jg_agente
├── database/                       # Migraciones y seeders
├── scripts/
│   └── check.sh                    # Script de verificación integral
└── docs/
    └── issues/                     # Trazabilidad técnica por issue
```

---

## 3. Estado de la Fase 01 (100% Completada)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F01-T01` | Estructura base del repositorio jg_dashboard y configuración de entorno | **COMPLETADO** |
| `ISSUE-F01-T02` | Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio | **COMPLETADO** |
| `ISSUE-F01-T03` | Pipeline de CI/CD para jg_dashboard | **COMPLETADO** |
