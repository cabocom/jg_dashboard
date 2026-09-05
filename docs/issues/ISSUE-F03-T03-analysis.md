# Análisis Técnico: ISSUE-F03-T03 — Endpoints de autenticación de usuarios: POST /auth/login y GET /auth/me

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F03-T03`  
> **Fase:** FASE 03 — Autenticación de Usuarios y RBAC  
> **Prioridad:** High  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F03-T02  

---

## 1. Objetivo del Issue

Proporcionar los endpoints HTTP para la autenticación interactiva de usuarios en la API REST de `jg_dashboard`.
Implementar:
1. `POST /api/v1/auth/login` (y `/api/auth/login`): Verificación de credenciales (email y contraseña), entrega de JWT Bearer, tipo de token, tiempo de expiración y metadatos del usuario.
2. `GET /api/v1/auth/me` (y `/api/auth/me`): Consulta del perfil del usuario actualmente autenticado mediante Bearer token, exponiendo su identificador, nombre, email, rol RBAC (`ADMIN`, `OPERATOR`, `VIEWER`) y catálogo de permisos activos.
3. `POST /api/v1/auth/logout` (y `/api/auth/logout`): Cierre y revocación de sesión.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Controlador de Autenticación (`App\Http\Controllers\Api\AuthController`):**
   - Método `login(Request $request)`: Valida formato de email y contraseña obligatoria; invoca `AuthService::attempt()`.
   - Método `me(Request $request)`: Protegido por middleware `jwt.auth`. Retorna estructura de perfil del usuario con array de permisos efectivos (`getPermissions()`).
   - Método `logout()`: Protegido por `jwt.auth`. Retorna `200 OK` confirmando cierre de sesión.

2. **Extensión del Modelo de Usuario (`App\Models\User`):**
   - Método `getPermissions(): array`: Calcula dinámicamente la lista de capacidades asignadas al usuario basándose en su rol y en la matriz RBAC (`agents:manage`, `hitl:approve`, `tasks:trigger`, `dashboard:view`, etc.).

3. **Rutas API (`routes/api.php`):**
   - Rutas versionadas:
     - `POST /api/v1/auth/login`
     - `GET /api/v1/auth/me`
     - `POST /api/v1/auth/logout`
   - Alias directos:
     - `POST /api/auth/login`
     - `GET /api/auth/me`
     - `POST /api/auth/logout`

4. **Criterios de Aceptación:**
   - Usuario autenticado obtiene token Bearer y puede consultar su perfil con roles y permisos correspondientes.
   - Peticiones a `/auth/me` sin token son rechazadas con `401 Unauthorized`.
   - Suite de pruebas de integración HTTP cubriendo login, perfil me y logout al 100%.

---

## 3. Decisiones Técnicas y de Seguridad

- **Seguridad en la Exposición de Permisos:** El endpoint `/auth/me` devuelve los permisos resueltos en tiempo de ejecución a partir del rol en base de datos, lo que permite al frontend o clientes API conocer sus capacidades dinámicamente sin hardcodear lógica de negocio.
- **Protección contra Fugas:** La serialización del usuario en `/auth/me` no expone bajo ninguna circunstancia hashes de contraseñas, tokens de reseteo ni atributos internos sensibles.

---

## 4. Plan de Verificación

- Suite Pest PHP `Tests\Feature\AuthApiTest`:
  - `api login returns 200 with jwt and user details on valid credentials`
  - `api login returns 401 on invalid credentials`
  - `api login returns 422 on validation failure`
  - `api auth me returns 401 when unauthenticated`
  - `api auth me returns user profile, role and permissions with valid token`
  - `api auth me returns admin full permissions`
  - `api logout endpoint returns 200 with success message`
- Ejecución completa mediante `./scripts/check.sh`.
