# Guía de Implementación y Walkthrough: [ISSUE-F06-T03] Consulta de estado de ejecuciones: GET /api/v1/executions/{agent_id}/{thread_id}

**Issue ID:** ISSUE-F06-T03  
**Fase:** FASE 06  
**Fecha:** 5 de septiembre de 2026  

---

## 1. Resumen de Cambios

Se implementó el método `show` en `ExecutionController` y las rutas asociadas en `routes/api.php` para consultar de forma segura y tipada el estado de cualquier hilo de ejecución alojado en `jg_agente`.

### Componentes Creados y Modificados:
1. `app/Http/Controllers/Api/ExecutionController.php`: Incorporación del método `show(Request $request, string $agentId, string $threadId)` con resolución de agente, delegación por `AgentHttpClient` y unificación con el estado de aprobación HITL local.
2. `routes/api.php`: Registro de `GET /api/v1/executions/{agent_id}/{thread_id}` y alias `GET /api/executions/{agent_id}/{thread_id}` bajo middleware `jwt.auth`.
3. `tests/Feature/ExecutionStatusApiTest.php`: Batería de pruebas de integración con 9 tests cubriendo autenticación, roles, rechazos 400/404, fallos de conectividad y mocks de red.
4. `README.md` & `PROJECT_CONTEXT.md`: Actualización del roadmap de la Fase 06 y contexto arquitectónico a la versión 3.10.0.

---

## 2. Ejemplos de Uso

### Consulta de Estado de Ejecución
```bash
curl -X GET http://localhost:8000/api/v1/executions/00000000-0000-0000-0000-000000000001/thread_xyz_123 \
  -H "Authorization: Bearer <TOKEN_JWT>" \
  -H "Accept: application/json"
```

**Respuesta Exitosa (HTTP 200 OK):**
```json
{
  "agent_id": "00000000-0000-0000-0000-000000000001",
  "agent_name": "Agent-Core-01",
  "thread_id": "thread_xyz_123",
  "execution_id": "exec_abc_456",
  "status": "waiting_approval",
  "prompt": "Reiniciar servicio crítico de base de datos",
  "output": null,
  "current_step": 2,
  "next_nodes": ["execute_tool"],
  "requires_approval": true,
  "approval_status": "PENDING",
  "pending_approval_id": "chk_hitl_01",
  "plan": {
    "action": "restart_database",
    "risk": "CRITICAL"
  },
  "steps": [
    { "name": "plan_generation", "status": "completed" },
    { "name": "security_guard", "status": "paused" }
  ],
  "hitl_governance": {
    "approval_id": 14,
    "status": "PENDING",
    "risk_level": "CRITICAL",
    "justification": null,
    "resolved_at": null
  },
  "latency_ms": 22.4
}
```

---

## 3. Verificación Automatizada

Ejecución de la suite completa de pruebas:
```bash
./scripts/check.sh
```
Todas las comprobaciones de código y pruebas automáticas pasaron en verde.
