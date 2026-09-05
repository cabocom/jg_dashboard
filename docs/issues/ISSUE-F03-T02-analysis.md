# Análisis Técnico: ISSUE-F03-T02 — Middleware y dependencias de autorización RBAC

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F03-T02`  
> **Fase:** FASE 03 — Autenticación de Usuarios y RBAC  
> **Prioridad:** Critical  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F03-T01  

---

## 1. Objetivo del Issue

Implementar la capa de middleware de autenticación y autorización basada en roles (RBAC) para el plano de control (`jg_dashboard`).
Garantizar que:
1. Las peticiones a rutas protegidas sin token, con token expirado o alterado reciban de forma unificada un código de estado `401 Unauthorized`.
2. Las peticiones autenticadas pero con rol insuficiente según la matriz RBAC (`ADMIN`, `OPERATOR`, `VIEWER`) reciban un código de estado `403 Forbidden`.
3. Los usuarios con rol `ADMIN` dispongan de acceso universal mediante jerarquía de roles, y los permisos granulares (`require_permission`) sean evaluados rigurosamente.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Middleware de Autenticación (`App\Http\Middleware\JwtAuthenticate` / alias `jwt.auth`):**
   - Inspección del encabezado HTTP `Authorization: Bearer <token>`.
   - Si no hay token: retorno inmediato `401 Unauthorized` (`error: 'Unauthorized'`, `message: 'Token de autenticación no provisto'`).
   - Verificación criptográfica con `JwtService`: si el token es inválido o ha expirado: `401 Unauthorized` (`message: 'Token de autenticación inválido o expirado'`).
   - Búsqueda del usuario activo en base de datos: si no existe, `401 Unauthorized`.
   - Inyección del usuario autenticado en el contexto de Laravel (`auth()->setUser($user)`, `$request->setUserResolver()`, `$request->attributes->set('jwt_payload', ...)`).

2. **Middleware de Rol RBAC (`App\Http\Middleware\RequireRole` / alias `role`):**
   - Soporte para listas de roles permitidos (ej. `role:admin` o `role:operator,admin`).
   - Jerarquía RBAC integrada:
     - `ADMIN`: Acceso a operaciones de administración, operación y visualización.
     - `OPERATOR`: Acceso a operaciones de operador y visualización.
     - `VIEWER`: Acceso exclusivo de solo lectura.
   - Si el rol es insuficiente: retorno `403 Forbidden` (`error: 'Forbidden'`, `message: 'Acceso denegado: rol insuficiente'`).

3. **Middleware de Permisos Granulares (`App\Http\Middleware\RequirePermission` / alias `permission`):**
   - Evaluación de matriz de capacidades del Control Plane (`agents:manage`, `hitl:approve`, `tasks:trigger`, `dashboard:view`, etc.).
   - Retorno `403 Forbidden` si el usuario carece del permiso.

4. **Criterios de Aceptación:**
   - Endpoints protegidos devuelven 403 Forbidden a usuarios con roles insuficientes.
   - Endpoints protegidos devuelven 401 Unauthorized a peticiones no autenticadas o con token inválido/expirado.
   - Suite de pruebas de integración HTTP en Pest PHP con 100% de éxito.

---

## 3. Decisiones Técnicas y de Seguridad

- **Registro en `bootstrap/app.php`:** Los middlewares fueron registrados como aliases nativos de Laravel 11 (`jwt.auth`, `role`, `permission`), lo que permite su uso declarativo en controladores y grupos de rutas (`routes/api.php`).
- **Seguridad en Fallo Abierto vs. Fallo Cerrado:** Si una petición llega a `role` sin un usuario autenticado previo, el middleware falla de forma cerrada devolviendo `401 Unauthorized` automáticamente.
- **Defensa en Profundidad:** El modelo `User` expone métodos utilitarios testeados (`hasRole()`, `hasPermission()`, `isAdmin()`, `isOperator()`, `isViewer()`) que centralizan la lógica de autorización tanto para la API REST como para Filament Admin Panel.

---

## 4. Plan de Verificación

- Suite Pest PHP incluyendo `Tests\Feature\RbacMiddlewareTest`:
  - `unauthenticated request to protected endpoint returns 401 unauthorized`
  - `request with invalid or tampered token returns 401 unauthorized`
  - `request with expired token returns 401 unauthorized`
  - `viewer role receives 403 forbidden on admin-only endpoint`
  - `operator role receives 403 forbidden on admin-only endpoint`
  - `admin role accesses admin-only endpoint successfully`
  - `operator role accesses operator endpoint successfully`
  - `admin role accesses operator endpoint through role hierarchy`
  - `viewer role receives 403 forbidden on operator endpoint`
  - `viewer role accesses viewer endpoint successfully`
  - `permission middleware enforces permissions according to rbac matrix`
- Verificación completa con `./scripts/check.sh`.
