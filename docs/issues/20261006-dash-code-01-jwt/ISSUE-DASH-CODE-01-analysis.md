# Análisis Técnico: [DASH-CODE-01] Implementación Manual de Criptografía JWT sin Rotación ni Blacklist

## 1. Contexto y Problema
En `jg_dashboard/app/Services/Auth/JwtService.php`, la codificación Base64URL, la firma criptográfica HMAC-SHA256 y la validación de claims RFC 7519 estaban implementadas de forma artesanal mediante manipulación directa de cadenas y llamadas a `hash_hmac()`.

### Deficiencias Identificadas:
1. **Ausencia de Biblioteca Estándar:** Riesgo de vulnerabilidades criptográficas sutiles al no utilizar bibliotecas auditadas por la comunidad (como `firebase/php-jwt`).
2. **Sin Rotación de Claves (`kid`):** El sistema dependía de un único secreto estático sin soporte para migrar o rotar credenciales mediante cabeceras `kid` (Key ID), impidiendo rotaciones sin caída de sesiones activas.
3. **Ausencia de `jti` (JWT ID):** Los tokens emitidos no contenían un identificador criptográfico único por token, impidiendo auditorías granulares y revocaciones específicas.
4. **Sin Lista de Revocación / Blacklist:** Al emitir un token válido por $N$ minutos, este no podía ser revocado antes de su caducidad, dejando sesiones vulnerables tras logout o compromiso de credenciales.
5. **Falta de Validación de Longitud Mínima de Secreto:** La implementación manual no exigía los 256 bits (32 bytes) mínimos requeridos por el estándar HMAC-SHA256 (RFC 7518 §3.2).

## 2. Solución Diseñada
1. **Adopción de `firebase/php-jwt`:**
   - Incorporación de `firebase/php-jwt` (v7.2.1) en `jg_dashboard/composer.json`.
   - Reemplazo de la lógica manual por `Firebase\JWT\JWT` y `Firebase\JWT\Key`.
2. **Soporte de Rotación de Claves (`kid`):**
   - Incorporación de cabecera `kid` en todos los tokens emitidos.
   - Soporte para mapeo de claves en `config/auth.php` (`jwt.key_id` y `jwt.keys`), permitiendo verificar tokens firmados con versiones previas mientras se emite con la activa.
3. **Identificador Único `jti`:**
   - Generación de UUIDv4 por token emitido como claim obligatorio `jti`.
4. **Mecanismo de Blacklist / Revocación Persistente y en Caché:**
   - Creación de tabla PostgreSQL `dashboard_jwt_revocations` con campos `jti`, `user_id`, `revoked_at`, `expires_at`, `reason`.
   - Modelo Eloquent `App\Models\JwtRevocation`.
   - Doble capa de almacenamiento (L1 Cache con TTL configurable + L2 PostgreSQL) para consultas ultrarrápidas en middleware.
   - Integración en `AuthController::logout` para invalidar de inmediato el token de la sesión saliente.
5. **Validación Criptográfica de Longitud de Clave:**
   - Validación explícita de clave mínima de 32 bytes (256 bits) para HMAC-SHA256 en `JwtService`.

## 3. Criterios de Aceptación
- [x] Librería estándar `firebase/php-jwt` integrada y funcionando.
- [x] Tokens generados incluyen `jti` (UUIDv4) y `kid`.
- [x] Soporte para verificación de tokens bajo claves rotadas mediante `kid`.
- [x] Tokens revocados son rechazados de inmediato por `verifyToken()`.
- [x] Llamada a `/api/v1/auth/logout` revoca el token y subsecuentes llamadas a endpoints protegidos retornan `401 Unauthorized`.
- [x] 100% de tests unitarios y de integración pasando.
