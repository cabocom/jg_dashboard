# Contexto Específico de Issue: ISSUE-F09-T01

**Issue ID:** ISSUE-F09-T01  
**Título:** Estructura base de la interfaz gráfica web (UI Templates / Static Assets)  
**Fase:** FASE 09 (Interfaz Gráfica Web y Consola HITL)  
**Versión de Contexto Global Vinculada:** 3.16.0  

---

## 1. Módulos y Clases Involucradas

- `resources/views/console/index.blade.php`: Plantilla principal HTML5 semántica.
- `public/css/console.css`: Estilado Vanilla CSS completo (glassmorphism, tema dark, micro-animaciones).
- `public/js/console.js`: Lógica cliente Vanilla JS (enrutador de pestañas, consumo REST, modales y polling).
- `app/Http/Controllers/WebConsoleController.php`: Controlador web que sirve la consola.
- `routes/web.php`: Rutas web públicas `/console` y `/dashboard`.
- `tests/Feature/WebConsoleTest.php`: Cobertura de pruebas de la consola web.

---

## 2. Decisiones Arquitectónicas Aplicadas

1. **Vanilla CSS y Vanilla JS (Cero Dependencias Pesadas de Build):**
   Para maximizar la resiliencia y velocidad de carga, la consola web se implementa sin depender de empaquetadores complejos o frameworks SPA, sirviendo CSS nativo de alta calidad y JS estándar moderno.
2. **Consumo Desacoplado de la API REST:**
   La interfaz gráfica consume exactamente los mismos endpoints que las integraciones externas (`/api/v1/agents`, `/api/v1/approvals/pending`, etc.), asegurando coherencia funcional completa.
3. **Compatibilidad con Filament:**
   Se preserva el panel Filament en `/admin` y la redirección `/` -> `/admin`, permitiendo coexistir a la consola operativa ligera (`/console`) y al panel de administración profunda de Filament.
