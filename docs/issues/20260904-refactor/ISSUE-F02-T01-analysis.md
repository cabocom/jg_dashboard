# Análisis Técnico: ISSUE-F02-T01 — Modelos de base de datos para usuarios y roles RBAC

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F02-T01`  
> **Fase:** FASE 02 — Persistencia y Modelos de Base de Datos  
> **Prioridad:** Critical  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F01-T01  

---

## 1. Objetivo del Issue

Definir la capa de persistencia y el modelo Eloquent para la gestión de usuarios administrativos y operadores del Control Plane (`jg_dashboard`), implementando control de acceso basado en roles (**RBAC: admin, operator, viewer**), contraseñas hasheadas mediante algoritmos criptográficos robustos (Bcrypt/Argon2 nativo de Laravel), integración con Filament vía `FilamentUser` y migraciones declarativas administradas por Laravel.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Migración de Base de Datos (`database/migrations/`):**
   - Tabla `users` gestionada mediante el sistema nativo de migraciones de Laravel (`Schema::create`).
   - Campos: `id`, `name`, `email` (unique), `password`, `role` (default: 'operator'), `email_verified_at`, `remember_token`, `timestamps`.
2. **Modelo Eloquent (`App\Models\User`):**
   - Implementación de la interfaz `Filament\Models\Contracts\FilamentUser`.
   - Restricción de acceso al panel mediante el método `canAccessPanel(Panel $panel)`, admitiendo únicamente los roles `admin` y `operator`.
   - Helpers de autorización: `isAdmin(): bool` e `isOperator(): bool`.
   - Casting de contraseña hasheada: `'password' => 'hashed'` (Bcrypt/Argon2).
   - Ocultamiento de campos sensibles: `password` y `remember_token` en `$hidden`.
3. **Inicialización y Seeding Seguro (`DatabaseSeeder.php`):**
   - Creación del usuario administrador inicial a partir de la variable obligatoria `ADMIN_DEFAULT_PASSWORD`.
   - Rechazo en arranque con excepción descriptiva si la contraseña maestra no está configurada (CWE-521).
4. **Criterios de Aceptación:**
   - Migración de tabla `users` funcional.
   - Modelo Eloquent `User` con helpers de rol operativos.
   - Restricción RBAC verificada: usuarios con rol `viewer` reciben HTTP 403 al intentar acceder al panel.

---

## 3. Decisiones Técnicas y Diseño

- **Gestor de Migraciones:** Descarte definitivo de herramientas de Python (Alembic) en favor del sistema nativo `php artisan migrate` de Laravel 11.
- **Jerarquía RBAC:**
  - `admin`: Acceso completo a métricas, tareas, configuración y aprobaciones HITL.
  - `operator`: Acceso operacional a tareas, monitoreo y aprobaciones HITL.
  - `viewer`: Acceso restringido/sólo lectura; sin permisos de entrada al panel de control interactivo (403 Forbidden).

---

## 4. Plan de Verificación

- Ejecución de la suite Pest PHP:
  - `Tests\Unit\DatabaseSeederTest`: Verifica creación y rotación del usuario admin.
  - `Tests\Feature\AgentTaskResourceTest`: Verifica helpers de rol y denegación de acceso 403 a usuarios con rol `viewer`.
  ```bash
  ./scripts/check.sh
  ```
