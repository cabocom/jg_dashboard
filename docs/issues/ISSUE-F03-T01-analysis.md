# Análisis Técnico: ISSUE-F03-T01 — Servicio de autenticación JWT y hashing de contraseñas

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F03-T01`  
> **Fase:** FASE 03 — Autenticación de Usuarios y RBAC  
> **Prioridad:** Critical  
> **Complejidad Estimada:** M  
> **Dependencias:** ISSUE-F02-T01  

---

## 1. Objetivo del Issue

Implementar la infraestructura criptográfica y de autenticación para los operadores y administradores del Control Plane (`jg_dashboard`).
Desarrollar:
1. Servicio de generación y validación de tokens JWT conforme al estándar RFC 7519 (algoritmo HMAC-SHA256), incluyendo claims de identidad (`sub`, `email`, `name`, `role`) y control estricto de caducidad (`exp`).
2. Mecanismo de hashing seguro de contraseñas mediante Bcrypt / Argon2id integrado en `AuthService`.
3. Endpoint de login seguro `POST /api/v1/auth/login` (y su alias `/api/auth/login`) para verificar credenciales y emitir tokens Bearer.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Servicio JWT (`App\Services\Auth\JwtService`):**
   - Implementación de RFC 7519 con firma HMAC-SHA256 (`HS256`).
   - Resolución de secreto criptográfico desde `config('auth.jwt.secret')`, `env('JWT_SECRET')` o `config('app.key')`.
   - Generación de tokens con claims: `iss`, `sub` (ID del usuario), `email`, `role`, `name`, `iat`, `nbf` y `exp` (TTL configurable, 60 minutos por defecto).
   - Verificación estricta: validación estructural en 3 segmentos, rechazo explícito de `alg: none`, comprobación de firma con `hash_equals()` en tiempo constante, y validación de expiración (`exp`) y no-antes (`nbf`).
   - Helpers: `verifyToken()`, `isExpired()`, `getUserFromToken()`, `base64UrlEncode()`, `base64UrlDecode()`.

2. **Servicio de Autenticación (`App\Services\Auth\AuthService`):**
   - Método `attempt(string $email, string $password): ?array`:
     - Búsqueda de usuario por email.
     - Verificación de contraseña mediante `Hash::check()`.
     - En caso de éxito, emisión de JWT y respuesta estandarizada con `access_token`, `token_type: 'Bearer'`, `expires_in` y datos esenciales del usuario.
     - En caso de fallo, retorno nulo seguro sin revelar si el usuario existe o no (prevención de enumeración).
   - Métodos auxiliares `hashPassword()` y `verifyPassword()`.

3. **Controlador y Rutas API:**
   - Registro de rutas API en `routes/api.php` y `bootstrap/app.php`.
   - Endpoint `POST /api/v1/auth/login` y `POST /api/auth/login`.
   - Respuestas HTTP estándar: `200 OK` en éxito, `401 Unauthorized` en credenciales incorrectas, `422 Unprocessable Entity` en fallos de validación.

4. **Criterios de Aceptación:**
   - Endpoints de login emiten JWT válidos con claims de usuario y rol.
   - Contraseñas inválidas son rechazadas con código 401.
   - Tokens manipulados o expirados son invalidados.
   - Suite de pruebas unitarias y de integración pasando al 100%.

---

## 3. Decisiones Técnicas y de Seguridad

- **Seguridad Criptográfica (RFC 7519 nativo):** Se implementó una solución sin dependencias externas pesadas, asegurando control total sobre el algoritmo aceptado (`HS256`), bloqueando ataques comunes como el *algorithm confusion attack* (`alg: none`).
- **Timing Attacks:** La verificación de la firma del token se ejecuta mediante `hash_equals()` para evitar ataques de canal lateral basados en tiempo.
- **Prevención de Enumeración:** Ante credenciales incorrectas (usuario no encontrado o contraseña incorrecta), la API responde de forma idéntica con `401 Unauthorized` y mensaje genérico `Credenciales inválidas`.

---

## 4. Plan de Verificación

- Suite Pest PHP incluyendo:
  - `Tests\Unit\JwtAndAuthServiceTest`: Pruebas de generación RFC 7519, claims, rechazo de firmas alteradas, rechazo de `alg: none`, rechazo de tokens vencidos, hashing y verificación de credenciales.
  - `Tests\Feature\AuthApiTest`: Pruebas de integración HTTP sobre `POST /api/v1/auth/login` (200 con payload completo, 401 por contraseña errónea, 401 por usuario inexistente, 422 por validación).
- Ejecución completa de `./scripts/check.sh`.
