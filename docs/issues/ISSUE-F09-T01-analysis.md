# Análisis Técnico: [ISSUE-F09-T01] Estructura base de la interfaz gráfica web (UI Templates / Static Assets)

**Issue ID:** ISSUE-F09-T01  
**Fase:** FASE 09 (Interfaz Gráfica Web y Consola HITL)  
**Prioridad:** High  
**Complejidad:** M  
**Dependencias:** ISSUE-F03-T03  

---

## 1. Contexto y Objetivos

Para que los operadores y administradores humanos supervisen y operen el ecosistema de agentes cognitivos sin depender de llamadas directas mediante herramientas de terminal (curl, Postman), el plano de control (`jg_dashboard`) requiere una consola web gráfica moderna, accesible desde cualquier navegador.

Siguiendo las directrices del proyecto:
- **Tecnología Base:** HTML5 semántico estructurado, Vanilla CSS para estilado sin frameworks pesados, y Vanilla JS (ES6) para interactividad y comunicación asíncrona.
- **Estética Visual:** Diseño dark-mode de alta tecnología (paleta HSL a medida, glassmorphism, micro-animaciones, tipografías Google Fonts como Inter y Outfit).
- **Navegación Fluida:** Conmutación reactiva entre secciones principales:
  1. Catálogo y salud de **Agentes** (`#nav-agents`).
  2. Consola de **Aprobaciones HITL** (`#nav-approvals`).
  3. Pasarela de disparo de **Ejecuciones** (`#nav-executions`).

---

## 2. Arquitectura de la Solución

### 2.1 Enrutamiento y Controladores
- **Rutas Web:** `GET /console` y `GET /dashboard` registradas en `routes/web.php`.
- **Compatibilidad con Filament:** La ruta raíz `GET /` se preserva redirigiendo a `/admin` para garantizar compatibilidad con los tests y el panel de administración.
- **Controlador:** `App\Http\Controllers\WebConsoleController@index` precarga métricas consolidadas (`totalAgents`, `activeAgents`, `offlineAgents`, `pendingApprovalsCount`) y renderiza la vista `console.index`.

### 2.2 Estilado Vanilla CSS (`public/css/console.css`)
- Variables CSS para paleta cromática (fondo `#090a0f`, superficies semitransparentes `#161a26`, acentos esmeralda `#10b981`, cian `#06b6d4`, rosa crítico `#f43f5e`).
- Sistema de tarjetas KPI con indicadores luminosos y bordes degradados.
- Tablas de datos responsivas, badges de estado con animación de pulso y modales centrados flotantes.

### 2.3 Interactividad Vanilla JS (`public/js/console.js`)
- Manejo de pestañas sin recarga de página (`switchTab`).
- Consumo asíncrono de los endpoints REST del Dashboard:
  - `GET /api/v1/agents`
  - `GET /api/v1/approvals/pending`
  - `POST /api/v1/approvals/{id}/approve`
  - `POST /api/v1/approvals/{id}/reject`
  - `POST /api/v1/executions/trigger`
- Sistema de notificaciones toast flotantes para retroalimentación inmediata.
- Ciclo de sondeo periódico (polling cada 15 segundos) para reflejar cambios en tiempo real.

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard web accesible en navegador en `/console` y `/dashboard`.
- [x] Navegación interactiva entre agentes, aprobaciones HITL y ejecuciones.
- [x] Estructura HTML semántica con estilos Vanilla CSS y script Vanilla JS.
- [x] Contadores KPI precargados e integración con API REST.
- [x] Modales de aprobación y rechazo con validación de motivos.
- [x] 100% de pruebas automáticas superadas en `./scripts/check.sh`.
