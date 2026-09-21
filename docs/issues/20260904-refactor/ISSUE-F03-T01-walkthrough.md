# Walkthrough: ISSUE-F03-T01 — Servicio de autenticación JWT y hashing de contraseñas

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el servicio de autenticación JWT y hashing seguro de contraseñas para `jg_dashboard`:

1. **Configuración de Autenticación ([config/auth.php](../../../config/auth.php)):**
   - Agregada sección `jwt` con clave secreta (`JWT_SECRET`), TTL configurable en minutos (`JWT_TTL_MINUTES`, por defecto 60) y algoritmo `HS256`.

2. **Servicio Criptográfico JWT ([app/Services/Auth/JwtService.php](../../../app/Services/Auth/JwtService.php)):**
   - Implementación nativa de RFC 7519 HMAC-SHA256.
   - Emisión de tokens firmados con claims del usuario (`sub`, `email`, `role`, `name`, etc.).
   - Validación robusta contra tokens manipulados, cabeceras alteradas (`alg: none`) y tokens expirados.

3. **Servicio de Autenticación ([app/Services/Auth/AuthService.php](../../../app/Services/Auth/AuthService.php)):**
   - Método `attempt()` que verifica credenciales con hash seguro y emite el token Bearer.
   - Helpers de hashing y verificación de contraseñas.

4. **Capa HTTP y Rutas API ([app/Http/Controllers/Api/AuthController.php](../../../app/Http/Controllers/Api/AuthController.php), [routes/api.php](../../../routes/api.php), [bootstrap/app.php](../../../bootstrap/app.php)):**
   - Configuración de enrutamiento API en `bootstrap/app.php`.
   - Endpoints de login: `POST /api/v1/auth/login` y `POST /api/auth/login`.
   - Controlador base `App\Http\Controllers\Controller`.

5. **Batería de Pruebas Unitarias y de Integración:**
   - [tests/Unit/JwtAndAuthServiceTest.php](../../../tests/Unit/JwtAndAuthServiceTest.php): 7 pruebas unitarias para `JwtService` y `AuthService`.
   - [tests/Feature/AuthApiTest.php](../../../tests/Feature/AuthApiTest.php): 3 pruebas de integración para el endpoint HTTP de login.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentModelTest
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
  ✓ jwt service generates valid rfc 7519 token with user claims          0.01s  
  ✓ jwt service rejects tampered tokens and signatures                   0.01s  
  ✓ jwt service rejects tokens with alg none or invalid headers
  ✓ jwt service rejects expired tokens                                   0.01s  
  ✓ auth service attempt succeeds with valid credentials                 0.01s  
  ✓ auth service attempt rejects invalid password and non-existent emai… 0.01s  
  ✓ auth service hashPassword and verifyPassword helpers                 0.01s  

   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AgentTaskResourceTest

   PASS  Tests\Feature\AuthApiTest
  ✓ api login returns 200 with jwt and user details on valid credential… 0.02s  
  ✓ api login returns 401 on invalid credentials                         0.01s  
  ✓ api login returns 422 on validation failure                          0.01s  

   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    53 passed (248 assertions)
  Duration: 1.54s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Próximos Pasos

Continuar con la **FASE 03**:
- **[ISSUE-F03-T02] Middleware y dependencias de autorización RBAC** (proteger endpoints con roles `ADMIN`, `OPERATOR`, `VIEWER` respondiendo 401 y 403).
