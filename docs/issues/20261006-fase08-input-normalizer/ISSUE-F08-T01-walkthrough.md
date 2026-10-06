# Walkthrough: ISSUE-F08-T01 (Input Normalizer)

- **Issue ID:** `ISSUE-F08-T01`
- **GitLab Issue:** `jg_dashboard#27`
- **Fecha:** 6 de octubre de 2026

---

## 1. Implementación Técnica

### 1.1 Contrato del Normalizador (`app/Adapters/Contracts/InputNormalizerInterface.php`)
Define el método canónico de transformación:
```php
public function normalize(Request|array $stimulus, array $context = []): array;
```

### 1.2 Implementación (`app/Adapters/Normalizers/InputNormalizer.php`)
- **Detección de Fuente (`detectSourceType`):** Inspecciona cabeceras HTTP (`X-Gitlab-Event`, `X-Github-Event`, `X-Slack-Signature`) y claves características de eventos (`object_kind`, `update_id`, `chat_id`, `pipeline_id`).
- **Extracción y Síntesis de Prompt (`extractPrompt`):**
  - Mapea campos literales: `prompt`, `user_prompt`, `message`, `text`, `content`, `query`, `input`.
  - Procesa mensajes anidados de chatbots (`message.text`, `event.text`).
  - Sintetiza texto para eventos de GitLab (`push`, `pipeline`, `merge_request`) y GitHub (`push`, `pull_request`).
  - Fallback seguro para cargas estructuradas arbitrarias.
- **Normalización de Identificadores (`extractThreadId`, `extractCorrelationId`):**
  - Preserva `thread_id`, `chat_id`, `conversation_id`, `channel_id`, `pipeline_id` o `build_id`.
  - Captura y propaga `X-Correlation-ID`.
  - Genera UUIDv4 en caso de ausencia.
- **Contexto y Metadatos:**
  - Construye `user_context` identificando al cliente externo (`external_client`).
  - Genera `metadata` con timestamp ISO-8601, IP de origen y fuente detectada.

### 1.3 Registro de Dependencias (`app/Providers/AppServiceProvider.php`)
```php
$this->app->singleton(
    \App\Adapters\Contracts\InputNormalizerInterface::class,
    \App\Adapters\Normalizers\InputNormalizer::class
);
```

---

## 2. Verificación de Pruebas Unitarias

La suite unitaria `tests/Unit/InputNormalizerTest.php` valida exhaustivamente:
1. Normalización de carga genérica con prompt explícito y parámetros.
2. Síntesis de prompt ante webhook de Push en GitLab CI/CD.
3. Síntesis de prompt ante webhook de Pull Request en GitHub.
4. Extracción de mensaje de texto y `chat_id` como `thread_id` en chatbots.
5. Inyección del flag `async` en el objeto de parámetros.
6. Propagación de cabecera `X-Correlation-ID`.
7. Procesamiento de estímulos recibidos vía parámetros de consulta URL (`query string`).
8. Preservación estricta de `execution_id` explícito y mapeo de `pipeline_id` a `thread_id`.
9. Síntesis de prompt de respaldo ante payloads JSON arbitrarios sin campos de texto conocidos.

```bash
vendor/bin/pest tests/Unit/InputNormalizerTest.php
```
**Resultado:** 9 pruebas pasadas al 100% (39 assertions en 0.38s).
