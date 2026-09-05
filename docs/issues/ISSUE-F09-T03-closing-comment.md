Se ha completado la implementación de **[ISSUE-F09-T03] Vista UI: Consola de aprobación Human-In-The-Loop (HITL)** en `jg_dashboard`.

### Resumen de Implementación
1. **Consola Interactiva HITL en `/console`:**
   - Barra de herramientas con filtros reactivos por nivel de riesgo (`Todos`, `Crítico`, `Alto`), buscador contextual y selector de agente.
   - Tarjetas de aprobación con desglose secuencial de pasos (`timeline`), resaltado de comandos shell y herramientas críticas.
   - Panel de comandos de reversión y rollback automático ante contingencias.
   - Modal de inspección técnica (`#modal-inspect-plan`) con visualizador del payload JSON estructurado y copiado al portapapeles.
2. **Acciones Directas de Aprobación y Rechazo:**
   - Aprobación y rechazo en un solo clic con confirmación visual inmediata y toasts flotantes.
   - Endpoints web dedicados en `WebConsoleController` (`GET /console/approvals/pending`, `POST /console/approvals/{id}/approve`, `POST /console/approvals/{id}/reject`) que eliminan la necesidad de inyectar tokens Bearer manuales en navegador.
   - Validación estricta de motivo obligatorio (mínimo 5 caracteres) para rechazos.
3. **Auditoría e Integración de Agentes:**
   - Delegación hacia `jg_agente` mediante `AgentHttpClient` y registro de eventos inmutables en `dashboard_audit_events`.
4. **Verificación Automatizada:**
   - Suite de pruebas feature en `tests/Feature/HitlConsoleWebTest.php` validando la interfaz, el filtrado, los endpoints web y las reglas de validación.
   - 100% de las pruebas del proyecto superadas exitosamente en `./scripts/check.sh`.
5. **Documentación:**
   - Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.18.0) y cuarteto documental en `docs/issues/ISSUE-F09-T03-*`.

/close
