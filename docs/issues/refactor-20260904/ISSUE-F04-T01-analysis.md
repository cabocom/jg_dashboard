# Análisis Técnico: ISSUE-F04-T01 — Servicio de registro de agentes y generación de bootstrap tokens

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F04-T01`  
> **Fase:** FASE 04 — Sistema de Registro de Agentes  
> **Prioridad:** Critical  
> **Complejidad Estimada:** M  
> **Dependencias:** ISSUE-F02-T03; ISSUE-F03-T02  

---

## 1. Objetivo del Issue

Implementar la capa de servicio central (`AgentRegistrationService`) para que un administrador del Control Plane (`jg_dashboard`) pueda registrar un nuevo agente autónomo (`jg_agente`) y generar un `bootstrap_token` criptográfico seguro con caducidad estricta (TTL de 24 horas).
Garantizar:
1. Creación atómica del agente en estado `PENDING` con UUID v4 único.
2. Generación de un token criptográfico seguro devuelto en texto plano una sola vez al administrador.
3. Persistencia estricta Zero-Trust en base de datos: almacenar únicamente el hash SHA-256 (`token_hash`) y nunca el token en plano.
4. Trazabilidad completa registrando eventos de auditoría (`AGENT_CREATED`, `AGENT_BOOTSTRAP_TOKEN_REGENERATED`) en `dashboard_audit_events`.
5. Soporte para regeneración controlada de tokens en caso de caducidad antes del aprovisionamiento.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Servicio de Registro (`App\Services\Agents\AgentRegistrationService`):**
   - Método `createAgent(string $name, ?string $version = null, ?array $capabilities = null, ?array $metadata = null, int $ttlHours = 24, ?User $adminUser = null): array`:
     - Valida el nombre del agente (no vacío).
     - Crea la entidad `Agent` en estado `PENDING`.
     - Genera un token aleatorio seguro (48 caracteres) con TTL por defecto de 24 horas.
     - Persiste únicamente el hash SHA-256 en `dashboard_bootstrap_tokens`.
     - Registra el evento de auditoría en `dashboard_audit_events`.
     - Retorna array con: `agent`, `agent_id`, `name`, `status`, `bootstrap_token` (en plano), `expires_at` y `ttl_hours`.
   - Método `regenerateBootstrapToken(Agent|string $agent, int $ttlHours = 24, ?User $adminUser = null): array`:
     - Exige que el agente esté en estado `PENDING` (rechaza agentes ya `ACTIVE`).
     - Invalida tokens previos no consumidos marcándolos como expirados.
     - Emite un nuevo token seguro y registra el evento de auditoría.
   - Método `validateBootstrapToken(string $plainToken): array`:
     - Calcula el hash SHA-256 y verifica existencia, caducidad y estado de consumo (`valid`, `reason`, `token`, `agent`).

2. **Criterios de Aceptación:**
   - Creación de agente genera UUID y token seguro de 24h.
   - El token se guarda hasheado en BD y se retorna al admin una sola vez.
   - Batería de pruebas unitarias en Pest PHP con 100% de éxito.

---

## 3. Decisiones Técnicas y de Seguridad

- **Principio Zero-Trust:** El token en texto plano solo existe transitoriamente en memoria durante la respuesta al administrador. La base de datos solo almacena el hash criptográfico SHA-256 con índice único.
- **Transaccionalidad (`DB::transaction`):** La creación del agente, la persistencia del token de bootstrap y el registro del evento de auditoría ocurren de forma atómica; si cualquier paso falla, se realiza un rollback automático.
- **Inmutabilidad y Prevención de Abuso:** Los agentes que ya han completado su registro y alcanzado el estado `ACTIVE` no pueden volver a generar tokens de bootstrap.

---

## 4. Plan de Verificación

- Suite Pest PHP `Tests\Unit\AgentRegistrationServiceTest`:
  - `createAgent generates uuid, pending status, and secure 24h bootstrap token`
  - `createAgent throws exception if name is empty`
  - `validateBootstrapToken correctly classifies valid, missing, consumed and expired tokens`
  - `regenerateBootstrapToken expires previous token and issues a new one`
  - `regenerateBootstrapToken rejects active agents`
- Ejecución completa mediante `./scripts/check.sh`.
