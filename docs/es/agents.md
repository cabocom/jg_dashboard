# Ciclo de Vida y Gestión de Agentes: jg_dashboard

---

## 1. Ciclo de Vida del Agente

Un agente registrado en `jg_dashboard` transiciona a través de los siguientes estados:

```text
               ┌───────────────────────┐
               │        PENDING        │  (Aprovisionado por Admin con bootstrap_token)
               └───────────┬───────────┘
                           │ POST /api/v1/agents/register
                           ▼
               ┌───────────────────────┐
               │        ACTIVE         │  (Token canjeado por credencial permanente)
               └───────────┬───────────┘
                           │
             ┌─────────────┴─────────────┐
             │                           │
(Heartbeat cada 30s)             (Sin reporte > 90s)
             │                           │
             ▼                           ▼
     [Permanece ACTIVE]        ┌─────────────────┐
                               │     OFFLINE     │
                               └────────┬────────┘
                                        │
                         (Nuevo heartbeat recibido)
                                        │
                                        ▼
                                 [Vuelve a ACTIVE]
```

---

## 2. Ingesta de Heartbeats y Métricas de Salud

- **Frecuencia del Agente:** Cada 30 segundos, cada agente emite una petición autenticada con su `permanent_secret`:
  ```http
  POST /api/v1/agents/heartbeat
  X-Agent-ID: agent-core-01
  Authorization: Bearer <permanent_agent_secret>
  ```
- **Payload de Telemetría:** El agente reporta métricas de CPU, memoria, versión y componentes activos.
- **Acción en Dashboard:** Actualiza `last_heartbeat_at`, restaura el estado a `ACTIVE` (si estaba `OFFLINE`) y persiste las métricas en `metrics_json`.

---

## 3. Monitor de Salud en Background (`AgentHealthMonitorService`)

- **Comando Artisan:** `php artisan agents:check-health`
- **Programación:** Ejecutado cada minuto a través del Scheduler de Laravel (`Schedule::command('agents:check-health')->everyMinute()`).
- **Lógica de Detección:** Localiza agentes con estado `ACTIVE` cuyo `last_heartbeat_at` sea superior a 90 segundos de antigüedad y los transiciona automáticamente a `OFFLINE`, registrando el evento de auditoría correspondiente.

---

## 4. Inspección de Capacidades del Agente

A través de `GET /api/v1/agents/{agent_id}/capabilities`, el Dashboard interroga en vivo el endpoint `/capabilities` de `jg_agente`:
- Recupera el inventario de herramientas (`tools`), motores cognitivos (`engines`) y plugins activos.
- Actualiza de forma asíncrona el campo `capabilities_json` en la tabla `dashboard_agents`, garantizando una instantánea actualizada del ecosistema sin romper el principio de aislamiento de base de datos.
