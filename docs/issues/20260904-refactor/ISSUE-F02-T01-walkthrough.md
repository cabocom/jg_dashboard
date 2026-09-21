# Walkthrough: ISSUE-F02-T01 — Modelos de base de datos para usuarios y roles RBAC

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha consolidado la capa de persistencia y autorización RBAC de usuarios en `jg_dashboard`:

1. **Migración de Usuarios ([database/migrations/0001_01_01_000000_create_users_table.php](../../../database/migrations/0001_01_01_000000_create_users_table.php)):**
   - Creación de tabla `users` con campo `role` por defecto `'operator'`.
   - Soporte para tablas de tokens de reseteo de contraseñas (`password_reset_tokens`) y sesiones en base de datos (`sessions`).

2. **Modelo Eloquent con RBAC ([app/Models/User.php](../../../app/Models/User.php)):**
   - Implementa `Filament\Models\Contracts\FilamentUser`.
   - `canAccessPanel(Panel $panel)`: Devuelve `true` únicamente si `$this->role` pertenece a `['admin', 'operator']`.
   - Métodos auxiliares: `isAdmin()` e `isOperator()`.
   - Cast de contraseñas hasheadas: `'password' => 'hashed'`.

3. **Seeder Seguro ([database/seeders/DatabaseSeeder.php](../../../database/seeders/DatabaseSeeder.php)):**
   - Inyección obligatoria de `ADMIN_DEFAULT_PASSWORD` para provisionar o rotar credenciales del administrador inicial.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\DatabaseSeederTest
  ✓ seeder throws exception when admin password is not set               0.19s  
  ✓ seeder creates admin user with explicit password                     0.01s  
  ✓ seeder updates existing admin user on password rotation              0.01s  

   PASS  Tests\Feature\AgentTaskResourceTest
  ✓ unauthorized role user cannot access dashboard                       0.01s  
  ✓ user role helper methods                                             0.01s  
  ✓ authenticated user can access dashboard                              0.05s  

  Tests:    25 passed (88 assertions)
  Duration: 0.94s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Estado de Criterios de Aceptación

- [x] Migración nativa de base de datos para la tabla `users` con soporte de roles.
- [x] Modelo Eloquent `App\Models\User` implementando `FilamentUser` y autorización RBAC.
- [x] Helpers de comprobación de roles (`isAdmin()`, `isOperator()`).
- [x] Tests unitarios y de integración validando RBAC y bloqueo con HTTP 403 para usuarios con rol `viewer`.
