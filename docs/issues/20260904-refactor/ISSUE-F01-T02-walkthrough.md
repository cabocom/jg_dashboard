# Walkthrough: ISSUE-F01-T02 — Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `docker compose config` & `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha configurado la infraestructura de contenedores para `jg_dashboard`:

1. **Dockerfile Multi-Stage ([Dockerfile](../../../Dockerfile)):**
   - Stage 1 con `composer:2.7` instalando dependencias optimizadas de producción.
   - Stage 2 con `php:8.3-fpm-alpine`, Nginx, Supervisord y extensiones requeridas para PostgreSQL y Laravel.
   - Metadatos actualizados con etiquetas de mantenedor y descripción oficial de `jG Dashboard`.
   - Healthcheck nativo mediante `curl` al endpoint `/up`.

2. **Entrypoint del Contenedor ([docker/entrypoint.sh](../../../docker/entrypoint.sh)):**
   - Configuración y ajuste de permisos restrictivos (775) para `www-data`.
   - Descubrimiento de paquetes y publicación de assets estáticos para Filament y Livewire.
   - Ejecución automática de migraciones y arranque de Supervisord.

3. **Orquestación con Docker Compose ([docker-compose.yml](../../../docker-compose.yml)):**
   - Servicio `jg_dashboard_postgres`:
     - Imagen PostgreSQL 16 Alpine.
     - Puerto en host aislado `5434:5432` para evitar colisiones con el agente (5433) o BD local (5432).
     - Volumen persistente dedicado: `jg_dashboard_postgres_data`.
     - Healthcheck activo mediante `pg_isready`.
   - Servicio `jg_dashboard`:
     - Build multi-stage desde `Dockerfile`.
     - Dependencia con condición de salud: `condition: service_healthy` sobre la base de datos.
     - Puerto web en host `8080:8080`.
     - Red interna compartida `jg_dashboard_net`.

4. **Variables de Entorno y Configuración:**
   - [.env.example](../../../.env.example): Puerto por defecto `DB_PORT=5434` y base de datos `DB_DATABASE=jg_dashboard`.

---

## 2. Resultados de la Verificación Automatizada

### Verificación de Docker Compose Config
```text
$ docker compose config
name: jg_dashboard
services:
  jg_dashboard:
    build:
      context: /Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard
      dockerfile: Dockerfile
    container_name: jg_dashboard
    depends_on:
      jg_dashboard_postgres:
        condition: service_healthy
    ports:
    - mode: ingress
      target: 8080
      published: "8080"
      protocol: tcp
  jg_dashboard_postgres:
    container_name: jg_dashboard_postgres
    image: postgres:16-alpine
    ports:
    - mode: ingress
      target: 5432
      published: "5434"
      protocol: tcp
```

### Verificación de Suite de Pruebas
```text
$ ./scripts/check.sh
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    25 passed (88 assertions)
  Duration: 0.98s
========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Estado de Criterios de Aceptación

- [x] Dockerfile multi-stage con compilación optimizada y runtime ligero Alpine.
- [x] `docker-compose.yml` con base de datos PostgreSQL exclusiva en puerto 5434 y volumen aislado.
- [x] Dependencias de arranque entre servicios condicionadas a `service_healthy`.
- [x] Configuración validada y pruebas automatizadas ejecutando al 100%.
