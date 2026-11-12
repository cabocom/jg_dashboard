# [REFACTOR-v2.0] Refactorización Estructural SOLID de `HitlApprovalService` y Controladores de Ejecución (`jg_dashboard`)

- **Estado:** Abierto / Planificado
- **Fecha:** 2026-11-09
- **Proyecto:** `jg_dashboard`
- **GitLab Issue:** [jg_dashboard#58](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/58)
- **Asignado a:** Javier Gálvez (`@cabocom`)
- **Documento Maestro:** [`docs/refactor.md`](../../../docs/refactor.md)

---

## 1. Contexto y Objetivo (Arquitectura v2.0 SOLID)

Este issue planifica la refactorización estructural v2.0 de `jg_dashboard` conforme a las directrices de programación estructurada y principios **SOLID** de `AGENTS.md` (Sección 8) y `docs/refactor.md` (máximo **300 LOC por clase** y **40 LOC por método**).

## 2. Diagnóstico Real de Ficheros Monolíticos (`>300 LOC`)

De los 95 ficheros de `jg_dashboard` (12.652 LOC), 82 ya están por debajo de 300 LOC. Los **13 ficheros que superan el umbral** se concentran principalmente en:
1. `app/Services/Hitl/HitlApprovalService.php` (**949 LOC**, 10 métodos extensos)
2. `resources/views/console/index.blade.php` (**864 LOC**)
3. `app/Http/Controllers/WebConsoleController.php` (**599 LOC**, 12 métodos)
4. `app/Http/Controllers/Api/ExecutionController.php` (**508 LOC**, 3 métodos gigantes)
5. `resources/views/filament/pages/topology-map.blade.php` (**455 LOC**)
6. `app/Services/Agents/AgentHttpClient.php` (**434 LOC**) y `app/Services/Auth/JwtService.php` (**433 LOC**)

## 3. Plan de Refactorización Estructural (Cero Roturas)

- **Fase 1 (Descomposición de `HitlApprovalService.php`) [COMPLETADA]:**
  - `HitlApprovalService.php` reducido de **949 LOC a 160 LOC** como fachada delgada y retrocompatible.
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
  - 100% de tests Pest (336 tests, 1.560 assertions) y pruebas E2E Selenium (Firefox) pasando al 100%.
- **Fase 3 (Vistas y Clientes):**
  - Modularizar parciales de `console/index.blade.php` y separar responsabilidades en `AgentHttpClient.php` y `JwtService.php`.

## 4. Estimación con Gemini Flash y Criterios de Aceptación

- **Esfuerzo estimado:** 3 sesiones (~1,5 M – 2,0 M tokens Gemini Flash, ~1 – 1,5 horas).
- **Criterios de cierre:**
  - [ ] Controladores y servicios principales por debajo de 300 LOC.
  - [ ] 100% de tests en verde (`php artisan test`) y `./vendor/bin/pint --test` limpio.
