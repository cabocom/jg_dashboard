# Análisis Técnico: ISSUE-F01-T02 — Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F01-T02`  
> **Fase:** FASE 01 — Bootstrap, Documentación y Tooling  
> **Prioridad:** High  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F01-T01  

---

## 1. Objetivo del Issue

Contenerizar la aplicación `jg_dashboard` (Control Plane) y orquestar su entorno de ejecución local mediante Docker y Docker Compose, garantizando aislamiento estricto de su base de datos PostgreSQL respecto a la base de datos de los agentes (`jg_agente`), evitando colisiones de puertos y asegurando un ciclo de arranque ordenado mediante chequeos de salud.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Dockerfile Multi-Stage Optimizado:**
   - **Stage 1 (Vendor):** Imagen `composer:2.7` para instalar dependencias de producción de forma optimizada (`--no-dev`, `--optimize-autoloader`).
   - **Stage 2 (Runtime):** Imagen `php:8.3-fpm-alpine` con Nginx, Supervisord y extensiones requeridas (`pdo_pgsql`, `intl`, `bcmath`, `opcache`, `pcntl`).
   - Permisos y directivas de seguridad para usuario no privilegiado (`www-data:www-data`, chmod 775 en storage).
   - Healthcheck nativo (`curl -f http://127.0.0.1:8080/up`).
2. **Orquestación con Docker Compose (`docker-compose.yml`):**
   - Servicio `jg_dashboard_postgres`:
     - Imagen PostgreSQL 16 Alpine.
     - Puerto en host mapeado a `5434:5432` para aislamiento total frente al puerto default (5432) y al del agente (5433).
     - Volumen persistente dedicado: `jg_dashboard_postgres_data`.
     - Healthcheck activo mediante `pg_isready`.
   - Servicio `jg_dashboard`:
     - Construcción directa desde el `Dockerfile`.
     - Dependencia declarada con condición de salud: `condition: service_healthy` sobre PostgreSQL.
     - Mapeo de puerto web en host: `8080:8080`.
     - Variables de entorno para conexión a BD y comunicación con el agente (`JG_AGENTE_API_URL`).
3. **Criterios de Aceptación:**
   - Dashboard y su base de datos levantan y configuran mediante `docker compose config` sin errores sintácticos.
   - Conectividad aislada y persistencia garantizada en volumen exclusivo.

---

## 3. Decisiones Técnicas y Diseño

- **Aislamiento de Puertos:**
  - Puerto 5432: Base de datos local por defecto.
  - Puerto 5433: Base de datos `jg_agente` (`core_checkpoints`).
  - Puerto 5434: Base de datos `jg_dashboard` (`dashboard_*`).
  - Puerto 8080: Puerto web unificado de `jg_dashboard` (Nginx reverse proxy a PHP-FPM 9000).
- **Entrypoint Robusto (`docker/entrypoint.sh`):**
  - Preparación de directorios `storage/` y `bootstrap/cache/`.
  - Descubrimiento de paquetes Laravel (`package:discover`).
  - Publicación de assets de Filament y Livewire.
  - Ejecución condicional de migraciones (`php artisan migrate --force`).
  - Ejecución de Supervisord para gestionar concurrentemente Nginx y PHP-FPM.

---

## 4. Plan de Verificación

1. Validación de sintaxis e interpolación de variables en Docker Compose:
   ```bash
   docker compose config
   ```
2. Ejecución de la suite de pruebas automatizadas:
   ```bash
   ./scripts/check.sh
   ```
