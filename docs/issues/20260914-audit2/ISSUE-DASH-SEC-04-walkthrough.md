# Walkthrough: [DASH-SEC-04] Ausencia de Verificación CSRF en Acciones HITL de Consola Web

## Resumen de Cambios

### 1. `jg_dashboard/bootstrap/app.php`
- Se retiró `'console/*'` de la lista `except` en `$middleware->validateCsrfTokens(...)`.
- Todas las rutas web de la consola ahora quedan sujetas a la verificación estricta de tokens CSRF.

### 2. `jg_dashboard/resources/views/console/index.blade.php`
- Se añadió la directiva `@csrf` como defensa en profundidad dentro de los formularios:
  - Formulario de disparo de ejecuciones (`#form-trigger-execution`)
  - Formulario de perfil de usuario (`#form-user-profile`)
  - Formulario modal de confirmación de aprobación HITL (`#form-confirm-approve`)
  - Formulario modal de confirmación de rechazo HITL (`#form-confirm-reject`)
  - Formulario modal de aprovisionamiento de nuevo agente (`#form-create-agent`)

### 3. Tests de Feature en `jg_dashboard/tests/Feature/HitlConsoleWebTest.php`
- Se agregaron 6 pruebas de integración específicas para CSRF:
  - `POST /console/approvals/{id}/approve` sin CSRF devuelve `419`.
  - `POST /console/approvals/{id}/reject` sin CSRF devuelve `419`.
  - `POST /console/approvals/{id}/approve` con cabecera `X-CSRF-TOKEN` válida es admitido y responde con `200`.
  - `POST /console/approvals/{id}/reject` con cabecera `X-CSRF-TOKEN` válida es admitido y responde con `200`.
  - `POST /console/executions/trigger` sin CSRF devuelve `419`.
  - `POST /console/agents` sin CSRF devuelve `419`.

---

## Verificación de Pruebas

```bash
# Suite de pruebas Pest en jg_dashboard
cd jg_dashboard && ./vendor/bin/pest
# 283 passed (1328 assertions)

# Verificación global del workspace
bash check_unit.sh
# 719 tests pasados exitosamente
```
