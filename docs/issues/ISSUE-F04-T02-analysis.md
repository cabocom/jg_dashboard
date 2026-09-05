# Análisis Técnico: ISSUE-F04-T02 — Endpoint administrativo: POST /api/v1/admin/agents

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F04-T02`  
> **Fase:** FASE 04 — Sistema de Registro de Agentes  
> **Prioridad:** High  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F04-T01  

---

## 1. Objetivo del Issue

Implementar y exponer el endpoint administrativo protegido `POST /api/v1/admin/agents` en `jg_dashboard` para que los usuarios con rol `ADMIN` puedan registrar nuevos agentes en el Control Plane y recibir su `agent_id` junto con el `bootstrap_token` criptográfico en texto plano (entregado una sola vez) con código HTTP `201 Created`.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Endpoint Administrativo (`POST /api/v1/admin/agents`):**
   - Recibe payload JSON con:
     - `name` (obligatorio, string max 128)
     - `version` (opcional, string max 32)
     - `capabilities` (opcional, array)
     - `metadata` (opcional, array/object)
     - `ttl_hours` (opcional, entero min 1, max 168; por defecto 24)
   - Valida la solicitud (422 si falta `name` o los tipos son incorrectos).
   - Invoca `AgentRegistrationService::createAgent(...)` pasando los parámetros y el usuario administrador autenticado.
   - Devuelve respuesta con código HTTP `201 Created` y estructura:
     ```json
     {
       "agent_id": "00000000-0000-0000-0000-000000000000",
       "name": "worker-01",
       "status": "PENDING",
       "bootstrap_token": "<plaintext_token_delivered_once>",
       "expires_at": "2026-09-06T18:00:00.000000Z",
       "ttl_hours": 24,
       "created_at": "2026-09-05T18:00:00.000000Z"
     }
     ```

2. **Seguridad y Control de Acceso (RBAC):**
   - Protegido por el stack de middlewares `['jwt.auth', 'role:admin']`.
   - Peticiones no autenticadas devuelven `401 Unauthorized`.
   - Peticiones autenticadas con roles no administrativos (`OPERATOR`, `VIEWER`) devuelven `403 Forbidden`.

3. **Endpoints Complementarios de Gestión de Agentes:**
   - `GET /api/v1/admin/agents`: Lista todos los agentes registrados (código `200 OK`, exclusivo `ADMIN`).
   - `POST /api/v1/admin/agents/{agent_id}/regenerate-token`: Permite regenerar un bootstrap token para un agente en estado `PENDING` (código `200 OK`, exclusivo `ADMIN`, código `400 Bad Request` si ya está `ACTIVE`).

4. **Criterios de Aceptación:**
   - Endpoint devuelve payload con `agent_id` y `bootstrap_token` con código 201.
   - Solo accesible por administradores.
   - Suite de pruebas de integración y feature en Pest PHP con 100% de éxito.

---

## 3. Decisiones Técnicas y Arquitectónicas

- **Controlador API Dedicado ([app/Http/Controllers/Api/AdminAgentController.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Http/Controllers/Api/AdminAgentController.php)):** Separa limpiamente la lógica de enrutamiento HTTP y validación de entrada de la lógica de negocio y persistencia que reside en `AgentRegistrationService`.
- **Enrutamiento Estructurado ([routes/api.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/routes/api.php)):** Se agrupan los endpoints bajo el prefijo `v1/admin` y el middleware de rol `role:admin`, asegurando consistencia con el esquema REST de la API y proporcionando además alias directo bajo `/api/admin/agents`.
- **Zero-Trust Token Exposure:** El token en texto plano generado por el servicio se serializa en la respuesta JSON únicamente en el momento de creación/regeneración. Nunca se persiste en texto plano en base de datos.
