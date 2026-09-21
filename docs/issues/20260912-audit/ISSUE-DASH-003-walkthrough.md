# Walkthrough: [ISSUE-DASH-003] Transaccionalidad, locking y control de concurrencia en HITL (GLOBAL-003)

## 1. Resumen de la Implementación
Se implementó control de concurrencia y tolerancia a fallos en el servicio de resolución HITL (`HitlApprovalService`), utilizando bloqueo pesimista en base de datos (`lockForUpdate`), el estado intermedio `PROCESSING` para evitar carreras de doble aprobación y reconciliación automática de estado a `PENDING` ante fallos de red o errores del agente.

## 2. Archivos Modificados y Creados
- **[app/Models/ApprovalRequest.php](../../../app/Models/ApprovalRequest.php)**:
  - Incorporación del método `isProcessing(): bool`.
  - Actualización de `approve()` y `reject()` para permitir la transición terminal no solo desde `PENDING` sino también desde `PROCESSING`.
- **[app/Services/Hitl/HitlApprovalService.php](../../../app/Services/Hitl/HitlApprovalService.php)**:
  - Implementación de `DB::transaction` con `lockForUpdate()` al resolver (`approveApproval` y `rejectApproval`).
  - Validación estricta impidiendo concurrencia en estado `PROCESSING` (HTTP 409 Conflict) o sobre registros ya resueltos.
  - Reconciliación atómica a `PENDING` cuando la llamada HTTP al agente falla o devuelve código de error HTTP >= 400.
  - Confirmación atómica final a `APPROVED` / `REJECTED` y registro en `AuditEvent` únicamente tras éxito en la llamada de red.
- **[tests/Unit/Services/HitlApprovalServiceConcurrencyTest.php](../../../tests/Unit/Services/HitlApprovalServiceConcurrencyTest.php)**:
  - Suite exhaustiva de pruebas unitarias verificando estados de concurrencia, idempotencia, rechazo ante dobles envíos y reconciliación ante excepciones o timeouts de red.

## 3. Verificación
Se ejecutaron todas las comprobaciones de calidad mediante `scripts/check.sh` y la suite de pruebas de Pest:
```bash
./vendor/bin/pest
Tests:    227 passed (1075 assertions)
Duration: 4.19s
```
Todos los 227 tests pasaron exitosamente al 100%.
