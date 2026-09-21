# Walkthrough: ISSUE-F03-T02 — Middleware y dependencias de autorización RBAC

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el conjunto de middlewares de autenticación JWT y autorización RBAC para `jg_dashboard`:

1. **Middleware de Autenticación JWT ([app/Http/Middleware/JwtAuthenticate.php](../../../app/Http/Middleware/JwtAuthenticate.php)):**
   - Valida el token Bearer usando `JwtService`.
   - Rechaza peticiones sin token o con token alterado/vencido con `401 Unauthorized`.
   - Inyecta el usuario autenticado en la sesión y contexto del request.

2. **Middleware de Roles RBAC ([app/Http/Middleware/RequireRole.php](../../../app/Http/Middleware/RequireRole.php)):**
   - Comprueba si el usuario autenticado posee los roles solicitados (`ADMIN`, `OPERATOR`, `VIEWER`).
   - Aplica jerarquía de roles (ADMIN incluye permisos de OPERATOR y VIEWER).
   - Retorna `403 Forbidden` si los roles son insuficientes.

3. **Middleware de Permisos Granulares ([app/Http/Middleware/RequirePermission.php](../../../app/Http/Middleware/RequirePermission.php)):**
   - Valida permisos de capacidades operativas (`agents:manage`, `hitl:approve`, etc.) respondiendo `403 Forbidden` cuando no corresponde.

4. **Registro de Middleware Aliases ([bootstrap/app.php](../../../bootstrap/app.php)):**
   - Registrados los aliases `jwt.auth`, `role` y `permission` en el pipeline de Laravel 11.

5. **Lógica de Roles en Modelo ([app/Models/User.php](../../../app/Models/User.php)):**
   - Métodos `hasRole()`, `hasPermission()` e `isViewer()` agregados y normalizados.

6. **Rutas Protegidas y Pruebas ([routes/api.php](../../../routes/api.php), [tests/Feature/RbacMiddlewareTest.php](../../../tests/Feature/RbacMiddlewareTest.php)):**
   - 11 pruebas de integración verificando escenarios de 401 por falta de token o expiración, y 403 por roles insuficientes entre ADMIN, OPERATOR y VIEWER.

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
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest

   PASS  Tests\Feature\RbacMiddlewareTest
  ✓ unauthenticated request to protected endpoint returns 401 unauthori… 0.02s  
  ✓ request with invalid or tampered token returns 401 unauthorized      0.01s  
  ✓ request with expired token returns 401 unauthorized                  0.01s  
  ✓ viewer role receives 403 forbidden on admin-only endpoint            0.01s  
  ✓ operator role receives 403 forbidden on admin-only endpoint          0.01s  
  ✓ admin role accesses admin-only endpoint successfully                 0.01s  
  ✓ operator role accesses operator endpoint successfully                0.01s  
  ✓ admin role accesses operator endpoint through role hierarchy         0.01s  
  ✓ viewer role receives 403 forbidden on operator endpoint              0.01s  
  ✓ viewer role accesses viewer endpoint successfully                    0.01s  
  ✓ permission middleware enforces permissions according to rbac matrix  0.01s  

   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    64 passed (271 assertions)
  Duration: 1.76s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Próximos Pasos

Continuar con la **FASE 03**:
- **[ISSUE-F03-T03] Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me** (consulta de perfil y roles para usuarios autenticados).
