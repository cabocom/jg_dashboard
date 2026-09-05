# Walkthrough: ISSUE-F04-T04 — Validación estricta de caducidad (TTL 24h) y revocación de tokens

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se han implementado las validaciones estrictas de caducidad, uso único y revocación de tokens en `jg_dashboard`:

1. **Modelo `BootstrapToken` ([app/Models/BootstrapToken.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/BootstrapToken.php)):**
   - Actualización de `isExpired()` para validar tanto el campo `expires_at` como el límite absoluto estricto de 24 horas a partir del timestamp de creación (`created_at`).
   - Incorporación del método `revoke()` para invalidación manual inmediata.

2. **Servicio de Registro ([app/Services/Agents/AgentRegistrationService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Services/Agents/AgentRegistrationService.php)):**
   - Bloqueo pesimista `lockForUpdate()` en el proceso de consumo del token para evitar condiciones de carrera.
   - Retorno estricto de código HTTP `401 Unauthorized` para tokens expirados (`>24h`), consumidos o alterados.
   - Implementación del método `revokeBootstrapTokens($agent, $adminUser)` con registro de auditoría `AGENT_BOOTSTRAP_TOKENS_REVOKED`.

3. **Controladores y Rutas ([app/Http/Controllers/Api/AdminAgentController.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Http/Controllers/Api/AdminAgentController.php), [routes/api.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/routes/api.php)):**
   - Endpoint administrativo `POST /api/v1/admin/agents/{agent_id}/revoke-tokens` protegido por `role:admin`.
   - Ajuste en `AgentRegistrationController` para responder uniformemente `401 Unauthorized` ante cualquier fallo de validación de token.

4. **Suite de Pruebas ([tests/Unit/AgentRegistrationServiceTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Unit/AgentRegistrationServiceTest.php), [tests/Feature/AgentRegistrationApiTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/AgentRegistrationApiTest.php), [tests/Feature/AdminAgentApiTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/AdminAgentApiTest.php)):**
   - Validación de respuesta 401 para tokens expirados (>24h).
   - Validación de respuesta 401 para tokens ya consumidos (uso único).
   - Validación de respuesta 401 para tokens revocados administrativamente.
   - Verificación del endpoint administrativo de revocación y protección RBAC.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentModelTest
   PASS  Tests\Unit\AgentRegistrationServiceTest
  ✓ consumeBootstrapTokenAndActivate rejects already consumed token
  ✓ consumeBootstrapTokenAndActivate rejects expired token
  ✓ revokeBootstrapTokens invalidates active tokens and logs audit event
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AdminAgentApiTest
  ✓ admin can revoke tokens via POST /api/v1/admin/agents/{id}/revoke-tokens
  ✓ operator and viewer cannot revoke tokens
   PASS  Tests\Feature\AgentRegistrationApiTest
  ✓ POST /api/v1/agents/register returns 401 when bootstrap token is missing
  ✓ subsequent registration attempt with already consumed token returns 401 unauthorized
  ✓ registration attempt with expired bootstrap token (>24h) returns 401 unauthorized
  ✓ registration attempt with non-existent token returns 401 unauthorized
  ✓ registration attempt with revoked token returns 401 unauthorized
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\InitAdminCommandTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    102 passed (488 assertions)
  Duration: 2.05s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```
