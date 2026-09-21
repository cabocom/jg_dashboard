# Contexto del Proyecto: [DASH-SEC-04] Ausencia de Verificación CSRF en Acciones HITL de Consola Web

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#50`
- **Código:** `DASH-SEC-04`
- **Severidad:** 🟡 MEDIO
- **CWE:** CWE-352 (Cross-Site Request Forgery)
- **Componentes Afectados:**
  - [`jg_dashboard/bootstrap/app.php`](../../../bootstrap/app.php)
  - [`jg_dashboard/resources/views/console/index.blade.php`](../../../resources/views/console/index.blade.php)
  - [`jg_dashboard/public/js/console.js`](../../../public/js/console.js)
  - [`jg_dashboard/tests/Feature/HitlConsoleWebTest.php`](../../../tests/Feature/HitlConsoleWebTest.php)

---

## Descripción de la Vulnerabilidad
En la configuración de middleware de la aplicación Laravel (`bootstrap/app.php:20-23`), la directiva `$middleware->validateCsrfTokens(except: [...])` contenía `'console/*'`, eximiendo a todas las rutas de la consola web de la validación de tokens CSRF.
Aunque las rutas bajo `/console/*` (`routes/web.php`) se encuentran protegidas por autenticación de sesión (`auth`) y autorización por roles (`role:admin,operator`), la ausencia de comprobación del token CSRF permitía que un atacante engañara a un operador o administrador con sesión activa para ejecutar de forma involuntaria acciones sensibles (Cross-Site Request Forgery).

## Impacto en Seguridad
Un atacante externo podría inducir a un operador con sesión abierta a visitar un sitio malicioso o hacer clic en un enlace señuelo que envíe peticiones web dirigidas (`POST`) a la consola web:
- Aprobar operaciones HITL críticas no autorizadas (`POST /console/approvals/{id}/approve`).
- Rechazar arbitrariamente operaciones en curso provocando denegación de servicio (`POST /console/approvals/{id}/reject`).
- Disparar ejecuciones con prompts o parámetros maliciosos (`POST /console/executions/trigger`).
- Aprovisionar agentes no autorizados en el Control Plane (`POST /console/agents`).
