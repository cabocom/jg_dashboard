# Cierre de Issue: [DASHBOARD-UI-01] Reorganización de la Consola Web: Separación de Ejecución e Historial, Modal de Traza, Filtros/Ordenación, Acciones Rápidas en Agentes, Selector de Temas y Gestión de Perfil

## Descripción Consolidada
Se reestructuraron los componentes visuales y operativos de la consola web (`/console`) y el panel de control (`/admin`) de `jg_dashboard` para resolver la dispersión de flujos, unificar la nomenclatura de navegación con el menú lateral, incorporar personalización de estilos visuales, permitir la autogestión de usuarios (perfil y credenciales) e incorporar pie de página institucional.

---

## Solución Aplicada
1. **Flujo de Ejecuciones e Historial:**
   - Desacople completo entre `#view-launcher` (disparo y monitoreo en vivo) y `#view-executions` (auditoría analítica e historial).
   - Inclusión de la columna **Usuario** (`👤 devops_operator`, `👤 Support User`, etc.).
   - **Filtro por Agente:** Desplegable por **Agente** (`#executions-filter-agent-id`) para seleccionar agentes concretos mostrando únicamente su nombre legible (sin UUID), con preselección automática al navegar desde una tarjeta.
   - Apertura de modal interactivo flotante (`#modal-execution-trace`) al pulsar "Ver Traza" en cualquier registro del historial, permitiendo examinar los saltos cognitivos (LangGraph) y la salida sin perder el contexto de la tabla.
   - **Filtros por Fecha y Hora**: Controles de rango temporal "Desde" y "Hasta" con reseteo rápido (`#executions-filter-date-from`, `#executions-filter-date-to`, `#btn-clear-date-filters`).
   - **Ordenación por Columnas**: Posibilidad de ordenar dinámicamente tanto en frontend como backend por cualquiera de las columnas principales (`Prompt`, `Fecha y Hora`, `Agente`, `Usuario`, `Estado`) con indicadores interactivos de ordenación (`▲`, `▼`, `⇅`).

2. **Launcher en Tiempo Real:**
   - Supresión de controles obsoletos y selectores manuales del panel derecho del launcher, arrancando directamente con la visualización en tiempo real de los **Saltos Cognitivos y Transiciones de Nodos** conforme avanza la tarea.

3. **Tarjetas de Agentes:**
   - Total de ejecuciones integrado como enlace clicable interactivo inmediatamente tras la versión (`v1.0.0 • ⚡ X Ejecuciones`), preseleccionando automáticamente al agente en el filtro del historial.
   - Supresión del botón redundante de historial en el pie de la tarjeta.
   - Alineación en una sola fila: el estado del agente (`🟢 En línea (...)`) se sitúa a la izquierda y los botones `Lanzar Ejecución` y `Capacidades` se colocan a la derecha al mismo nivel.

4. **Personalización de Estilos y Temas (y Limpieza de Cabecera / Navegación):**
   - Botón y menú desplegable `🎨 Tema` en la cabecera superior.
   - Eliminación del botón manual redundante "Sincronizar" en la barra superior (aprovechando el polling automático de 15 segundos y el botón de actualización dedicado del historial).
   - El botón inferior de la barra lateral fue renombrado a **"Ir al admin →"** y configurado con visibilidad exclusiva para usuarios con rol `admin` (`@if(auth()->user()?->isAdmin())`).
   - 5 estilos visuales profesionales intercambiables en caliente y persistidos en `localStorage` (*Cyber Dark*, *Matrix Emerald*, *Midnight Slate*, *Amethyst Night*, *High Contrast*).

5. **Autogestión de Perfil y Contraseñas de Usuarios:**
   - Botón `👤 Mi Perfil` en la cabecera superior y modal `#modal-user-profile`.
   - Modificación segura de nombre, email y contraseña con validaciones estrictas y verificación de clave actual.
   - Habilitado `->profile()` en `AdminPanelProvider` para soporte nativo en el panel Filament (`/admin`).

6. **Pie de Página Institucional:**
   - Footer responsivo con efecto blur y degradado oscuro que incluye `© YYYY • Todos los derechos reservados por Javier Gálvez` enlazando a [javiergalvez.com](https://javiergalvez.com).

7. **Verificación Automatizada:**
   - `UserProfileWebTest.php`: 5 tests pasados (16 aserciones).
   - `ExecutionViewerWebTest.php`: 9 tests pasados (95 aserciones).
   - 262 tests superados en `jg_dashboard` (1.289 aserciones).
   - Suite completa del repositorio ejecutada satisfactoriamente con cero regresiones.
