# Walkthrough: ISSUE-F04-T02 — Endpoint administrativo: POST /api/v1/admin/agents

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el endpoint administrativo protegido para el aprovisionamiento de agentes en `jg_dashboard`:

1. **Controlador Administrativo ([app/Http/Controllers/Api/AdminAgentController.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Http/Controllers/Api/AdminAgentController.php)):**
   - Inyección de dependencias de `AgentRegistrationService`.
   - `store()`: Valida payload de entrada (`name`, `version`, `capabilities`, `metadata`, `ttl_hours`), delega la creación al servicio y retorna respuesta HTTP `201 Created` con `agent_id`, `bootstrap_token`, estado y caducidad.
   - `index()`: Consulta y lista todos los agentes ordenados por fecha descendente (código `200 OK`).
   - `regenerateToken()`: Valida TTL opcional, invoca regeneración para agentes `PENDING` y devuelve el nuevo token o código `400 Bad Request` si el agente no es apto.

2. **Rutas de API ([routes/api.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/routes/api.php)):**
   - Agrupación de rutas bajo `jwt.auth` y `role:admin`:
     - `POST /api/v1/admin/agents` -> `AdminAgentController@store`
     - `GET /api/v1/admin/agents` -> `AdminAgentController@index`
     - `POST /api/v1/admin/agents/{agent_id}/regenerate-token` -> `AdminAgentController@regenerateToken`
   - Configuración de alias sin prefijo v1 (`/api/admin/agents`).

3. **Suite de Pruebas de Integración y Feature ([tests/Feature/AdminAgentApiTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/AdminAgentApiTest.php)):**
   - 9 pruebas completas que verifican:
     - Retorno `401 Unauthorized` ante peticiones anónimas.
     - Retorno `403 Forbidden` ante usuarios con rol `VIEWER`.
     - Retorno `403 Forbidden` ante usuarios con rol `OPERATOR`.
     - Retorno `422 Unprocessable Entity` ante errores de validación (nombre ausente).
     - Retorno `201 Created` con `agent_id` y `bootstrap_token` para rol `ADMIN`.
     - Persistencia del agente con estado `PENDING` en `dashboard_agents`.
     - Persistencia exclusiva del hash SHA-256 en `dashboard_bootstrap_tokens`.
     - Registro de auditoría `AGENT_CREATED` con usuario creador en `dashboard_audit_events`.
     - Listado de agentes exclusivo para administradores (`GET /api/v1/admin/agents`).
     - Regeneración de token para agentes `PENDING` y caducidad del anterior.
     - Rechazo con `400 Bad Request` al intentar regenerar tokens en agentes `ACTIVE`.

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
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AdminAgentApiTest
  ✓ unauthenticated request to POST /api/v1/admin/agents returns 401 unauthorized
  ✓ viewer role receives 403 forbidden on POST /api/v1/admin/agents
  ✓ operator role receives 403 forbidden on POST /api/v1/admin/agents
  ✓ validation error returns 422 when agent name is missing or invalid
  ✓ admin role registers new agent and receives 201 with agent_id and bootstrap_token
  ✓ admin can list registered agents via GET /api/v1/admin/agents
  ✓ non-admin roles cannot list agents via GET /api/v1/admin/agents
  ✓ admin can regenerate bootstrap token for a PENDING agent
  ✓ regenerate token returns 400 if agent is already ACTIVE
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\InitAdminCommandTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    87 passed (417 assertions)
  Duration: 1.92s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```
