Se ha completado la implementación de **[ISSUE-F07-T02] Endpoint de listado de aprobaciones pendientes: GET /api/v1/approvals/pending**.

### Resumen de Implementación
1. **Controlador HITL:** Creación de `HitlApprovalController` en `app/Http/Controllers/Api/HitlApprovalController.php` con el método `pending()`.
2. **Exposición de Rutas y RBAC:** Definición de `GET /api/v1/approvals/pending` y el alias `GET /api/approvals/pending` bajo middleware `jwt.auth` y `role:operator` (permitiendo acceso a operadores y administradores, y denegando a visores).
3. **Capa de Servicio y Formateo Canónico:** Ampliación de `HitlApprovalService` con filtrado por nivel de riesgo, extracción de resumen de acciones (`extractActionSummary`) y formateo estandarizado con cálculo dinámico de tiempo transcurrido (`elapsed_time` y `elapsed_seconds`).
4. **Metadatos y Agregaciones:** Entrega de datos paginados (`meta`), catálogo de herramientas (`tools_involved`), plan propuesto, comandos de compensación y métricas acumuladas (`summary`).
5. **Verificación Automatizada:** Suite de pruebas feature en `tests/Feature/HitlApprovalsPendingApiTest.php` validando autenticación, autorización RBAC, filtros y cálculo temporal, con 100% de la suite en verde.
6. **Documentación:** Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.13.0) y documentación técnica en `docs/issues/ISSUE-F07-T02-*`.

/close
