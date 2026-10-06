# Walkthrough: ISSUE-F08-T02 (Adaptador HTTP / Webhook para aplicaciones cliente)

- **Issue ID:** `ISSUE-F08-T02`
- **GitLab Issue:** `jg_dashboard#28`
- **Fecha:** 6 de octubre de 2026

---

## 1. Cambios Realizados

### 1.1 Configuración (`config/adapters.php`)
Se definió el archivo de configuración para adaptadores externos con soporte para:
- `webhook_secret`: Secreto opcional o requerido para autenticación de llamadas externas (`ADAPTER_WEBHOOK_SECRET`).
- `require_auth`: Booleano para forzar verificación de secreto.
- `default_agent_id`: Agente predeterminado para ejecuciones no explícitas.
- `default_format`: Formato de respuesta por defecto (`generic`).

### 1.2 Capa de Adaptadores (`app/Adapters/`)
- **`InputNormalizerInterface` & `InputNormalizer`:**
  - Ingiere peticiones `Request` o arreglos asociativos.
  - Detecta tipo de fuente (`gitlab`, `github`, `slack`, `telegram`, `chatbot`, `ci_cd`, `generic`).
  - Sintetiza texto de prompt claro a partir de eventos CI/CD y mensajes conversacionales.
  - Genera o preserva de forma consistente `execution_id`, `thread_id` y `correlation_id`.
  - Construye el contrato canónico `AgentInput` cumpliendo con la especificación de `jg_agente`.
- **`OutputFormatterInterface` & `ClientOutputFormatter`:**
  - `generic`: Formato JSON estructurado para APIs generales.
  - `chatbot`: Formato con `response`, `text`, `thread_id` y estado conversacional.
  - `ci_cd`: Formato con `status`, `job_status`, `summary` y `pipeline_status`.
- **`HttpWebhookAdapter`:**
  - Valida autenticación por cabecera `X-Webhook-Secret`, Bearer token o query parameter.
  - Resuelve dinámicamente el agente objetivo (por parámetro de ruta, payload o primer agente activo registrado).
  - Gestiona fallos de conectividad o indisponibilidad upstream retornando 400, 404, 502 o 504.
  - Registra eventos de auditoría inmutables (`adapter.webhook.received`, `adapter.webhook.completed`).
  - Persiste tareas en `dashboard_agent_tasks`.

### 1.3 Controlador y Enrutamiento
- **`WebhookAdapterController` (`app/Http/Controllers/Api/Adapters/WebhookAdapterController.php`):**
  - Manejador HTTP con control de excepciones y retorno de respuestas JSON.
- **`routes/api.php`:**
  - `POST /api/v1/adapters/webhook`
  - `POST /api/v1/adapters/webhook/{agent_id}`
- **`AppServiceProvider`:**
  - Registro de los contratos `InputNormalizerInterface`, `OutputFormatterInterface` y del servicio `HttpWebhookAdapter`.

---

## 2. Validación y Resultados de Pruebas

Se ejecutó la suite de pruebas Pest y PHPUnit cubriendo pruebas unitarias del normalizador, del formateador de salida y pruebas funcionales de integración HTTP:

```bash
vendor/bin/pest tests/Unit/InputNormalizerTest.php tests/Unit/ClientOutputFormatterTest.php tests/Feature/WebhookAdapterApiTest.php
```

### Resultados de la suite específica de adaptadores:
```text
PASS  Tests\Unit\InputNormalizerTest
  ✓ normalizes generic payload with explicit prompt into valid AgentInput
  ✓ synthesizes prompt from GitLab CI/CD push webhook payload
  ✓ synthesizes prompt from GitHub webhook PR payload
  ✓ extracts text and thread_id from chatbot / conversational payload
  ✓ maps async flag into parameters object
  ✓ extracts correlation_id from headers if present

PASS  Tests\Unit\ClientOutputFormatterTest
  ✓ formats generic response correctly
  ✓ formats chatbot response with text and conversation details
  ✓ formats CI/CD response with job status and details

PASS  Tests\Feature\WebhookAdapterApiTest
  ✓ generic external webhook request is normalized to AgentInput, executed and returned in client format
  ✓ webhook targeted to specific agent_id in URL routes directly to that agent
  ✓ GitLab CI/CD push webhook is synthesized and formatted as CI/CD response
  ✓ Chatbot stimulus is normalized and formatted as chatbot conversational response
  ✓ asynchronous webhook request returns 202 Accepted with PENDING status
  ✓ webhook authentication rejects unauthorized requests when webhook_secret is configured
  ✓ returns 404 when target agent does not exist
  ✓ returns 400 when target agent is OFFLINE
  ✓ returns 502 when upstream agent fails with connection error
  ✓ propagates and echoes X-Correlation-ID header

Tests: 19 passed (76 assertions)
```

### Resultados de la suite global de `jg_dashboard`:
```text
Tests: 303 passed (1416 assertions)
Duration: 5.24s
```

Verificado con `./scripts/check.sh --unit -p jg_dashboard` pasando al 100%.
