# Guía de Implementación y Walkthrough: [ISSUE-F06-T02] Pasarela para disparar ejecuciones: POST /api/v1/executions/trigger

**Issue ID:** ISSUE-F06-T02  
**Fase:** FASE 06  
**Fecha:** 5 de septiembre de 2026  

---

## 1. Resumen de Cambios

Se implementó el controlador de ejecuciones `ExecutionController` y los endpoints correspondientes para permitir a operadores y administradores disparar ejecuciones cognitivas hacia `jg_agente` desde el Control Plane.

### Componentes Creados y Modificados:
1. `app/Http/Controllers/Api/ExecutionController.php`: Controlador que valida la entrada, normaliza a `AgentInput`, delega la llamada a `AgentHttpClient`, registra auditoría y almacena planes HITL.
2. `routes/api.php`: Rutas protegidas `POST /api/v1/executions/trigger` y alias `POST /api/executions/trigger` con middlewares `jwt.auth` y `role:operator`.
3. `tests/Feature/ExecutionTriggerApiTest.php`: Suite completa de pruebas de integración con 11 casos de prueba cubriendo RBAC, validación, mocks de red y persistencia.
4. `README.md` & `PROJECT_CONTEXT.md`: Actualización del roadmap y especificación arquitectónica a la versión 3.9.0.

---

## 2. Ejemplos de Uso

### 2.1 Disparo Síncrono de Ejecución
```bash
curl -X POST http://localhost:8000/api/v1/executions/trigger \
  -H "Authorization: Bearer <TOKEN_OPERATOR>" \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Comprobar salud de los pods en el namespace producción",
    "agent_id": "00000000-0000-0000-0000-000000000001"
  }'
```

**Respuesta Exitosa (HTTP 200 OK):**
```json
{
  "execution_id": "3b16957a-97ad-4560-8451-ea26c3674da3",
  "thread_id": "893c5d6e-473d-4c3e-a745-9852f86588aa",
  "agent_id": "00000000-0000-0000-0000-000000000001",
  "status": "completed",
  "requires_approval": false,
  "pending_approval_id": null,
  "output": "Todos los pods del namespace producción se encuentran en estado Running.",
  "data": { ... },
  "latency_ms": 45.2
}
```

### 2.2 Disparo Asíncrono de Ejecución (HTTP 202 Accepted)
```bash
curl -X POST http://localhost:8000/api/v1/executions/trigger \
  -H "Authorization: Bearer <TOKEN_OPERATOR>" \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Generar reporte consolidado de auditoría nocturna",
    "async": true
  }'
```

---

## 3. Verificación Automatizada

Ejecución de la suite de pruebas del proyecto:
```bash
./scripts/check.sh
```
Todas las comprobaciones pasaron con éxito.
