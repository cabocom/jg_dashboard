# Walkthrough Técnico: [ISSUE-F07-T03] Endpoint de aprobación HITL: POST /api/v1/approvals/{approval_id}/approve

**Issue ID:** ISSUE-F07-T03  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  

---

## 1. Cambios Implementados

### 1.1 Servicio de Dominio (`HitlApprovalService`)
- **Archivo Modificado:** `app/Services/Hitl/HitlApprovalService.php`
- **Método `approveApproval`:**
  - Valida existencia (404) y estado `PENDING` (409).
  - Valida disponibilidad del agente emisor (400 si está `PENDING` o no existe).
  - Delega el comando hacia `jg_agente` invocando `AgentHttpClient::forAgent($agent)->approveExecution()`.
  - Si el agente responde positivamente, transiciona el modelo a `APPROVED`, asocia el `user_id` del operador y guarda la justificación opcional.
  - Registra el evento de auditoría `hitl.approved` con metadatos del hilo, agente, operador e IP.

### 1.2 Controlador HITL (`HitlApprovalController`)
- **Archivo Modificado:** `app/Http/Controllers/Api/HitlApprovalController.php`
- **Método `approve`:**
  - Valida el payload de entrada (`justification` opcional).
  - Invoca `HitlApprovalService::approveApproval()`.
  - Retorna `200 OK` con el objeto de aprobación enriquecido y la respuesta del agente, o errores tipificados (400, 404, 409, 502/503).

### 1.3 Registro de Rutas
- **Archivo Modificado:** `routes/api.php`
  - Añadida `POST /api/v1/approvals/{approval_id}/approve` bajo `jwt.auth` y `role:operator`.
  - Añadido alias raíz `POST /api/approvals/{approval_id}/approve`.

### 1.4 Pruebas de Integración
- **Archivo Creado:** `tests/Feature/HitlApprovalApproveApiTest.php`
  - Pruebas de autenticación y autorización (401 y 403).
  - Validación de 404 Not Found para IDs inexistentes.
  - Validación de 409 Conflict para solicitudes ya resueltas.
  - Validación de 400 Bad Request para agentes en estado `PENDING`.
  - Prueba integral con `Http::fake()` verificando firma HMAC, cabeceras de control plane, persistencia en BD y emisión de auditoría.
  - Prueba de rol `admin` mediante jerarquía.
  - Prueba de tolerancia a fallos del agente dejando la solicitud en estado `PENDING`.
  - Prueba del alias raíz `/api/approvals/{id}/approve`.

---

## 2. Verificación de Calidad

Ejecución de `./scripts/check.sh`:

```bash
./scripts/check.sh
```

**Resultado:**
- 100% de tests en verde (188 tests, 859 aserciones).
- Cero advertencias de sintaxis o linter.
