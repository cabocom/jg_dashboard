# Contexto del Proyecto: [DASH-SEC-01] JWT Secret con Fallback Hardcodeado Predecible en JwtService.php

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#47`
- **Código:** `DASH-SEC-01`
- **Severidad:** 🔴 CRÍTICO
- **CWE:** CWE-798 (Use of Hard-coded Credentials), CWE-1188 (Insecure Default Initialization of Resource)
- **Componentes Afectados:**
  - [`jg_dashboard/app/Services/Auth/JwtService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Services/Auth/JwtService.php)
  - [`jg_dashboard/.env.example`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/.env.example)
  - [`jg_dashboard/tests/Unit/JwtAndAuthServiceTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Unit/JwtAndAuthServiceTest.php)

---

## Descripción de la Vulnerabilidad
En el constructor de `JwtService.php`, la resolución del secreto criptográfico para la firma de tokens RFC 7519 incorporaba un valor fallback por defecto:
```php
$resolvedSecret = $secret 
    ?? config('auth.jwt.secret') 
    ?? config('app.key') 
    ?? 'default_jg_dashboard_jwt_secret_32_bytes_long';
```
Si un despliegue o entorno no configuraba explícitamente `auth.jwt.secret` ni `app.key`, la aplicación inicializaba `JwtService` silenciosamente con la clave fija pública `'default_jg_dashboard_jwt_secret_32_bytes_long'`.

## Impacto en Seguridad
Cualquier actor malicioso externo con conocimiento del código fuente podía forjar tokens JWT válidos utilizando HS256 y la clave fija conocida. Con un payload manipulado conteniendo claims de rol administrativo (`"role": "admin"`), el atacante podía eludir la autenticación de la API REST del Control Plane, ejecutar acciones privilegiadas, aprobar o rechazar ejecuciones HITL arbitrarias e interactuar con la consola de comandos sin credenciales legítimas.
