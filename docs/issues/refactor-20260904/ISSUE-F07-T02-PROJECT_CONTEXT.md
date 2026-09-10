# Contexto Específico de Issue: ISSUE-F07-T02

**Issue ID:** ISSUE-F07-T02  
**Título:** Endpoint de listado de aprobaciones pendientes: GET /api/v1/approvals/pending  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Versión de Contexto Global Vinculada:** 3.13.0  

---

## 1. Módulos y Clases Involucradas

- `app/Http/Controllers/Api/HitlApprovalController.php`: Controlador REST para gestionar peticiones sobre solicitudes de aprobación humana.
- `app/Services/Hitl/HitlApprovalService.php`: Capa de servicio encargada de la consulta paginada, filtrado y normalización del formato canónico de solicitudes HITL.
- `routes/api.php`: Rutas públicas del Control Plane para operadores y administradores.
- `tests/Feature/HitlApprovalsPendingApiTest.php`: Pruebas de integración del endpoint con validaciones de seguridad RBAC, filtros y estructuras de datos.

---

## 2. Decisiones Arquitectónicas Aplicadas

1. **Gobernanza RBAC Restringida:**
   Solo los roles de operación y administración (`operator`, `admin`) tienen permiso para consultar la cola de aprobaciones pendientes, preservando el principio de menor privilegio sobre roles observadores (`viewer`).
2. **Cálculo Dinámico de Métricas Temporales:**
   El tiempo transcurrido se calcula en tiempo de consulta combinando una representación legible (`diffForHumans()`) y un entero en segundos (`elapsed_seconds`), facilitando la implementación de contadores en interfaces reactivas (Filament / frontend).
3. **Resumen de Acciones Automático:**
   Para evitar que el operador tenga que inspeccionar manualmente estructuras JSON complejas del plan propuesto, el servicio extrae una síntesis directa del paso, comando o herramientas a ejecutar.
