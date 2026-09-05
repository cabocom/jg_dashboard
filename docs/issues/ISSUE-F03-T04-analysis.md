# Análisis Técnico: ISSUE-F03-T04 — Script de inicialización para creación del usuario administrador inicial

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F03-T04`  
> **Fase:** FASE 03 — Autenticación de Usuarios y RBAC  
> **Prioridad:** Medium  
> **Complejidad Estimada:** XS  
> **Dependencias:** ISSUE-F03-T03  

---

## 1. Objetivo del Issue

Proporcionar un comando CLI y mecanismo de inicialización automatizada (`php artisan dashboard:init-admin` y `scripts/init_admin.sh`) para aprovisionar de forma segura e idempotente el usuario con rol `ADMIN` inicial en la base de datos de `jg_dashboard`.
Garantizar:
1. Lectura segura y configurable de credenciales desde variables de entorno (`ADMIN_DEFAULT_PASSWORD`, `ADMIN_EMAIL`, `ADMIN_NAME`) o mediante argumentos CLI.
2. Idempotencia estricta en arranques sucesivos o despliegues en contenedores (si el usuario ya existe, omitir la creación sin provocar fallos ni duplicaciones).
3. Soporte opcional para rotación forzada de contraseñas mediante la bandera `--force`.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Comando Artisan (`App\Console\Commands\InitAdminCommand`):**
   - Firma: `dashboard:init-admin {--email=} {--password=} {--name=} {--force}`.
   - Si no se especifican opciones, lee de `env('ADMIN_EMAIL')`, `env('ADMIN_DEFAULT_PASSWORD')`, `env('ADMIN_NAME')`.
   - Si no hay contraseña provista (opción o entorno): falla con código 1 advirtiendo del riesgo CWE-521.
   - Comprueba si el usuario existe:
     - Si existe y no se incluye `--force`: informa y termina con código de éxito 0.
     - Si existe con `--force`: actualiza la contraseña y metadatos con código 0.
     - Si no existe: crea el usuario con rol `admin` y contraseña hasheada.

2. **Script de Arranque (`scripts/init_admin.sh`):**
   - Script shell ejecutable que invoca `php artisan dashboard:init-admin "$@"` para automatización en Docker o pipelines.

3. **Seeder de Base de Datos (`Database\Seeders\DatabaseSeeder`):**
   - Actualizado para ser compatible con `ADMIN_EMAIL` y `ADMIN_NAME` configurables manteniendo retrocompatibilidad.

4. **Criterios de Aceptación:**
   - Script crea usuario admin inicial si no existe ninguno en la base de datos sin duplicaciones en arranques posteriores.
   - Batería de pruebas automatizadas en Pest PHP verificando creación, idempotencia, rotación con `--force` y rechazo sin contraseña.

---

## 3. Decisiones Técnicas y de Seguridad

- **Seguridad contra Contraseñas por Defecto Débiles (CWE-521):** El comando no utiliza contraseñas por defecto predecibles. Exige que se suministre explícitamente una contraseña mediante opción CLI o mediante la variable de entorno `ADMIN_DEFAULT_PASSWORD`.
- **Idempotencia para CI/CD y Kubernetes/Docker:** Los entornos contenerizados ejecutan scripts de migración y seed en cada inicio de contenedor. El comando garantiza que múltiples ejecuciones sucesivas mantengan el estado sin fallar ni duplicar registros.

---

## 4. Plan de Verificación

- Pruebas en `Tests\Feature\InitAdminCommandTest`:
  - `dashboard:init-admin creates new admin user with cli options`
  - `dashboard:init-admin is idempotent and skips creation if user exists`
  - `dashboard:init-admin with force option updates existing admin password`
  - `dashboard:init-admin fails when password is not provided in cli or env`
  - `dashboard:init-admin uses environment variables when options are omitted`
- Pruebas en `Tests\Unit\DatabaseSeederTest` para asegurar compatibilidad total.
- Verificación global ejecutando `./scripts/check.sh`.
