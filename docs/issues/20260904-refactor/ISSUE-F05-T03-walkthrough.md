# Walkthrough: ISSUE-F05-T03 — Endpoint de listado y estado de agentes: GET /api/v1/agents

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el endpoint de listado y consulta de salud de agentes en `jg_dashboard`:

1. **Controlador API ([app/Http/Controllers/Api/AgentController.php](../../../app/Http/Controllers/Api/AgentController.php)):**
   - Método `index()`:
     - Valida parámetros de consulta (`status`, `search`, `per_page`).
     - Construye la consulta paginada ordenada por fecha de creación descendente.
     - Agrega el bloque de métricas globales `summary` (`total`, `active`, `offline`, `pending`).
     - Formatea cada agente incorporando `is_healthy` evaluado en tiempo real (umbral 90s), capacidades y métricas de telemetría.
     - Oculta de forma estricta cualquier hash de credencial permanente.
   - Método `show()`:
     - Devuelve los detalles exhaustivos de un agente específico o responde `404 Not Found`.

2. **Enrutamiento y RBAC ([routes/api.php](../../../routes/api.php)):**
   - Agregadas las rutas `GET /api/v1/agents` y `GET /api/v1/agents/{agent_id}` bajo el middleware `jwt.auth`.
   - Agregados los alias correspondientes bajo `/api/agents`.

3. **Suite de Pruebas de Feature ([tests/Feature/AgentListApiTest.php](../../../tests/Feature/AgentListApiTest.php)):**
   - 8 pruebas exhaustivas validando:
     - Rechazo `401 Unauthorized` ante peticiones anónimas.
     - Respuesta `200 OK` con paginación y resumen de métricas para rol `VIEWER`.
     - Acceso garantizado para roles `OPERATOR` y `ADMIN`.
     - Filtrado preciso por estado (`?status=ACTIVE`).
     - Búsqueda por nombre o UUID (`?search=alpha`).
     - Consulta individual detallada de agente (`GET /api/v1/agents/{agent_id}`).
     - Retorno `404 Not Found` para identificadores inexistentes.
     - Funcionamiento idéntico del alias `/api/agents`.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentHealthMonitorServiceTest
   PASS  Tests\Unit\AgentHeartbeatServiceTest
   PASS  Tests\Unit\AgentModelTest
   PASS  Tests\Unit\AgentRegistrationServiceTest
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AdminAgentApiTest
   PASS  Tests\Feature\AgentHeartbeatApiTest
   PASS  Tests\Feature\AgentListApiTest
  ✓ unauthenticated request to GET /api/v1/agents returns 401 unauthorized
  ✓ authenticated user can query GET /api/v1/agents with pagination and aggregated summary metrics
  ✓ operator and admin roles can also access GET /api/v1/agents
  ✓ filter by status returns only agents with requested status
  ✓ search query filters agents by name or agent_id
  ✓ GET /api/v1/agents/{agent_id} returns detailed single agent state and health
  ✓ GET /api/v1/agents/{agent_id} returns 404 when agent does not exist
  ✓ root alias /api/agents functions identically
   PASS  Tests\Feature\AgentRegistrationApiTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest
   PASS  Tests\Feature\CheckAgentHealthCommandTest
   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\InitAdminCommandTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    127 passed (608 assertions)
  Duration: 2.44s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```
