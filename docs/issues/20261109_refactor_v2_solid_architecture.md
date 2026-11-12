# [REFACTOR-v2.0] Refactorización Estructural SOLID de `HitlApprovalService` y Controladores de Ejecución (`jg_dashboard`)

- **Estado:** Completado (Fases 1, 2 y 3 al 100%)
- **Fecha:** 2026-11-09
- **Fecha de Cierre:** 2026-11-12
- **Proyecto:** `jg_dashboard`
- **GitLab Issue:** [jg_dashboard#58](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/58)
- **Asignado a:** Javier Gálvez (`@cabocom`)
- **Documento Maestro:** [`docs/refactor.md`](../../../docs/refactor.md)

---

## 1. Contexto y Objetivo (Arquitectura v2.0 SOLID)

Este issue planifica y ejecuta la refactorización estructural v2.0 de `jg_dashboard` conforme a las directrices de programación estructurada y principios **SOLID** de `AGENTS.md` (Sección 8) y `docs/refactor.md` (máximo **300 LOC por clase** y **40 LOC por método**).

## 2. Diagnóstico Real de Ficheros Monolíticos (`>300 LOC`) y Estado Final

Todos los 7 ficheros monolíticos detectados han sido descompuestos y reducidos por debajo del umbral de 300 LOC:
1. `app/Services/Hitl/HitlApprovalService.php`: de 949 LOC a **181 LOC** (-81%).
2. `resources/views/console/index.blade.php`: de 864 LOC a **57 LOC** (-93%).
3. `app/Http/Controllers/WebConsoleController.php`: de 599 LOC a **289 LOC** (-52%).
4. `app/Http/Controllers/Api/ExecutionController.php`: de 508 LOC a **51 LOC** (-90%).
5. `resources/views/filament/pages/topology-map.blade.php`: de 455 LOC a **63 LOC** (-86%).
6. `app/Services/Agents/AgentHttpClient.php`: de 434 LOC a **265 LOC** (-39%).
7. `app/Services/Auth/JwtService.php`: de 433 LOC a **281 LOC** (-35%).

## 3. Plan de Refactorización Estructural Ejecutado (Cero Roturas)

- **Fase 1 (Descomposición de `HitlApprovalService.php`) [COMPLETADA]:**
  - `HitlApprovalService.php` reducido de **949 LOC a 181 LOC** como fachada delgada y retrocompatible.
  - Casos de uso y servicios enfocados extraídos:
    * `app/Actions/Hitl/RegisterHitlApprovalAction.php` (125 LOC)
    * `app/Actions/Hitl/ApproveHitlRequestAction.php` (225 LOC)
    * `app/Actions/Hitl/RejectHitlRequestAction.php` (220 LOC)
    * `app/Actions/Hitl/ReconcileHitlApprovalsAction.php` (65 LOC)
    * `app/Services/Hitl/HitlApprovalFormatter.php` (175 LOC)
    * `app/Services/Hitl/HitlTaskSynchronizer.php` (115 LOC)
  - 100% de tests pasando en verde (336 tests Pest) sin rotura de contratos.

- **Fase 2 (Adelgazamiento de Controladores HTTP) [COMPLETADA]:**
  - `ExecutionController.php` reducido de **508 LOC a 51 LOC** delegando en `TriggerAgentExecutionAction.php` (264 LOC), `FetchExecutionTraceAction.php` (269 LOC) y `ExecutionTaskSynchronizer.php` (106 LOC) usando los DTOs inmutables `TriggerExecutionInput` y `TriggerExecutionResult`.
  - `WebConsoleController.php` reducido de **599 LOC a 289 LOC** delegando en casos de uso atómicos:
    * `app/Actions/Console/ListConsoleAgentsAction.php` (64 LOC)
    * `app/Actions/Console/GetConsolePendingApprovalsAction.php` (124 LOC)
    * `app/Actions/Console/ListConsoleExecutionsAction.php` (218 LOC)
    * `app/Actions/Console/UpdateConsoleProfileAction.php` (70 LOC)
  - 100% de tests Pest (336 tests, 1.560 assertions) pasando sin regresiones.

- **Fase 3 (Vistas y Clientes HTTP/JWT) [COMPLETADA]:**
  - `AgentHttpClient.php` reducido de **434 LOC a 265 LOC** mediante:
    * `app/Services/Agents/AgentHmacSigner.php` (97 LOC): Generación determinista de firmas canónicas HMAC-SHA256 y cabeceras de seguridad.
    * `app/Services/Agents/AgentHttpRequestDispatcher.php` (125 LOC): Despacho resiliente de peticiones con reintentos configurables.
    * `app/Services/Agents/AgentHttpResponseHandler.php` (172 LOC): Mapeo robusto de respuestas exitosas y excepciones de red/HTTP.
  - `JwtService.php` reducido de **433 LOC a 281 LOC** mediante:
    * `app/Services/Auth/JwtKeyManager.php` (136 LOC): Gestión, rotación y normalización de claves criptográficas y soporte de identificador `kid`.
    * `app/Services/Auth/JwtBlacklistService.php` (135 LOC): Blacklist y revocación inmediata de tokens `jti` en caché L1 y base de datos PostgreSQL L2.
  - Modularización de plantillas Blade monolíticas:
    * `resources/views/console/index.blade.php` (864 -> 57 LOC): Descompuesto en 7 partials de vistas y 6 partials de modales en `resources/views/console/partials/`.
    * `resources/views/filament/pages/topology-map.blade.php` (455 -> 63 LOC): Descompuesto en partials para panel lateral, métricas y scripts Alpine/Vis.js en `resources/views/filament/pages/partials/`.

## 4. Criterios de Aceptación y Verificación

- [x] 100% de clases y módulos de la refactorización por debajo de 300 LOC.
- [x] 100% de métodos por debajo de 40 LOC.
- [x] Inmutabilidad garantizada mediante DTOs tipados (`readonly class`).
- [x] Compatibilidad hacia atrás al 100% (cero roturas en APIs externas, endpoints o interfaces).
- [x] 336/336 tests unitarios y de integración Pest pasando en verde (1.560 assertions).
- [x] Purgado automático de cachés (`view:clear`, `cache:clear`, `config:clear`, `route:clear`) y reinicio de contenedor Docker cumplidos.
