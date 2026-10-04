# 06. Observabilidad, Resiliencia y Topología de Infraestructura (`jg_dashboard`)

[← Volver al README](../README.md) | [← 05. Esquema de Base de Datos](05_database_schema.md)

---

## 1. Visión General de la Capa de Observabilidad

`jg_dashboard` integra un subsistema completo de observabilidad, resiliencia y supervisión topológica diseñado para entornos de ingeniería de fiabilidad de sitios (SRE). Esta capa garantiza que el Control Plane no sea un mero CRUD administrativo, sino un centro de mando capaz de tolerar fallos transitorios en agentes satélites, prevenir tormentas de alertas y representar visualmente el estado del ecosistema en tiempo real.

```text
┌────────────────────────────────────────────────────────────────────────┐
│                        Observabilidad del Dashboard                    │
│                                                                        │
│   ┌─────────────────────┐   ┌───────────────────┐   ┌───────────────┐  │
│   │   Circuit Breaker   │   │  Mapa Topológico  │   │ Deduplicación │  │
│   │   (Estados/Filtros) │   │  (Grafo Nodos/BD) │   │ de Incidentes │  │
│   └──────────┬──────────┘   └─────────┬─────────┘   └───────┬───────┘  │
│              │                        │                     │          │
│              ▼                        ▼                     ▼          │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │             Widgets de Telemetría en Tiempo Real                │  │
│   │       - AgentHealthWidget (Polling 10s)                         │  │
│   │       - ActiveTasksOverview (Polling 4s)                        │  │
│   │       - CircuitBreakersStatus (Alertas en Rojo)                 │  │
│   └─────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Gestor de Circuit Breakers (`CircuitBreakerState`)

Para evitar la saturación en cascada cuando un agente satélite o una API externa de infraestructura (Kubernetes, Docker Socket, LLM Provider) experimenta degradación severa, el Dashboard implementa el patrón **Circuit Breaker** por servicio destino.

### 2.1 Modelo y Estados (`circuit_breaker_state`)
El modelo `App\Models\CircuitBreakerState` gestiona el estado del circuito para cada clave o `target`:
- **`target` (PK):** Identificador del servicio supervisado (ej. `k8s_api`, `agent_devops`, `agent_conversational`).
- **`is_tripped` (Boolean):** Indica si el interruptor está disparado (`true` = circuito abierto / corte de llamadas) o saludable (`false` = circuito cerrado / tráfico normal).
- **`tripped_reason` (Text):** Diagnóstico técnico o mensaje de error que provocó la apertura del circuito.
- **`failure_history_json` (JSON Array):** Registro cronológico de excepciones, códigos HTTP y timestamps de fallos recientes.
- **`updated_at` (DateTime):** Marca temporal de la última actualización de estado.

### 2.2 Gestión en Panel Administrativo (`CircuitBreakerResource`)
En Filament v3, el recurso `CircuitBreakerResource` expone:
- **Badge Dinámico de Peligro:** Muestra en rojo el recuento exacto de circuitos disparados en el menú de navegación (`danger`).
- **Polling Reactivo de 5 Segundos:** Actualización automática de la tabla de circuitos sin recargar la página.
- **Infolist con Historial Formateado:** Visualizador JSON con resaltado monospace sobre fondo oscuro para auditar las causas del disparo.
- **Filtro Ternario:** Segmentación rápida entre circuitos disparados (*Tripped*) y operativos (*Healthy*).

---

## 3. Mapa de Topología Dinámica de Infraestructura (`TopologyMap`)

El Dashboard proporciona una representación visual en grafo de la topología completa del ecosistema mediante la página `App\Filament\Pages\TopologyMap`.

### 3.1 Persistencia de Snapshots (`infra_topology`)
El modelo `App\Models\InfraTopology` almacena snapshots estructurados en PostgreSQL:
- **`target` (PK):** Clave de snapshot (por defecto `'current'`).
- **`nodes_json` (JSON Array):** Lista de nodos del sistema, especificando `id`, `label`, `type` (`host`, `container`, `database`), `status` (`healthy`, `running`, `warning`, `down`) y metadatos.
- **`edges_json` (JSON Array):** Relaciones dirigidas entre nodos (`from`, `to`, `relation`).
- **Accessors Calculados:**
  - `nodes_count` y `edges_count`.
  - `healthy_count`: recuento de nodos con estado `healthy` o `running`.
  - `down_count`: recuento de nodos con estado `down`, `exited` o `dead`.
  - `warning_count`: recuento de nodos con estado `warning`, `restarting` o `paused`.

### 3.2 Mecanismo de Fallback Dinámico Automático
Si la tabla `infra_topology` no contiene un registro precalculado con clave `'current'`, el método `refreshTopology()` activa un **fallback dinámico** construyendo el grafo en tiempo real:
1. **Nodos Base de Infraestructura:** Host SRE Linux, contenedor `jg_dashboard`, contenedor de base de datos aislada `jg_dashboard_postgres` (:5434) y base de datos del agente `jg_agente_postgres`.
2. **Nodos Dinámicos de Agentes:** Consulta el catálogo Eloquent (`Agent::all()`), evaluando el estado de salud de cada agente (`isHealthy(90)`). Si el agente está activo y reportando latidos, se categoriza como `healthy`; si está en `PENDING`, como `warning`; si no reporta latidos, como `down`.
3. **Aristas de Red:** Mapea las relaciones `contains`, `database_connection` y `rest_api` inter-servicios.

### 3.3 Interacción y Slide-Over
Al hacer clic sobre cualquier nodo del mapa, el componente Livewire abre un panel lateral (*slide-over*) mostrando en detalle:
- Versión reportada del agente o imagen Docker.
- Timestamp ISO-8601 del último heartbeat.
- URL interna de la API (`api_url`).
- Estado de conectividad de red.

---

## 4. Registro y Deduplicación de Incidentes (`NotifiedIncident`)

Para mitigar la fatiga por alertas en los operadores SRE, el Dashboard incluye el modelo `App\Models\NotifiedIncident` (`notified_incidents`):
- **`incident_id` (PK):** Identificador determinista derivado del incidente de observabilidad (hash del error, componente y contexto).
- **`notified_at` (DateTime):** Momento exacto en que la notificación fue despachada hacia canales de guardia o administradores.
- **Propósito:** Actúa como filtro idempotente para impedir que el monitor emita alertas repetitivas por el mismo incidente no resuelto dentro de la ventana de silencio configurada.
- **Inspección Filament:** Gestionado a través de `NotifiedIncidentResource` para auditar cuándo y cuántas veces se alertó sobre una anomalía.

---

## 5. Widgets de Telemetría en Tiempo Real

El panel principal de Filament incorpora widgets con sondeo periódico automático (*auto-polling*):

| Widget | Intervalo | Función y Métricas Expuestas |
| :--- | :---: | :--- |
| **`AgentHealthWidget`** | `10s` | Sondea el runtime `jg_agente` invocando `GET /health` (con fallback a `/`). Muestra el estado del motor cognitivo y la proporción de agentes activos en red (`activos / totales`). |
| **`ActiveTasksOverview`** | `4s` | Desglose en tiempo real del ciclo de vida de tareas: en cola (`QUEUED`), en ejecución (`PROCESSING`), pendientes de autorización humana (`WAITING_APPROVAL`), y balance de completadas frente a fallidas. |
| **`CircuitBreakersStatus`** | Dinámico | Recuento total de interruptores de circuito y alerta destacada en rojo si existen circuitos abiertos (`tripped`). |

---

## 6. Comandos CLI Operacionales

El Control Plane dispone de comandos Artisan específicos para automatización, despliegue y mantenimiento preventivo:

### 6.1 Inicialización Idempotente del Administrador (`dashboard:init-admin`)
Permite provisionar el superadministrador inicial de forma no interactiva (ideal para pipelines CI/CD y despliegues en contenedores Docker):
```bash
# Aprovisionamiento estándar (respeta ADMIN_EMAIL, ADMIN_NAME, ADMIN_DEFAULT_PASSWORD del .env)
php artisan dashboard:init-admin

# Con parámetros explícitos
php artisan dashboard:init-admin --email=admin@empresa.com --name="SRE Lead" --password="ClaveRobustaSegura2026!"

# Forzar actualización de contraseña si el usuario ya existe (--force)
php artisan dashboard:init-admin --email=admin@empresa.com --password="NuevaClave2026!" --force
```

### 6.2 Monitor Periódico de Salud de Agentes (`agents:check-health`)
Inspecciona los agentes registrados en estado `ACTIVE` y detecta aquellos cuyo último latido supere el umbral de inactividad permitido:
```bash
# Ejecución manual con umbral por defecto (90 segundos)
php artisan agents:check-health

# Con umbral personalizado de tolerancia (ej. 120 segundos)
php artisan agents:check-health --timeout=120
```
- **Transición Automática:** Los agentes que superan el umbral pasan atómicamente a `OFFLINE`.
- **Auditoría Forense:** Genera un registro inmutable en `dashboard_audit_events` con el tipo `AGENT_OFFLINE`.
- **Programación:** Ejecutado automáticamente cada minuto en el scheduler de Laravel (`routes/console.php` / `app/Console/Kernel.php`):
  ```php
  Schedule::command('agents:check-health')->everyMinute();
  ```

---

## 7. Navegación

- [← Volver al Esquema de Base de Datos (05)](05_database_schema.md)
- [Volver al README Principal](../README.md)
