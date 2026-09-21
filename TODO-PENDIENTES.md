# TAREAS PENDIENTES Y DEUDA TÉCNICA: jg_dashboard

**Proyecto:** `jg_dashboard` (Control Plane)  
**Versión actual:** 3.20.0  
**Fecha de consolidación:** 6 de septiembre de 2026  
**Auditoría de referencia:** Auditoría Técnica y Diagnóstico de Arquitectura Independiente (Codex)

---

## 1. Resumen Ejecutivo de Pendientes

El plano de control `jg_dashboard` ha completado con éxito sus fases troncales (Fases 01 a 07 y Fase 09), proveyendo autenticación JWT/RBAC, administración de agentes mediante `bootstrap_tokens` (TTL 24h), recepción de heartbeats, proxy HTTP con firmado HMAC (`AgentHttpClient`), gestión y resolución de solicitudes HITL (`/approvals`), consola web unificada en `/console` (panel de agentes, aprobaciones y visor de ejecuciones en tiempo real) y persistencia aislada en PostgreSQL (`jg_dashboard_postgres`:5434).

No obstante, la auditoría exhaustiva del código fuente revela seis ítems pendientes de desarrollo o deuda técnica arquitectónica:

| ID | Título | Criticidad | Estado | Componente Afectado |
| :--- | :--- | :--- | :--- | :--- |
| **ITEM-DB-01** | Visualización de herramientas en vez de etapas cognitivas del grafo en `console.js` | MEDIA | `PARCIALMENTE IMPLEMENTADO` | `public/js/console.js`, `resources/views/console/` |
| **ITEM-DB-02** | Adaptadores para estímulos externos (Webhooks / Canales) (Fase 08) | MEDIA | `NO IMPLEMENTADO` | `app/Adapters/`, `routes/api.php` |
| **ITEM-DB-03** | Tests integrales end-to-end multi-agente en CI/CD (Fase 10) | MEDIA | `NO IMPLEMENTADO` | `tests/Feature/`, `.gitlab-ci.yml` |
| **ITEM-DB-04** | Dualidad de clientes API y residuos de `Devops60k` | MEDIA | `DEUDA TÉCNICA` | `app/Services/DevOps60kApiClient.php`, Filament |
| **ITEM-DB-05** | Ejecución de tests (`pest`) dentro del contenedor Docker | BAJA | `PENDIENTE` | `Dockerfile`, `scripts/check.sh` |
| **ITEM-DB-06** | Streaming en tiempo real vía Server-Sent Events (SSE) o WebSockets | BAJA | `NO IMPLEMENTADO` (Post-MVP) | `app/Http/Controllers/`, `console.js` |

---

## 2. Detalle de Ítems Pendientes

### ITEM-DB-01: Visualización de herramientas en vez de etapas cognitivas del grafo en `console.js`
- **Severidad:** MEDIA
- **Estado:** `PARCIALMENTE IMPLEMENTADO`
- **Comportamiento Esperado:**
  El visor de ejecuciones en `/console` debe representar en la línea de tiempo tanto las etapas cognitivas orquestadas por LangGraph (`observe`, `reason`, `plan`, `risk_assess`, `human_approval`, `execute`, `verify`, `rollback`, `conclude`) como las herramientas ejecutadas dentro del nodo `execute`.
- **Comportamiento Actual:**
  El script frontend `public/js/console.js` procesa únicamente el arreglo `execution_records` retornado por la API del agente. Si un agente ejecuta una sola herramienta (ej. `DockerRestartTool`), la consola muestra únicamente "Paso 1: docker_restart", ocultando al operador los nodos previos (`observe`, `plan`, `risk_assess`) y posteriores (`verify`) que se ejecutaron en el motor cognitivo.
- **Acciones Necesarias para Resolución:**
  1. Adaptar el serializador en `jg_agente` o enriquecer la respuesta de `GET /executions/{thread_id}` para incluir el array de transiciones de nodos del grafo (`state.node_history` o similar).
  2. Actualizar `console.js` y la vista Blade para renderizar las etapas del grafo con sus respectivos iconos de estado (observación, razonamiento, planificación, verificación).
- **Archivos de Referencia:**
  - [public/js/console.js](public/js/console.js)
  - [resources/views/console/index.blade.php](resources/views/console/index.blade.php)

---

### ITEM-DB-02: Adaptadores para estímulos externos (Webhooks / Canales) (Fase 08)
- **Severidad:** MEDIA
- **Estado:** `NO IMPLEMENTADO`
- **Comportamiento Esperado:**
  Para dar cumplimiento al principio de ausencia absoluta de interfaces externas en el agente, el Dashboard debe exponer endpoints adaptadores (ej. Webhooks entrantes de monitorización, pasarelas de mensajería o telefonía simulada) que transformen payloads heterogéneos en el contrato normalizado `AgentInput` y canalicen la petición hacia el agente adecuado.
- **Comportamiento Actual:**
  La Fase 08 del roadmap original quedó pendiente. Actualmente, los únicos mecanismos para disparar ejecuciones son la interfaz web interactiva (`/console`), las acciones Filament o la API REST interna `POST /api/v1/executions/trigger`.
- **Acciones Necesarias para Resolución:**
  1. Crear módulo `App\Adapters\` con drivers para Webhooks genéricos (JSON mapping hacia `AgentInput`).
  2. Implementar controlador `WebhookAdapterController` y enrutamiento por tipo de evento.
- **Archivos de Referencia:**
  - [app/Http/Controllers/Api/ExecutionController.php](app/Http/Controllers/Api/ExecutionController.php)
  - [routes/api.php](routes/api.php)

---

### ITEM-DB-03: Tests integrales end-to-end multi-agente en CI/CD (Fase 10)
- **Severidad:** MEDIA
- **Estado:** `NO IMPLEMENTADO`
- **Comportamiento Esperado:**
  Disponer de una suite automatizada de pruebas end-to-end en CI/CD que levante simultáneamente la topología completa (Dashboard, Agente Conversacional, Agente DevOps y bases de datos PostgreSQL) y valide:
  - Caso A: Conversación pura con respuesta normalizada `AgentOutput`.
  - Caso B: Ejecución DevOps con pausa HITL, notificación al Dashboard, aprobación manual remota y conclusión con rollback si procede.
- **Comportamiento Actual:**
  Cada repositorio (`jg_agente` con 312 tests y `jg_dashboard` con 35 tests) prueba sus componentes de manera aislada utilizando mocks HTTP. No existe un pipeline común orquestado que valide la integración en vivo en un entorno efímero de CI.
- **Acciones Necesarias para Resolución:**
  1. Configurar un job en `.gitlab-ci.yml` con servicios Docker-in-Docker o compose para ejecutar pruebas de integración end-to-end entre ambos contenedores.
- **Archivos de Referencia:**
  - [.gitlab-ci.yml](.gitlab-ci.yml)
  - [tests/Feature/](tests/Feature/)

---

### ITEM-DB-04: Dualidad de clientes API y residuos de `Devops60k`
- **Severidad:** MEDIA (Deuda Técnica)
- **Estado:** `DEUDA TÉCNICA`
- **Comportamiento Esperado:**
  Todos los recursos y servicios del Dashboard deben utilizar exclusivamente la arquitectura de agentes `jg_agente` vía `App\Services\Agents\AgentHttpClient`. Las clases y referencias al sistema monolítico anterior (`Devops60k`) deben ser completamente eliminadas o migradas.
- **Comportamiento Actual:**
  Coexisten dos clientes API en el proyecto:
  - `App\Services\Agents\AgentHttpClient`: El cliente oficial del nuevo ecosistema `jg_agente` con firmado HMAC-SHA256 y soporte multi-agente.
  - `App\Services\DevOps60kApiClient`: Cliente heredado de la versión anterior, todavía registrado como singleton en `AppServiceProvider` y referenciado por componentes como `TopologyMap`, `AgentHealthWidget` y `AgentTaskResource`.
- **Acciones Necesarias para Resolución:**
  1. Refactorizar `TopologyMap`, `AgentHealthWidget` y `AgentTaskResource` para consumir `AgentHttpClient` o modelos Eloquent de `Agent`.
  2. Eliminar la clase `DevOps60kApiClient.php`, su configuración en `config/devops60k.php` y el binding en `AppServiceProvider.php`.
- **Archivos de Referencia:**
  - [app/Services/DevOps60kApiClient.php](app/Services/DevOps60kApiClient.php)
  - [app/Services/Agents/AgentHttpClient.php](app/Services/Agents/AgentHttpClient.php)
  - [app/Filament/Pages/TopologyMap.php](app/Filament/Pages/TopologyMap.php)
  - [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)

---

### ITEM-DB-05: Ejecución de tests (`pest`) dentro del contenedor Docker
- **Severidad:** BAJA
- **Estado:** `PENDIENTE`
- **Comportamiento Esperado:**
  Poder ejecutar la suite de pruebas Pest/PHPUnit dentro del contenedor en ejecución mediante `docker exec jg_dashboard ./scripts/check.sh` o `docker exec jg_dashboard vendor/bin/pest`.
- **Comportamiento Actual:**
  El Dockerfile de `jg_dashboard` compila la imagen para producción ejecutando:
  ```dockerfile
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
  ```
  Esto excluye `pestphp/pest` y `phpunit/phpunit` del directorio `vendor/` del contenedor, por lo que ejecutar los tests requiere instalar dependencias en el host local o contar con un target de desarrollo en el Dockerfile.
- **Acciones Necesarias para Resolución:**
  1. Añadir target `dev` en el Dockerfile multi-stage de `jg_dashboard` que instale dependencias de desarrollo (`composer install` completo).
- **Archivos de Referencia:**
  - [Dockerfile](Dockerfile)
  - [scripts/check.sh](scripts/check.sh)

---

### ITEM-DB-06: Streaming en tiempo real vía Server-Sent Events (SSE) o WebSockets
- **Severidad:** BAJA
- **Estado:** `NO IMPLEMENTADO` (Post-MVP)
- **Comportamiento Esperado:**
  Actualización reactiva push en tiempo real de la consola web sin sobrecargar la red con sondeos HTTP constantes.
- **Comportamiento Actual:**
  La vista de consola (`public/js/console.js`) implementa un polling recursivo cada 3000 ms (`setInterval` hacia `GET /api/v1/executions/{agent_id}/{thread_id}`). Si bien es funcional y robusto para el MVP, genera tráfico recurrente mientras el usuario mantiene la consola abierta.
- **Acciones Necesarias para Resolución:**
  1. Implementar streaming SSE o Laravel Reverb / WebSockets en futuras versiones cuando `jg_agente` soporte streaming de tokens y eventos de nodos.
- **Archivos de Referencia:**
  - [public/js/console.js](public/js/console.js)
