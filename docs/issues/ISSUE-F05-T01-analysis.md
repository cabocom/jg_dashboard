# Análisis Técnico: ISSUE-F05-T01 — Endpoint de recepción de heartbeats: POST /api/v1/agents/heartbeat

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F05-T01`  
> **Fase:** FASE 05 — Monitoreo de Agentes y Heartbeats  
> **Prioridad:** Critical  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F04-T03  

---

## 1. Objetivo del Issue

Implementar el endpoint público de recepción de señales de vida y telemetría `POST /api/v1/agents/heartbeat` en `jg_dashboard`. Este endpoint es invocado periódicamente (cada ~30 segundos) por cada instancia de `jg_agente` activa, utilizando su credencial permanente (`permanent_agent_secret`) y su `agent_id` para:
1. Autenticar criptográficamente la procedencia del agente activo comparando el hash SHA-256 del secreto contra `dashboard_agents.permanent_secret_hash`.
2. Actualizar el timestamp `last_heartbeat_at` al momento actual.
3. Consolidar el estado del agente en `ACTIVE` en `dashboard_agents`.
4. Registrar métricas operativas de telemetría opcionales (`cpu`, `mem_mb`, `active_executions`) en `metrics_json`.
5. Responder con código HTTP `200 OK` para confirmar la recepción exitosa.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Endpoint `POST /api/v1/agents/heartbeat`:**
   - **Autenticación:**
     - Identificador del agente: cabecera `X-Agent-ID` o campo en payload `agent_id`.
     - Credencial permanente: cabecera `Authorization: Bearer <secret>`, cabecera `X-Agent-Secret` o campo en payload `permanent_secret`.
     - Si falta alguno de los dos, responde con `401 Unauthorized` (`Identificador de agente o credencial permanente no provistos`).
     - Si la credencial no coincide con el hash almacenado, responde con `401 Unauthorized` (`Credencial de agente inválida.`).
     - Si el agente no existe o está en estado `PENDING` (no activado), responde con `401 Unauthorized`.
   - **Payload de Telemetría (Opcional):**
     - `status`: Estado del agente (`ACTIVE`, `IDLE`, `BUSY`, etc.).
     - `metrics`: Objeto JSON con indicadores de rendimiento (ej. CPU, memoria).
     - `active_executions`: Contador entero de tareas en ejecución en el runtime.
   - **Respuesta Exitosa (200 OK):**
     ```json
     {
       "status": "success",
       "message": "Heartbeat registrado exitosamente",
       "agent_id": "00000000-0000-0000-0000-000000000000",
       "name": "worker-01",
       "agent_status": "ACTIVE",
       "last_heartbeat_at": "2026-09-05T18:40:00.000000Z"
     }
     ```

2. **Criterios de Aceptación:**
   - Petición autenticada actualiza `last_heartbeat_at` y estado del agente en `dashboard_agents` devolviendo 200 OK.
   - 100% de pruebas pasando en Pest PHP.

---

## 3. Decisiones Técnicas y de Seguridad

- **Verificación Criptográfica por Hash SHA-256:** Para tolerar la alta frecuencia de heartbeats (múltiples agentes cada 30 segundos) sin saturar la CPU con costosos algoritmos de hash de contraseñas de usuarios (Bcrypt/Argon2), las credenciales de agentes emplean cadenas criptográficas de alta entropía (48 caracteres) verificadas mediante `hash_equals(permanent_secret_hash, hash('sha256', $plainSecret))`.
- **Flexibilidad de Transporte:** Soporta tanto headers estándar HTTP (`X-Agent-ID`, `Authorization: Bearer`) como payload JSON plano, asegurando compatibilidad con diversos clientes HTTP y scripts de monitoreo.
- **Transición Determinista:** Registrar un heartbeat exitoso reafirma al agente en estado `ACTIVE`, preparando la base para que el futuro monitor de salud (F05-T02) pueda detectar desconexiones si cesan los reportes (>90s).
