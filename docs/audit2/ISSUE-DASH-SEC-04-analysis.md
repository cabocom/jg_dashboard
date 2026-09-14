# Análisis Técnico: [DASH-SEC-04] Ausencia de Verificación CSRF en Acciones HITL de Consola Web

## 1. Causa Raíz (CWE-352)
En `jg_dashboard/bootstrap/app.php`:
```php
$middleware->validateCsrfTokens(except: [
    'console/*',
    'api/*',
]);
```
La regla excluía de forma global el prefijo `console/*` del middleware `ValidateCsrfToken` de Laravel.
Dicha excepción anulaba el mecanismo de defensa contra Cross-Site Request Forgery (CWE-352) para todas las operaciones mutables realizadas a través de la consola web.

## 2. Implicaciones de Seguridad
1. **Falsificación de Peticiones en Sitios Cruzados:** Al depender exclusivamente de la cookie de sesión del navegador (`laravel_session`), las peticiones originadas desde dominios de terceros (`cross-origin`) incluían automáticamente las credenciales de sesión en navegadores sin particionamiento estricto o políticas `SameSite` completas.
2. **Impacto Crítico en Acciones HITL:** Las solicitudes de aprobación o rechazo de acciones críticas (reinicio de nodos, escalado de recursos, ejecución de scripts de mitigación) podían ser manipuladas por atacantes sin requerir conocimiento de las credenciales del usuario.
3. **Coherencia con el Front-end:** La interfaz web de la consola (`resources/views/console/index.blade.php`) ya contaba con la metaetiqueta `<meta name="csrf-token" content="{{ csrf_token() }}">` y la capa JavaScript (`public/js/console.js`) ya inyectaba sistemáticamente `X-CSRF-TOKEN` en `getAuthHeaders()`. Por tanto, la excepción en `bootstrap/app.php` era innecesaria y constituía un fallo grave de configuración de seguridad.

## 3. Solución Implementada
1. **Eliminación de la Excepción en `bootstrap/app.php`:**
   Se removió `'console/*'` de `$middleware->validateCsrfTokens(except: [...])`, manteniendo únicamente `'api/*'` exento (pues la API utiliza autenticación sin estado mediante cabeceras JWT / HMAC).
2. **Defensa en Profundidad en Formularios Blade:**
   Se incorporó la directiva `@csrf` dentro de todos los elementos `<form>` presentes en `resources/views/console/index.blade.php` (`#form-trigger-execution`, `#form-user-profile`, `#form-confirm-approve`, `#form-confirm-reject`, `#form-create-agent`).
3. **Verificación Automatizada de Seguridad:**
   Se implementaron pruebas de integración en `tests/Feature/HitlConsoleWebTest.php` comprobando que las peticiones sin token CSRF son rechazadas con HTTP 419 y que las peticiones con cabecera `X-CSRF-TOKEN` válida son admitidas y procesadas.
