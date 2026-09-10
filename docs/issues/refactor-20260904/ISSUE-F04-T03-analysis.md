# Análisis Técnico: ISSUE-F04-T03 — Endpoint de consumo de registro por el agente: POST /api/v1/agents/register

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F04-T03`  
> **Fase:** FASE 04 — Sistema de Registro de Agentes  
> **Prioridad:** Critical  
> **Complejidad Estimada:** M  
> **Dependencias:** ISSUE-F04-T01  

---

## 1. Objetivo del Issue

Implementar el endpoint público de consumo de aprovisionamiento `POST /api/v1/agents/register` en `jg_dashboard`. Este endpoint es invocado por las instancias de `jg_agente` durante su arranque inicial (`boot`), presentando el `bootstrap_token` recibido por variables de entorno para:
1. Validar la autenticidad, vigencia y no-consumo del bootstrap token.
2. Marcar atómicamente el token como consumido (`consumed = true`), impidiendo de forma irreversible cualquier reutilización.
3. Emitir y retornar la credencial permanente del agente (`permanent_secret`) en texto plano por única vez.
4. Persistir exclusivamente el hash SHA-256 de la credencial permanente en `dashboard_agents.permanent_secret_hash`.
5. Transicionar el estado del agente de `PENDING` a `ACTIVE` y registrar el evento de auditoría `AGENT_ACTIVATED`.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Endpoint `POST /api/v1/agents/register`:**
   - **Autenticación:**
     - El agente presenta el token a través del header `Authorization: Bearer <bootstrap_token>` o en el payload JSON `{ "bootstrap_token": "<token>" }`.
     - Si no se proporciona token, responde con código HTTP `401 Unauthorized` (`Bootstrap token no provisto`).
   - **Payload Opcional:**
     - `version`: Versión del software del agente (ej. `1.0.0`).
     - `capabilities`: Array de capacidades y herramientas soportadas.
     - `metadata`: Objeto/Array con metadatos contextuales del entorno del agente.
   - **Manejo de Errores Semánticos:**
     - Token inexistente o alterado: `404 Not Found`.
     - Token ya consumido previamente: `409 Conflict`.
     - Token expirado (> 24h): `410 Gone`.
     - Agente en estado no pendiente (ya `ACTIVE`): `400 Bad Request`.
   - **Respuesta Exitosa (200 OK):**
     ```json
     {
       "status": "success",
       "message": "Agente aprovisionado y activado exitosamente",
       "agent_id": "00000000-0000-0000-0000-000000000000",
       "name": "worker-01",
       "status": "ACTIVE",
       "permanent_secret": "sec_...",
       "registered_at": "2026-09-05T18:30:00.000000Z"
     }
     ```

2. **Criterios de Aceptación:**
   - Endpoint valida token, lo marca como consumido (un solo uso), emite credencial permanente y activa el estado del agente.
   - 100% de pruebas pasando en Pest PHP.

---

## 3. Decisiones Técnicas y de Seguridad

- **Punto de Entrada Autónomo:** Este endpoint no requiere autenticación de sesión de usuario (`jwt.auth`), pues quien se conecta es el runtime del agente en su primer arranque, autenticándose mediante la prueba de posesión del token de un solo uso.
- **Doble Método de Extracción:** Admite tanto el estándar HTTP Bearer en encabezado `Authorization` como el campo explícito `bootstrap_token` en el cuerpo JSON para flexibilidad con diversos clientes HTTP.
- **Transaccionalidad Atómica:** El consumo del token, la transición de estado del agente a `ACTIVE`, el hasheo del secreto permanente y el registro del evento de auditoría se ejecutan en una única transacción de base de datos (`DB::transaction`).
- **Zero-Trust Permanent Secret:** El secreto devuelto al agente (`sec_*`) se genera con 48 caracteres aleatorios criptográficos; el Control Plane guarda únicamente su hash SHA-256.

---

## 4. Plan de Verificación

- Pruebas Unitarias en `Tests\Unit\AgentRegistrationServiceTest`:
  - `consumeBootstrapTokenAndActivate activates agent, marks token consumed, and issues permanent secret`
  - `consumeBootstrapTokenAndActivate rejects invalid or not found token`
  - `consumeBootstrapTokenAndActivate rejects already consumed token`
  - `consumeBootstrapTokenAndActivate rejects expired token`
- Pruebas de Feature en `Tests\Feature\AgentRegistrationApiTest`:
  - Validación de 401 ante token ausente.
  - Activación exitosa vía Authorization Bearer.
  - Activación exitosa vía JSON body.
  - Rechazo con 409 Conflict ante reutilización de token.
  - Rechazo con 410 Gone ante token expirado.
  - Rechazo con 404 Not Found ante token inválido.
  - Verificación del alias `/api/agents/register`.
