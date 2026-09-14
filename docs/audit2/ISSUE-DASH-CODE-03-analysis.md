# Análisis Técnico: [DASH-CODE-03] Código Muerto Inseguro con Autenticación Inconsistente (DevOps60kApiClient)

## 1. Causa Raíz (CWE-561)
El archivo `jg_dashboard/app/Services/DevOps60kApiClient.php` fue mantenido tras la migración a la arquitectura multi-agente de `jg_dashboard`.
Contenía un método de transporte que inyectaba credenciales estáticas en texto plano:
```php
protected function client()
{
    return Http::baseUrl($this->baseUrl)
        ->timeout($this->timeout)
        ->retry($this->retryTimes, $this->retrySleepMs)
        ->withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'DevOps60k-Web-Panel/1.0',
        ]);
}
```
Tras la retirada del singleton en `AppServiceProvider` y la adopción de `AgentHttpClient` para todos los widgets y controladores, este archivo y su suite de pruebas (`tests/Feature/DevOps60kApiClientTest.php`) constituían código muerto (CWE-561) desalineado con las políticas de autenticación y firma HMAC.

## 2. Implicaciones de Seguridad y Mantenimiento
- **Desalineación Criptográfica:** El agente (`jg_agente`) valida firmas HMAC canónicas mediante middleware `VerifyHmacSignature`. `DevOps60kApiClient` enviaba cabeceras incompatibles `X-API-Key` con valores predeterminados predecibles (`admin_key_pro`).
- **Complejidad Innecesaria:** Mantener pruebas para componentes no utilizados ralentizaba la suite de pruebas y generaba falsa sensación de cobertura en componentes obsoletos.

## 3. Solución Implementada
1. **Eliminación del Archivo Legacy:** Se suprimió `app/Services/DevOps60kApiClient.php`.
2. **Eliminación de Tests Asociados:** Se suprimió `tests/Feature/DevOps60kApiClientTest.php`.
3. **Actualización del README:** Se clarificó la estructura de servicios en `README.md` destacando `AgentHttpClient` y `AgentRegistrationService`.
