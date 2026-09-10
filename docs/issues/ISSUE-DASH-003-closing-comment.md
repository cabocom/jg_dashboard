# Comentario de Cierre: [ISSUE-DASH-003] Transaccionalidad, locking y control de concurrencia en HITL (GLOBAL-003)

## Estado: RESUELTO / IMPLEMENTADO

### Resumen de la Solución
Se ha implementado el control de concurrencia y tolerancia a fallos en la resolución HITL de `jg_dashboard`:
1. **Bloqueo Pesimista:** Se aplica `lockForUpdate` dentro de `DB::transaction` en `approveApproval()` y `rejectApproval()` de `HitlApprovalService`.
2. **Estado `PROCESSING`:** La solicitud transiciona inmediatamente a `PROCESSING` antes de iniciar la llamada HTTP hacia el agente, bloqueando cualquier doble aprobación o conflicto concurrente (HTTP 409 Conflict).
3. **Reconciliación Automática:** En caso de fallos de red, timeouts (HTTP 504) o excepciones del agente (HTTP 500/503), el estado se revierte de forma automática a `PENDING`.
4. **Verificación Automatizada:** Se agregaron 6 pruebas de concurrencia y resiliencia en `tests/Unit/Services/HitlApprovalServiceConcurrencyTest.php`. Toda la suite de Pest de `jg_dashboard` (227 tests) y `scripts/check.sh` pasaron al 100%.
