# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 en progreso (`ISSUE-F01-T01` e `ISSUE-F01-T02` completados)  
> **Próxima Tarea Inmediata:** `ISSUE-F01-T03` (Pipeline de CI/CD para jg_dashboard)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** del ecosistema jG. Es el **único componente expuesto hacia el exterior**, asegurando que los agentes de ejecución (`jg_agente`) operen aislados en redes internas privadas sin acceso directo a Internet.

### Principios de Aislamiento de Infraestructura:
- **Separación Radical de Persistencia:** Cada plano mantiene su propia base de datos. El Dashboard tiene su base de datos PostgreSQL exclusiva en el puerto 5434 con volumen `jg_dashboard_postgres_data`. **Nunca se conecta a la base de datos de los agentes (puerto 5433)**.
- **Comunicación Vía API REST:** La orquestación y aprobación HITL se realiza estrictamente a través de peticiones HTTP autenticadas hacia los endpoints del agente.
- **Arranque Controlado:** Los contenedores en Docker Compose declaran dependencias mediante comprobaciones de salud activas (`pg_isready`).

---

## 2. Estado Actual del Repositorio

### 2.1 Tooling y Contenerización
- `Dockerfile`: Multi-stage con `composer:2.7` y `php:8.3-fpm-alpine` + Nginx + Supervisord.
- `docker-compose.yml`: Orquestación local con PostgreSQL 16 (puerto host 5434) y Dashboard web (puerto 8080).
- Suite de pruebas: Pest PHP v3 con 25 tests pasando en verde.
- Script de verificación: `./scripts/check.sh`.

### 2.2 Estructura de Ficheros de Infraestructura
```text
jg_dashboard/
├── Dockerfile                      # Multi-stage build (PHP 8.3-FPM + Nginx)
├── docker-compose.yml              # Orquestación con PostgreSQL aislado (5434)
├── docker/
│   ├── entrypoint.sh               # Inicialización de permisos, migraciones y servicios
│   ├── nginx.conf                  # Configuración de Nginx reverse proxy
│   ├── php.ini                     # Opcache y memoria de PHP
│   └── supervisord.conf            # Supervisor para Nginx y PHP-FPM
├── composer.json
├── app/
├── database/
├── scripts/
│   └── check.sh
└── docs/
    └── issues/
        ├── ISSUE-F01-T01-*
        └── ISSUE-F01-T02-*
```

---

## 3. Estado de la Fase Actual (Fase 01)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F01-T01` | Estructura base del repositorio jg_dashboard y configuración de entorno | **COMPLETADO** |
| `ISSUE-F01-T02` | Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio | **COMPLETADO** |
| `ISSUE-F01-T03` | Pipeline de CI/CD para jg_dashboard | Pendiente |
