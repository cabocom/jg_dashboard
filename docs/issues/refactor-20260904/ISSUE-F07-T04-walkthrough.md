# Walkthrough Técnico: [ISSUE-F07-T04] Endpoint de rechazo HITL: POST /api/v1/approvals/{approval_id}/reject

**Issue ID:** ISSUE-F07-T04  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  

---

## 1. Cambios Implementados

### 1.1 Servicio de Dominio (`HitlApprovalService`)
- **Archivo Modificado:** `app/Services/Hitl/HitlApprovalService.php`
- **Método `rejectApproval`:**
  - Valida la obligatoriedad de la justificación.
  - Comprueba existencia (404) y estado `PENDING` (409).
  - Invoca `AgentHttpClient::forAgent($agent)->rejectExecution()` con cabeceras firmadas HMAC-SHA256.
  - Actualiza el estado a `REJECTED`, guardando el ID del operador, timestamp y motivo.
  - Emite el evento de auditoría `hitl.rejected`.

### 1.2 Controlador HITL (`HitlApprovalController`)
- **Archivo Modificado:** `app/Http/Controllers/Api/HitlApprovalController.php`
- **Método `reject`:**
  - Aplica validación estricta de formulario: `justification: required|string|min:5|max:1000`.
  - Delega en `HitlApprovalService::rejectApproval()`.
  - Retorna `200 OK` con el objeto actualizado y la confirmación devuelta por el agente.

### 1.3 Rutas de API
- **Archivo Modificado:** `routes/api.php`
  - Añadida `POST /api/v1/approvals/{approval_id}/reject` bajo `role:operator`.
  - Añadido alias raíz `POST /api/approvals/{approval_id}/reject`.

### 1.4 Suite de Pruebas de Integración
- **Archivo Creado:** `tests/Feature/HitlApprovalRejectApiTest.php`
  - Verificación de rechazo anónimo (401) y rol `viewer` (403).
  - Verificación de rechazo por justificación ausente o corta (422).
  - Validación de 404 para identificadores inexistentes.
  - Validación de 409 para solicitudes ya resueltas.
  - Validación de 400 para agentes en estado `PENDING`.
  - Verificación de rechazo exitoso con `Http::fake()` asegurando firma HMAC y registro en BD y auditoría.
  - Verificación de acceso para rol `admin` mediante jerarquía RBAC.
  - Verificación de tolerancia a fallos del agente preservando el estado `PENDING`.
  - Verificación del alias raíz.

### 1.5 Suite de Documentación Arquitectónica
- **Archivos Creados en `jg_dashboard/docs/`:**
  - `docs/architecture.md`: Visión del Control Plane, principios de desacoplamiento y modelo HTTP proxy.
  - `docs/security.md`: Zero-Trust, tokens TTL 24h, HMAC-SHA256 y matriz RBAC.
  - `docs/database.md`: Modelos PostgreSQL, esquemas, índices y campos JSON.
  - `docs/hitl.md`: Máquinas de estado HITL, transiciones, idempotencia y compensación.
  - `docs/agents.md`: Ciclo de vida de agentes, heartbeats, monitor background y capacidades.

---

## 2. Verificación de Calidad

Ejecución de `./scripts/check.sh`:

```bash
./scripts/check.sh
```

**Resultado:**
- 100% de tests en verde (198 tests, 900 aserciones).
- Cero errores o advertencias de linter y formato.
