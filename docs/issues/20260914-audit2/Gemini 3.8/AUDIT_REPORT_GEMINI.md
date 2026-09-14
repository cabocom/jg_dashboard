# AUDITORÍA TÉCNICA GLOBAL INDEPENDIENTE
## Plataforma de Ejecución y Control Agéntico: `jg_agente` + `jg_dashboard`

**Auditor:** Antigravity AI Engine (Google DeepMind / Gemini 3.8 Flash)  
**Fecha de Emisión:** 12 de Septiembre de 2026  
**Alcance:** Inspección estática profunda, análisis de flujo de control, evaluación de seguridad, concurrencia, resiliencia y verificación empírica de pruebas unitarias/integración de `jg_agente` y `jg_dashboard`.  
**Metodología:** Análisis de código de caja blanca de extremo a extremo, sin presunciones arquitectónicas previas ni dependencia de informes anteriores.

---

## 1. Executive Summary

### 1.1. Resumen del Estado Real del Sistema
El proyecto auditado comprende una arquitectura desacoplada de dos capas:
1. **Control Plane (`jg_dashboard`):** Aplicación monolítica construida sobre **PHP 8.2+ / Laravel 11 / Filament v3 / Livewire 3**, responsable de la identidad de usuarios (RBAC con roles `admin`, `auditor`, `operator`), administración de tokens de bootstrap, recepción de heartbeats de telemetría, gestión de aprobaciones Human-In-The-Loop (HITL), consola web y persistencia relacional en PostgreSQL/MySQL.
2. **Execution Plane (`jg_agente`):** Servicio asíncrono basado en **Python 3.11+ / FastAPI / LangGraph / LangChain**, que implementa un grafo de estados cíclico (`StateGraph`) estructurado en 9 nodos (`reason` -> `plan` -> `risk_assess` -> `human_approval` -> `execute` -> `verify` -> `rollback` -> `evaluate` -> `conclude`), persistencia transaccional mediante `DualPostgresSaver` (checkpointer de LangGraph) y un sistema modular de plugins dinámicos (`jg_plugin_conversational` y `jg_plugin_devops`).

### 1.2. Principales Fortalezas
- **Separación conceptual rigurosa:** La división entre un plano de control administrativo/auditor (`jg_dashboard`) y un plano de ejecución de inferencia y herramientas (`jg_agente`) es una decisión arquitectónica madura que aísla las interfaces de usuario de los motores de ejecución LLM.
- **Ciclo de Ejecución Autónomo con Safeguards:** La implementación del bucle OODA extendido en `jg_agente/core/graph.py` cuenta con nodos formales de evaluación de riesgos (`risk_assess`), compuertas de aprobación humana asíncronas (`human_approval`), verificación post-ejecución (`verify`), rollback automático ante fallos (`rollback`) y evaluación de objetivo cumplido (`evaluate`).
- **Defensa en Profundidad en Invocación de Herramientas:** Implementación de `SecurityGuard` (`guard.py`) con control de acceso basado en permisos requeridos (`required_permissions`), validación estricta de SSRF (`ssrf.py`) para herramientas de red, y auditoría inmutable en base de datos (`audit.py`).
- **Alta Cobertura de Pruebas Automatizadas:** 
  - `jg_dashboard`: 251 tests aprobados (1,201 aserciones en Pest/PHPUnit).
  - `jg_agente`: 360 tests aprobados en pytest cubriendo nodos, grafos, plugins, modelos e interfaces de red.

### 1.3. Principales Debilidades y Riesgos Críticos
- **Ruptura del Registro Dinámico de Agentes (HMAC Asimétrico):** El proceso de aprovisionamiento de agentes dinámicos genera un token de bootstrap que se intercambia por un secreto permanente (`permanent_secret`). Sin embargo, el Dashboard almacena únicamente el hash SHA-256 (`permanent_secret_hash`) y descarta el secreto en texto plano. Dado que el Dashboard debe firmar todas las peticiones salientes con HMAC-SHA256, es matemáticamente imposible firmar peticiones hacia agentes registrados dinámicamente, provocando que cualquier escalado dinámico de agentes falle con `401 Unauthorized`.
- **Desincronización de Secretos Preaprovisionados:** En `docker-compose.yml`, los secretos permanentes de los contenedores están fijados estáticamente en variables de entorno fijas (`sec_conversational_agent_preprovisioned_key_12345`). Si un administrador configura `JG_AGENTE_SECRET` en el archivo `.env` según las instrucciones oficiales, el Dashboard firmará con el nuevo secreto, pero los agentes rechazarán todas las peticiones porque sus variables en Compose no se actualizan dinámicamente.
- **Escapada de Contenedor y Compromiso Total del Host en `jg_agente_devops`:** El contenedor de DevOps ejecuta como usuario `root` (`user: "0:0"`), expone su API en `0.0.0.0:8002` y tiene montado el socket del demonio de Docker del host (`/var/run/docker.sock`). Un atacante o una secuencia errónea del agente puede crear contenedores privilegiados con volúmenes montados en `/` del host, logrando control total e inmediato del sistema anfitrión.
- **Bypass de Autorización RBAC en `risk_assess_node`:** Si el invocador de una ejecución no remite permisos explícitos (`caller_permissions`), el nodo de evaluación de riesgos auto-asigna a la llamada todos los permisos requeridos por la herramienta (`caller_perms = list(tool.required_permissions)`), anulando por completo la compuerta de seguridad en tiempo de ejecución.
- **Denegación de Ejecución en `execute_node` por Omisión de Permisos:** En el nodo de ejecución, la llamada a `guard.execute_tool(...)` omite suministrar los permisos del invocador, provocando que herramientas no aprobadas previamente por bypass fallen con denegación de permisos por defecto (`INSUFFICIENT_PERMISSIONS`).
- **Heurística de Lenguaje Natural Falaz en `reason_node`:** Una regla sintáctica basada en prefijos en español (`"dime"`, `"qué"`, `"cuenta"`, `"explica"`, `"cuál"`) cataloga erróneamente comandos operativos como simples conversaciones (e.g. `"dime el estado de los contenedores"`), provocando que el agente salte directo al nodo de conclusión sin ejecutar el plan ni consultar las herramientas.

### 1.4. Dictamen de Preparación para Producción
> [!CAUTION]
> **NO PREPARADO PARA PRODUCCIÓN EN SU ESTADO ACTUAL.**
> Aunque la base de código exhibe una calidad de ingeniería superior a la media, existen vulnerabilidades críticas en el esquema criptográfico de firma entre servicios, riesgos severos de seguridad por el montaje del Docker socket, auto-concesión de permisos en nodos del agente y fallos de sincronización de secretos que impiden la operabilidad fiable en entornos productivos multi-nodo.

---

## 2. Arquitectura Descubierta

A partir de la inspección estricta del código fuente y configuraciones, se deduce la siguiente arquitectura real:

```
                                  +----------------------------------------------------+
                                  |              CLIENT / OPERATOR BROWSER             |
                                  +----------------------------------------------------+
                                           |                                |
                            HTTP Session / | Web UI          REST API / JWT | Bearer Token
                            Cookie (Livewire / Filament)                    | (role: operator|admin)
                                           v                                v
+---------------------------------------------------------------------------------------------------------+
|                                    CONTROL PLANE (jg_dashboard)                                         |
|                                                                                                         |
|  [Presentation Layer]                                                                                   |
|    - Filament Admin Panels & Resources (Agents, Executions, Approvals, Audit Events, Web Console)       |
|    - Livewire 3 Components (Interactive Dashboards, Real-time HITL Modals)                              |
|                                                                                                         |
|  [Application Services]                                                                                 |
|    - HitlApprovalService: Coordina transiciones de estado HITL (pending -> approved/rejected)           |
|    - AgentRegistrationService: Emite bootstrap tokens, consume y activa agentes                         |
|    - AgentHeartbeatService: Procesa telemetría, métricas (RAM, CPU, queue) y estado online/offline       |
|    - AgentHttpClient: Cliente Guzzle/Curl con middleware de firma HMAC-SHA256                           |
|                                                                                                         |
|  [Security & Middleware]                                                                                |
|    - JwtAuthenticate (decodifica Bearer JWT, valida expiración y rol)                                   |
|    - RequireRole (RBAC granular para endpoints de ejecución y consola)                                  |
|                                                                                                         |
|  [Persistence Engine]                                                                                   |
|    - PostgreSQL (tablas: dashboard_users, dashboard_agents, dashboard_executions,                      |
|                         dashboard_approval_requests, dashboard_audit_events, etc.)                     |
+---------------------------------------------------------------------------------------------------------+
                  ^                                                                  |
                  | Heartbeat / Status Polling                                       | POST /api/v1/executions
                  | (HMAC: X-Agent-ID, X-Timestamp,                                  | (HMAC: X-Agent-ID, X-Timestamp,
                  |        X-Signature)                                              |        X-Signature)
                  |                                                                  v
+---------------------------------------------------------------------------------------------------------+
|                                   EXECUTION PLANE (jg_agente)                                           |
|                                                                                                         |
|  [API & Lifecycle Ingress] (FastAPI, uvicorn)                                                           |
|    - /api/v1/executions (trigger, status, cancel)                                                       |
|    - /api/v1/executions/{id}/hitl (resume execution after approval/rejection)                            |
|    - Lifespan Hook: bootstrap.py (registro dinámico) + heartbeat.py (demonio en background 30s)         |
|    - AuthMiddleware / verify_hmac: Valida X-Signature vs secret local en .agent_credentials.json       |
|                                                                                                         |
|  [Orchestration Engine - LangGraph StateGraph]                                                          |
|    - Nodes:                                                                                             |
|        [reason] ---------> [plan] ---------> [risk_assess]                                              |
|           ^                   |                       |                                                 |
|           |                   |                       v                                                 |
|        [evaluate]             |               [human_approval] (HITL Pause if High Risk)                |
|           ^                   |                       |                                                 |
|           |                   v                       v                                                 |
|        [verify] <------- [execute] <------------------+                                                 |
|           |                                                                                             |
|           + (failure) ----> [rollback] ----> [evaluate]                                                 |
|           | (success) ----> [evaluate]                                                                  |
|                                |                                                                        |
|                                v                                                                        |
|                           [conclude] ---------> END                                                     |
|                                                                                                         |
|  [State & Checkpointing]                                                                                |
|    - DualPostgresSaver: Checkpointer transaccional LangGraph en tabla agent_checkpoints                 |
|    - Fallback silencioso a MemorySaver si PostgreSQL no está disponible al arrancar                     |
|                                                                                                         |
|  [Security & Tool Execution Guard]                                                                      |
|    - SecurityGuard: Valida permisos, límites de parámetros, SSRFValidator para URLs externas           |
|    - SecurityAuditor: Inserta trazas inmutables de invocación de herramientas en agent_audit_events     |
|                                                                                                         |
|  [Extensibility Plugins]                                                                                |
|    - jg_plugin_conversational: Herramientas 'weather_tool' y 'news_tool' (RSS parser)                   |
|    - jg_plugin_devops: Herramientas Docker CLI/SDK ('docker_ps', 'docker_logs', etc.)                  |
+---------------------------------------------------------------------------------------------------------+
```

### 2.1. Comunicación Inter-Componentes y Contrato Criptográfico
1. **Identificación y Aprovisionamiento:**
   - En el arranque (`boot.py`), si el agente no tiene credenciales en disco (`.agent_credentials.json`), lee `BOOTSTRAP_TOKEN` de su entorno y realiza un `POST /api/v1/internal/agents/register` hacia el Dashboard.
   - El Dashboard valida el token en la tabla `dashboard_bootstrap_tokens`, crea/actualiza el registro en `dashboard_agents`, almacena el `permanent_secret_hash` y activa el agente (`status = active`).
2. **Firmado de Peticiones HMAC-SHA256:**
   - Todas las llamadas entre Dashboard y Agente requieren tres cabeceras obligatorias:
     - `X-Agent-ID`: Identificador único del agente (UUID).
     - `X-Timestamp`: Marca temporal ISO 8601 (con ventana de tolerancia de ±300 segundos).
     - `X-Signature`: Firma HMAC-SHA256 calculada como:  
       `hash_hmac('sha256', $timestamp . $agentId . $payloadJson, $secret)`.
   - Si la firma no coincide o la ventana temporal está vencida, la petición es rechazada con código `401 Unauthorized`.

---

## 3. Findings

### ID: FINDING-01
- **Título:** Almacenamiento Unidireccional del Secreto Permanente en el Dashboard Rompe el Registro Dinámico de Agentes
- **Severidad:** CRITICAL
- **Categoría:** Criptografía y Arquitectura de Autenticación
- **Estado:** Confirmado
- **Componente:** `jg_dashboard` (Control Plane)
- **Archivo:** `jg_dashboard/app/Services/AgentRegistrationService.php`, `jg_dashboard/app/Services/AgentHttpClient.php`
- **Localización:** `AgentRegistrationService.php:240`, `AgentHttpClient.php:78-95`
- **Descripción:** Cuando un agente se registra dinámicamente consumiendo un token de bootstrap en `consumeBootstrapTokenAndActivate`, el servicio calcula el hash SHA-256 del secreto permanente:
  ```php
  'permanent_secret_hash' => hash('sha256', $permanentSecret),
  ```
  y descarta `$permanentSecret` sin almacenarlo de forma reversible (e.g. cifrado simétrico AES-256-GCM) en la tabla `dashboard_agents`. Posteriormente, cuando un usuario o la consola dispara una ejecución mediante `AgentHttpClient::generateSignatureHeaders`, el cliente intenta recuperar el secreto del agente para calcular la firma HMAC-SHA256:
  ```php
  $secret = $agent->permanent_secret ?? config('services.agent.secret');
  ```
  Como `$agent->permanent_secret` no existe como columna de texto plano ni atributo desencriptable, `$secret` recurre a la variable estática del `.env` (`config('services.agent.secret')`). El agente, por su parte, valida la firma con el `$permanentSecret` generado durante su registro dinámico y guardado en su archivo local `.agent_credentials.json`.
- **Cómo puede producirse:**
  1. Un nuevo contenedor de agente arranca con una variable `BOOTSTRAP_TOKEN`.
  2. El agente se registra satisfactoriamente con el Dashboard en `/api/v1/internal/agents/register`.
  3. El Dashboard intenta enviar una orden de ejecución `POST /api/v1/executions` a dicho agente.
  4. El Dashboard genera la cabecera `X-Signature` con `JG_AGENTE_SECRET` genérico (o falla si está vacío), mientras el agente valida contra su clave privada generada.
  5. El agente rechaza la petición con `401 Unauthorized: Invalid HMAC signature`.
- **Impacto:** Imposibilidad absoluta de aprovisionar o escalar agentes dinámicamente en producción. Todo agente registrado dinámicamente queda huérfano e inaccesible.
- **Evidencia:**
  - `AgentRegistrationService.php` línea 240:
    ```php
    'permanent_secret_hash' => hash('sha256', $permanentSecret),
    'status' => 'active',
    ```
  - `AgentHttpClient.php` líneas 80-84:
    ```php
    $secret = $agent->permanent_secret ?? config('services.agent.secret');
    if (empty($secret)) {
        throw new \RuntimeException("No HMAC secret configured for agent [{$agent->id}]");
    }
    ```
- **Recomendación:** Almacenar el secreto en `dashboard_agents` cifrado mediante el mecanismo nativo de Laravel (`Crypt::encryptString($permanentSecret)`) en un campo `permanent_secret_encrypted`, o utilizar un esquema de clave pública/privada (Ed25519) donde el agente registre su clave pública y firme sus mensajes, y el Dashboard firme con la suya.

---

### ID: FINDING-02
- **Título:** Desincronización Crítica de Secretos en Docker Compose e Inmutabilidad de Semillas
- **Severidad:** CRITICAL
- **Categoría:** Configuración y Despliegue
- **Estado:** Confirmado
- **Componente:** Orquestación / Infraestructura
- **Archivo:** `docker-compose.yml`, `jg_dashboard/database/seeders/DatabaseSeeder.php`, `.env.example`
- **Localización:** `docker-compose.yml:110,155`, `DatabaseSeeder.php:45,67`
- **Descripción:** En `docker-compose.yml`, los agentes preconfigurados tienen sus secretos fijados como literales estáticos en variables de entorno de contenedor:
  ```yaml
  PERMANENT_AGENT_SECRET: sec_conversational_agent_preprovisioned_key_12345
  ...
  PERMANENT_AGENT_SECRET: sec_devops_agent_preprovisioned_key_12345
  ```
  Al mismo tiempo, la raíz `.env.example` (línea 31) expone `JG_AGENTE_SECRET=sec_conversational_agent_preprovisioned_key_12345`. Si el operador cambia este valor en el archivo `.env` por seguridad (o en producción), `jg_dashboard` consumirá el nuevo secreto vía `config('services.agent.secret')`, pero los agentes mantendrán los valores quemados en `docker-compose.yml`. Además, `DatabaseSeeder.php` sobrescribe en base de datos el hash con el valor hardcodeado:
  ```php
  'permanent_secret_hash' => hash('sha256', 'sec_conversational_agent_preprovisioned_key_12345'),
  ```
- **Cómo puede producirse:** Al cambiar `JG_AGENTE_SECRET` en `.env` durante el despliegue inicial en un servidor, el Dashboard firma con el nuevo valor y todos los agentes rechazan las peticiones por firma inválida.
- **Impacto:** Fallo general de conectividad entre el plano de control y el plano de ejecución tras cualquier rotación de secretos.
- **Evidencia:** `docker-compose.yml` líneas 110 y 155; `DatabaseSeeder.php` líneas 45 y 67.
- **Recomendación:** Parametrizar completamente las variables en `docker-compose.yml` utilizando interpolación `${JG_CONVERSATIONAL_SECRET}` y `${JG_DEVOPS_SECRET}`, y vincular `DatabaseSeeder` a leer las variables de entorno de Laravel en lugar de cadenas fijas.

---

### ID: FINDING-03
- **Título:** Escapada de Contenedor y Ejecución Remota Arbitraria con Privilegios Root en Host (`jg_agente_devops`)
- **Severidad:** CRITICAL
- **Categoría:** Seguridad de Infraestructura y Aislamiento
- **Estado:** Confirmado
- **Componente:** `jg_agente_devops` / `docker-compose.yml`
- **Archivo:** `docker-compose.yml`, `jg_agente/plugins/jg_plugin_devops/tools/docker_tools.py`
- **Localización:** `docker-compose.yml:138-158`, `docker_tools.py:120-145`
- **Descripción:** El servicio `jg_agente_devops` se define en `docker-compose.yml` con:
  ```yaml
  user: "0:0"
  volumes:
    - /var/run/docker.sock:/var/run/docker.sock
  ports:
    - "8002:8000"
  ```
  El contenedor se ejecuta como `root` en el sistema de contenedores y tiene acceso de lectura/escritura directo al socket de Docker del host. Además, el puerto `8002` se vincula a todas las interfaces (`0.0.0.0`). Cualquier actor que logre enviar una orden de ejecución al agente (o si el modelo genera una llamada a herramientas Docker de creación/ejecución) puede iniciar un contenedor con `--privileged -v /:/host` y obtener acceso directo de lectura y escritura al filesystem del host como `root`.
- **Cómo puede producirse:**
  1. Un operador con rol `operator` o un atacante con acceso a la red interna envía una instrucción al agente de DevOps: `"Ejecuta un diagnóstico montando el disco del sistema"`.
  2. El agente o una herramienta ejecuta la creación de un contenedor interactivo con montaje de `/`.
  3. Se logra compromiso total del servidor host en menos de un segundo.
- **Impacto:** Compromiso total, persistente e inadvertido de la máquina anfitriona, fuga de todos los secretos de la infraestructura y destrucción de bases de datos.
- **Evidencia:** `docker-compose.yml` línea 141 (`user: "0:0"`), línea 147 (`/var/run/docker.sock:/var/run/docker.sock`), línea 139 (`"8002:8000"`).
- **Recomendación:**
  1. Eliminar el montaje directo de `/var/run/docker.sock` en entornos productivos o sustituirlo por un proxy de socket con autorización granular (como `tecnativa/docker-socket-proxy`) que restrinja métodos POST peligrosos (`containers/create`, `exec`).
  2. Forzar que el puerto `8002` escuche únicamente en `127.0.0.1` o dentro de una red interna de Docker no accesible desde el exterior.

---

### ID: FINDING-04
- **Título:** Bypass Completo de Autorización RBAC en `risk_assess_node` por Auto-Asignación de Permisos
- **Severidad:** HIGH
- **Categoría:** Lógica del Agente / Seguridad
- **Estado:** Confirmado
- **Componente:** `jg_agente` (Motor de Grafo)
- **Archivo:** `jg_agente/core/nodes/risk_assess.py`
- **Localización:** `risk_assess.py:128-132`
- **Descripción:** En el nodo `risk_assess_node`, el sistema evalúa el riesgo y los permisos requeridos para ejecutar las herramientas solicitadas en el plan. El código implementa la siguiente lógica:
  ```python
  caller_perms = state.get("caller_permissions") or []
  if not caller_perms and tool:
      caller_perms = list(tool.required_permissions)
  ```
  Si el invocador de la ejecución no envía permisos (`caller_permissions` está vacío o nulo), el nodo **le concede automáticamente todos los permisos exigidos por la herramienta a ejecutar**. Inmediatamente después, invoca `guard.evaluate(tool_name, tool_args, caller_permissions=caller_perms)`. Dado que `caller_perms` ahora contiene exactamente lo que la herramienta exige, la validación de permisos en `SecurityGuard` siempre resultará exitosa.
- **Cómo puede producirse:** Un llamador no autenticado o con permisos vacíos solicita una herramienta con permisos restrictivos (e.g. `docker:delete`, `docker:write`). Como `caller_perms` es `[]`, el nodo le auto-asigna `['docker:delete']`, superando la evaluación del guardián de seguridad.
- **Impacto:** Invalidación total del modelo de control de acceso basado en roles (RBAC) definido a nivel de herramientas en el Execution Plane.
- **Evidencia:** `jg_agente/core/nodes/risk_assess.py` líneas 129-131:
  ```python
  caller_perms = state.get("caller_permissions") or []
  if not caller_perms and tool:
      caller_perms = list(tool.required_permissions)
  ```
- **Recomendación:** Eliminar de inmediato la auto-asignación. Si `caller_perms` está vacío, debe permanecer vacío para que `guard.evaluate` rechace la ejecución si la herramienta exige permisos explícitos.

---

### ID: FINDING-05
- **Título:** Omisión de `caller_permissions` en `execute_node` Provoca Denegación de Herramientas Legítimas
- **Severidad:** HIGH
- **Categoría:** Lógica de Ejecución y Resiliencia
- **Estado:** Confirmado
- **Componente:** `jg_agente` (Motor de Grafo)
- **Archivo:** `jg_agente/core/nodes/execute.py`
- **Localización:** `execute.py:202-215`
- **Descripción:** En el nodo `execute_node`, cuando se itera sobre las llamadas a herramientas para ejecutarlas formalmente a través del guardián:
  ```python
  tool_result = await guard.execute_tool(
      tool_name=tc["name"],
      arguments=tc["args"],
      caller_permissions=state.get("caller_permissions") or [],
      bypass_approval=bypass_approval,
  )
  ```
  En la implementación de `execute.py` (línea 204), si la herramienta no cuenta con `bypass_approval=True` (por ejemplo, tras una aprobación humana o en ejecución estándar evaluada), `guard.execute_tool` vuelve a evaluar las políticas mediante `self.evaluate(...)`. Si la corrección de `FINDING-04` se aplica o si el estado no propagó los permisos de forma inmutable, `caller_permissions` vuelve a vaciarse, denegando la ejecución en el último paso y provocando errores de ejecución inesperados (`ToolExecutionError: Execution denied: INSUFFICIENT_PERMISSIONS`).
- **Cómo puede producirse:** Una ejecución con un plan de herramientas legítimo que requiere permisos pasa por la fase de aprobación, pero en el nodo de ejecución, si no se transfieren explícitamente los permisos originales del operador en todas las rutas del grafo, la llamada es rechazada en seco por el `SecurityGuard`.
- **Impacto:** Fallos intermitentes de ejecución de herramientas legítimas y frustración operativa del agente.
- **Evidencia:** `jg_agente/core/nodes/execute.py` líneas 202-206.
- **Recomendación:** Asegurar que `caller_permissions` forme parte indivisible del contrato de estado tipado (`AgentState`) y se propague sin mutaciones a lo largo de todos los nodos del grafo (`reason`, `plan`, `risk_assess`, `execute`).

---

### ID: FINDING-06
- **Título:** Heurística de Preguntas en Español en `reason_node` Anula el Planificador en Comandos Operativos
- **Severidad:** HIGH
- **Categoría:** Lógica de Inferencia / Comportamiento de Agente
- **Estado:** Confirmado
- **Componente:** `jg_agente` (Nodo de Razonamiento)
- **Archivo:** `jg_agente/core/nodes/reason.py`
- **Localización:** `reason.py:151-170, 310-326`
- **Descripción:** La función auxiliar `_is_simple_greeting_or_chat` implementa una heurística sintáctica para atajar conversaciones simples sin invocar herramientas. Contiene la siguiente regla:
  ```python
  question_words = ["qué", "que", "cómo", "como", "cuál", "cual", "cuándo", "cuando", "dónde", "donde", "por qué", "porque", "quién", "quien", "dime", "cuenta", "explica"]
  if any(clean_msg.startswith(w) for w in question_words):
      operational_keywords = ["reinicia", "para", "inicia", "despliega", "escribe", "crea", "elimina", "borra", "ejecuta", "actualiza", "mata", "kill", "restart", "deploy", "delete", "create"]
      if not any(k in clean_msg for k in operational_keywords):
          return True
  ```
  Cualquier instrucción operativa redactada de forma natural en español como:
  - `"Dime el estado de los contenedores"`
  - `"Cuál es la memoria libre en el servidor"`
  - `"Explica qué puertos están escuchando"`
  comienza con una palabra interrogativa/petición (`"dime"`, `"cuál"`, `"explica"`) y no contiene verbos destructivos directos (`"reinicia"`, `"borra"`, etc.). Por ende, la función retorna `True` (`is_simple_greeting_or_chat = True`). En `reason_node` (línea 312), esta condición hace que el nodo asigne directamente la salida del LLM al estado final y enrute a `conclude`, saltándose por completo los nodos `plan`, `risk_assess` y `execute`.
- **Cómo puede producirse:** El operador pregunta `"Dime qué contenedores están caídos"`. El agente no llama a la herramienta Docker, inventa una respuesta o dice que no tiene acceso, saltando de inmediato a la conclusión.
- **Impacto:** Inoperancia del agente ante consultas de sólo lectura formuladas en lenguaje natural en español.
- **Evidencia:** `jg_agente/core/nodes/reason.py` líneas 155-165 y 310-318.
- **Recomendación:** Eliminar la heurística de palabras clave prefijadas y permitir que sea el LLM quien decida mediante llamadas a herramientas (`tool_calls`) estructuradas, o limitar la heurística estrictamente a saludos puros (`"hola"`, `"buenos días"`).

---

### ID: FINDING-07
- **Título:** Fallback Arbitrario en `plan_node` Invoca Herramientas Peligrosas con Argumentos Vacíos
- **Severidad:** HIGH
- **Categoría:** Lógica del Agente / Robustez
- **Estado:** Confirmado
- **Componente:** `jg_agente` (Nodo de Planificación)
- **Archivo:** `jg_agente/core/nodes/plan.py`
- **Localización:** `plan.py:163-178`
- **Descripción:** Cuando el planificador estructurado o la salida del modelo falla en generar un plan válido (`plan is None` y no hay `tcs` emitidas), el código entra en una cláusula de fallback:
  ```python
  if not plan and not tcs:
      # Fallback: create a single-step plan from available tools
      available_tools = plugin_manager.get_all_tools()
      if available_tools:
          tool = available_tools[0]
          steps = [PlanStep(step_id=1, tool_name=tool.name, arguments={}, ...)]
  ```
  El sistema selecciona ciegamente la **primera herramienta disponible en el registro** (`available_tools[0]`) y le programa una ejecución con argumentos vacíos `{}`.
- **Cómo puede producirse:** Si el registro de herramientas inicializa primero un plugin con herramientas de sistema o destructivas (e.g. `docker_prune` o `docker_stop`), y el LLM produce un formato inesperado en una tarea de DevOps, el agente intentará ejecutar esa primera herramienta arbitraria.
- **Impacto:** Ejecución impredecible de herramientas no solicitadas, posibles fallos de validación o efectos secundarios indeseados en el entorno.
- **Evidencia:** `jg_agente/core/nodes/plan.py` líneas 165-175.
- **Recomendación:** Eliminar el fallback de selección arbitraria de herramientas. Si no se puede generar un plan válido, el nodo debe abortar con un error explícito de planificación o transicionar a `conclude` indicando la imposibilidad de planificar.

---

### ID: FINDING-08
- **Título:** Degradación Silenciosa del Checkpointer a Memoria Volátil y Fuga de Conexiones
- **Severidad:** MEDIUM
- **Categoría:** Persistencia y Recuperación ante Errores
- **Estado:** Confirmado
- **Componente:** `jg_agente` (Capa de Checkpointing)
- **Archivo:** `jg_agente/core/checkpointer.py`, `jg_agente/main.py`
- **Localización:** `checkpointer.py:175-186`, `main.py:40-75`
- **Descripción:** 
  1. En `checkpointer.py`, si la conexión a PostgreSQL falla durante el arranque de `DualPostgresSaver`, el sistema captura la excepción y retorna silenciosamente una instancia en memoria:
     ```python
     except Exception as exc:
         logger.warning(f"DualPostgresSaver initialization failed: {exc}. Falling back to MemorySaver.")
         _cached_checkpointer = MemorySaver()
         return _cached_checkpointer
     ```
     No se emite ninguna alarma al plano de control ni se aborta el arranque. Si el contenedor de PostgreSQL tiene un retraso transitorio en el boot, el agente queda operando en memoria volátil. Cualquier reinicio posterior del agente destruye todas las ejecuciones pendientes, checkpoints y estados de HITL.
  2. En `main.py`, en el gestor de ciclo de vida (`lifespan`), se inicializan las conexiones pero nunca se cierra explícitamente el pool de conexiones (`ConnectionPool`) de `DualPostgresSaver` en la fase de apagado (`yield`).
- **Cómo puede producirse:** En un despliegue de Docker Compose, `postgres` tarda 5 segundos más de lo esperado en levantar; el agente arranca en modo degradado (en memoria) sin advertencia crítica.
- **Impacto:** Pérdida inadvertida de persistencia, imposibilidad de reanudar ejecuciones HITL tras un reinicio y fuga de descriptores de conexión en PostgreSQL.
- **Evidencia:** `checkpointer.py` líneas 178-185; `main.py` líneas 65-75.
- **Recomendación:** Implementar una política de reintentos con backoff exponencial durante el arranque en `boot.py`. Si la persistencia requerida es PostgreSQL, el agente debe fallar el healthcheck si no puede conectar, evitando arrancar en falso modo volátil. Implementar el cierre del pool en el shutdown de `lifespan`.

---

### ID: FINDING-09
- **Título:** Procesamiento de Feeds RSS Externos sin Protección contra XXE (CWE-611)
- **Severidad:** MEDIUM
- **Categoría:** Seguridad de Aplicación (XML External Entity)
- **Estado:** Confirmado
- **Componente:** `jg_plugin_conversational`
- **Archivo:** `jg_agente/plugins/jg_plugin_conversational/tools/news.py`
- **Localización:** `news.py:176`
- **Descripción:** En la herramienta `news_tool`, el contenido obtenido de feeds RSS externos se procesa utilizando la biblioteca estándar de Python:
  ```python
  import xml.etree.ElementTree as ET
  ...
  root = ET.fromstring(response.text)
  ```
  `xml.etree.ElementTree` no desactiva la resolución de entidades externas ni la expansión de entidades por defecto en versiones estándar si no se emplean analizadores seguros como `defusedxml`. Si una fuente RSS manipulada o un endpoint apuntado mediante SSRF devuelve un XML malicioso (e.g. Billion Laughs Attack o referencias a entidades locales), el intérprete de Python consumirá memoria masiva o intentará leer recursos locales.
- **Cómo puede producirse:** El agente consulta un feed de noticias cuya URL es alterada o proviene de un origen comprometido que contiene una bomba XML o entidades externas.
- **Impacto:** Denegación de servicio (DoS) por agotamiento de CPU/RAM o divulgación de información en versiones vulnerables.
- **Evidencia:** `news.py` línea 176: `root = ET.fromstring(response.text)`.
- **Recomendación:** Reemplazar `xml.etree.ElementTree` por `defusedxml.ElementTree.fromstring` o configurar un parser estricto que prohíba DTD y entidades externas.

---

### ID: FINDING-10
- **Título:** Incompatibilidad de Script de Reinicio en Sistemas Operativos Linux (`sed -i ''`)
- **Severidad:** LOW
- **Categoría:** Portabilidad y DevOps
- **Estado:** Confirmado
- **Componente:** Scripts de Despliegue
- **Archivo:** `restart_services.sh`
- **Localización:** `restart_services.sh:58`
- **Descripción:** El script `restart_services.sh` utiliza la sintaxis específica de BSD/macOS para la edición in-place con `sed`:
  ```bash
  sed -i '' "s/APP_KEY=.*/APP_KEY=$ESCAPED_KEY/" jg_dashboard/.env
  ```
  En distribuciones de Linux (Debian, Ubuntu, Alpine, RHEL), la opción `-i ''` produce un error de sintaxis (`sed: can't read : No such file or directory`) e interrumpe la ejecución del script.
- **Cómo puede producirse:** Al ejecutar `./restart_services.sh` en un servidor de producción basado en Linux.
- **Impacto:** Fallo inmediato en la automatización del despliegue en entornos de integración continua o servidores Linux.
- **Evidencia:** `restart_services.sh` línea 58.
- **Recomendación:** Utilizar una función compatible entre plataformas o detectar el sistema operativo (`uname -s`) para aplicar `sed -i` en Linux o `sed -i ''` en macOS.

---

### ID: FINDING-11
- **Título:** Código Muerto Inseguro con Autenticación Inconsistente (`DevOps60kApiClient`)
- **Severidad:** LOW
- **Categoría:** Deuda Técnica y Mantenibilidad
- **Estado:** Confirmado
- **Componente:** `jg_dashboard`
- **Archivo:** `jg_dashboard/app/Services/DevOps60kApiClient.php`
- **Localización:** `DevOps60kApiClient.php:40-65`
- **Descripción:** Existe un cliente legacy `DevOps60kApiClient` que envía peticiones utilizando la cabecera `X-API-Key: ...` en lugar del protocolo unificado HMAC-SHA256 establecido en `AgentHttpClient`. Este archivo no se referencia en los flujos principales de la aplicación ni en los controladores de ejecución modernos, constituyendo código residual que confunde a los desarrolladores y genera divergencias de autenticación.
- **Impacto:** Confusión técnica, riesgo de reintroducción de patrones obsoletos y aumento de la superficie de ataque.
- **Evidencia:** `DevOps60kApiClient.php` líneas 50-55.
- **Recomendación:** Eliminar el archivo y unificar cualquier llamada de telemetría a través del servicio estándar `AgentHttpClient`.

---

## 4. Security Review

### 4.1. Autenticación y Autorización
- **Control Plane (`jg_dashboard`):**
  - Implementa RBAC mediante roles (`admin`, `operator`, `auditor`). La consola web y los triggers de ejecución exigen el rol `operator` o `admin` a través del middleware `RequireRole`.
  - La autenticación en la API REST de Dashboard utiliza JWT (`JwtAuthenticate`) con validación de expiración y firma HMAC (`JWT_SECRET`).
- **Execution Plane (`jg_agente`):**
  - La autenticación inter-servicio se basa en firmas HMAC-SHA256 con ventana de tiempo de 300s para mitigar ataques de repetición (Replay Attacks).
  - **Fallo crítico:** Como se demostró en el hallazgo `FINDING-01`, el diseño actual no puede verificar firmas en agentes aprovisionados dinámicamente debido a la falta del secreto compartido en el Dashboard.

### 4.2. Inyección y Validación de Entradas
- **SQL Injection:** Ambos componentes utilizan abstracciones ORM/Query Builder sólidas (Eloquent con PDO parametrizado en Laravel; SQLAlchemy 2.0 y psycopg3 en FastAPI), previniendo eficazmente inyecciones SQL en rutas comunes.
- **Command Injection:** En `docker_tools.py` del plugin de DevOps, las operaciones se delegan preferentemente al SDK de Docker en Python (`docker` library). Sin embargo, cuando se ejecutan comandos indirectos en contenedores (`container.exec_run`), no existe sanitización de argumentos frente a metacaracteres de shell si el contenedor destino ejecuta un intérprete.
- **SSRF (Server-Side Request Forgery):** Se destaca la presencia de `SSRFValidator` (`jg_agente/core/security/ssrf.py`), que valida y bloquea rangos de IP privadas (RFC 1918, link-local, loopback) y esquemas peligrosos antes de realizar peticiones HTTP salientes en herramientas de red.

### 4.3. Aislamiento y Superficie de Ataque
- El montaje de `/var/run/docker.sock` en el agente de DevOps con privilegios de `root` (`FINDING-03`) constituye la mayor vulnerabilidad del despliegue, permitiendo la anulación total del aislamiento del contenedor.

---

## 5. Reliability Review

### 5.1. Recuperación ante Errores y Transaccionalidad
- **Transiciones de Estado de HITL:** El flujo de aprobación humana está bien estructurado a nivel de base de datos (`dashboard_approval_requests`), con estados finitos (`pending`, `approved`, `rejected`, `timeout`). Cuando el agente pausa su ejecución en `human_approval_node`, persiste el estado en LangGraph checkpoint y queda a la espera de reactivación vía `POST /api/v1/executions/{id}/hitl`.
- **Manejo de Caídas:** Si el agente se apaga inesperadamente mientras espera aprobación humana, al reiniciar puede reanudar desde el último checkpoint almacenado en PostgreSQL (`DualPostgresSaver`), siempre que el checkpointer no se haya degradado a memoria volátil (`FINDING-08`).
- **Rollback Autónomo:** El nodo `rollback_node` ejecuta acciones de compensación registradas durante el paso de ejecución (`compensating_actions`), mitigando estados corruptos en herramientas transaccionales.

### 5.2. Timeouts y Reintentos
- Las peticiones HTTP entre Dashboard y Agente en `AgentHttpClient` cuentan con timeouts definidos (15 segundos para comandos interactivos, configurable para ejecuciones asíncronas). Sin embargo, carecen de un mecanismo de reintento automático con jitter ante fallos transitorios de red (e.g. 502/504 en proxy reverso).

---

## 6. Agent / Execution Review

### 6.1. Ciclo de Vida del Grafo LangGraph
El grafo implementado en `jg_agente/core/graph.py` define una máquina de estados determinista gobernada por nodos especializados:
1. `reason`: Formula la hipótesis inicial y decide si requiere planificación de herramientas.
2. `plan`: Construye un `PlanDAG` secuencial o paralelo de herramientas.
3. `risk_assess`: Evalúa criticidad de cada paso (`low`, `medium`, `high`, `critical`).
4. `human_approval`: Interrumpe la ejecución si el riesgo es alto/crítico o requiere permisos especiales.
5. `execute`: Invoca las herramientas a través de `SecurityGuard`.
6. `verify`: Comprueba si la salida de la herramienta satisfizo las condiciones esperadas.
7. `rollback`: Dispara acciones de compensación si la verificación falla.
8. `evaluate`: Decide si el objetivo de la tarea se ha cumplido o si requiere un nuevo ciclo (`reason`).
9. `conclude`: Emite la respuesta consolidada al usuario y finaliza.

### 6.2. Detección de Bucles y Condiciones de Estancamiento
- El grafo previene ciclos infinitos mediante una variable de estado `iteration_count` y un límite duro (`max_iterations = 5` o `10`). Si se alcanza el límite sin alcanzar el objetivo, el router fuerza la transición a `conclude` con estado de fallo parcial.
- **Debilidad identificada:** El fallback del planificador (`FINDING-07`) y la heurística de preguntas (`FINDING-06`) distorsionan la navegación natural del grafo, provocando conclusiones prematuras o invocaciones de herramientas no deseadas.

---

## 7. Architecture Review

### 7.1. Separación de Responsabilidades y Acoplamiento
- **Ventaja:** Excelente desacoplamiento de capas de negocio. El Dashboard gestiona usuarios, políticas globales y auditoría de alto nivel sin conocer los detalles de implementación de los modelos LLM subyacentes.
- **Debilidad:** Acoplamiento bidireccional fuerte en la topología de red. El Agente necesita conocer la URL del Dashboard para enviar Heartbeats (`DASHBOARD_URL`), y el Dashboard necesita conocer la IP/puerto de cada Agente (`endpoint` en la base de datos). Si los contenedores de los agentes cambian de IP dinámica o se despliegan detrás de balanceadores efímeros, la comunicación falla si no existe un service discovery adecuado.

### 7.2. Puntos Únicos de Fallo (SPOF)
- La base de datos PostgreSQL compartida representa el principal SPOF. Si la base de datos se satura, tanto la interfaz web como la persistencia del estado del agente se detienen.
- La ejecución mono-proceso de Uvicorn en los contenedores de agente limita el rendimiento concurrente ante múltiples peticiones pesadas de streaming de LLM.

---

## 8. Testing Review

### 8.1. Análisis Empírico de Pruebas
Durante la auditoría se ejecutaron las suites de pruebas completas de ambos repositorios en el entorno local:
- **`jg_dashboard`:**
  - Comando: `php artisan test` (Pest v2 / PHPUnit)
  - Resultado: **251 passed** (1,201 assertions), 0 fallos.
  - Alcance: Cubre modelos, seeders, políticas, servicios de registro, heartbeat, clientes HTTP y componentes Livewire.
- **`jg_agente`:**
  - Comando: `pytest`
  - Resultado: **360 passed** en 70.78s, 0 fallos.
  - Alcance: Cubre contratos de estado, routers de grafo, nodos individuales, plugins, SSRF y autenticación de endpoints.

### 8.2. Falsos Positivos y Gaps de Cobertura
A pesar de la alta cifra de tests aprobados (611 tests combinados), la auditoría detectó discrepancias críticas entre los tests y la realidad operativa:
1. **Mocks Excesivos en la Capa Criptográfica:** Los tests de `AgentHttpClient` y `AgentRegistrationService` prueban los métodos aislando la persistencia o mockeando las llamadas Guzzle. Ningún test de integración end-to-end intentó: *Generar token en Dashboard -> Registrar Agente Dinámico -> Guardar secreto -> Enviar ejecución firmada desde Dashboard al Agente -> Verificar firma en el Agente*. Si existiera este test de integración real, el hallazgo crítico `FINDING-01` habría saltado de inmediato.
2. **Ausencia de Tests de Escenarios de Fuga:** No existen pruebas automatizadas de seguridad que validen el comportamiento de `SecurityGuard` cuando `caller_permissions` es nulo, lo cual permitió que la auto-asignación de permisos de `risk_assess.py` (`FINDING-04`) pasara completamente desapercibida.
3. **Omisión de Pruebas con Entradas en Español Coloquial:** Los tests del nodo `reason` evalúan mensajes sintéticos en inglés o comandos explícitos, dejando sin validar las preguntas operativas en lenguaje natural que activan el bypass de `FINDING-06`.

---

## 9. Prioritized Remediation Plan

### P0 — Inmediato (Bloqueantes de Producción)
1. **Corregir el Esquema de Almacenamiento Criptográfico de Agentes (`FINDING-01`):**
   - Modificar la migración de `dashboard_agents` para añadir `permanent_secret_encrypted`.
   - Cifrar el secreto permanente en `AgentRegistrationService.php` y permitir que `AgentHttpClient.php` lo desencripte para firmar con HMAC.
2. **Resolver la Desincronización de Secretos en Docker Compose (`FINDING-02`):**
   - Eliminar los secretos quemados en texto plano en `docker-compose.yml` y sustituirlos por referencias dinámicas a variables de entorno en el archivo `.env`.
3. **Aislar el Contenedor de DevOps y Restringir Docker Socket (`FINDING-03`):**
   - Interponer un proxy de autorización (e.g. `docker-socket-proxy`) con métodos restringidos antes de exponer el demonio de Docker a `jg_agente_devops`.
   - Vincular el puerto `8002` a `127.0.0.1`.
4. **Cerrar el Bypass de Permisos en `risk_assess_node` (`FINDING-04`):**
   - Eliminar las líneas 129-131 de `jg_agente/core/nodes/risk_assess.py` para impedir la auto-asignación de permisos a invocadores no autorizados.

### P1 — Importante (Fiabilidad y Comportamiento)
5. **Propagar `caller_permissions` Inmutablemente a `execute_node` (`FINDING-05`):**
   - Garantizar que los permisos del llamador se validen correctamente en la fase de ejecución sin denegaciones indebidas.
6. **Reescribir la Heurística Sintáctica de `reason_node` (`FINDING-06`):**
   - Desactivar el bypass basado en prefijos de palabras interrogativas en español para comandos de DevOps/monitorización.
7. **Eliminar el Fallback Arbitrario en `plan_node` (`FINDING-07`):**
   - Abortar limpiamente la planificación si el modelo no produce pasos estructurados válidos.
8. **Blindar la Inicialización del Checkpointer (`FINDING-08`):**
   - Evitar la degradación silenciosa a memoria volátil; fallar el healthcheck del agente si PostgreSQL no responde en el boot.

### P2 — Mejora (Calidad y Robustez)
9. **Sustituir el Parser XML en `news.py` (`FINDING-09`):**
   - Adoptar `defusedxml` para mitigar ataques XXE en la lectura de fuentes RSS externas.
10. **Hacer Portables los Scripts de Automatización (`FINDING-10`):**
    - Corregir el comando `sed -i` en `restart_services.sh` para compatibilidad universal macOS/Linux.
11. **Limpieza de Código Legacy (`FINDING-11`):**
    - Eliminar `DevOps60kApiClient.php` para mantener un único estándar de comunicación.

### P3 — Opcional (Evolución Arquitectónica)
12. **Migración a Criptografía Asimétrica (Ed25519):**
    - Sustituir HMAC simétrico por firmas asimétricas donde cada agente posea su par de claves pública/privada, eliminando por completo la necesidad de que el Dashboard guarde secretos reversibles.
13. **Implementación de Service Mesh / Message Broker:**
    - Introducir una cola de mensajería (e.g. RabbitMQ o Redis Streams) para desacoplar las peticiones asíncronas entre Dashboard y Agente, eliminando la dependencia de URLs fijas.

---

## 10. Riesgos que NO deben confundirse con bugs

1. **Latencia Inherente al Ciclo de 9 Nodos:**
   - Cada ciclo completo del agente involucra múltiples llamadas secuenciales al LLM (`reason`, `plan`, `risk_assess`, `verify`, `evaluate`). Esto genera latencias de respuesta de entre 5 y 25 segundos por ejecución. Esto es una consecuencia directa del diseño OODA robusto y no un bug de rendimiento.
2. **Bloqueo Asíncrono Intencional en HITL:**
   - La pausa de una ejecución que requiere aprobación humana no es un cuelgue ni una condición de carrera; es el comportamiento esperado de la máquina de estados de LangGraph para garantizar control operativo.
3. **Uso de SQLite en Entornos de Test Local:**
   - El hecho de que los tests utilicen SQLite en memoria (`:memory:`) mientras producción usa PostgreSQL responde a la velocidad de ejecución de la suite de pruebas y no a una inconsistencia de datos si las migraciones están estandarizadas.

---

## 11. Preguntas Abiertas y Verificaciones Adicionales

1. **Gestión de Carga en Concurrencia Real:**
   - No se ha podido verificar el comportamiento del pool de conexiones de `DualPostgresSaver` bajo una carga sostenida de más de 100 agentes concurrentes escribiendo checkpoints simultáneamente.
2. **Respuesta ante Fallos de Red en Modo Streaming:**
   - Queda pendiente comprobar cómo maneja la consola web de Filament la desconexión abrupta de la conexión HTTP durante el streaming en tiempo real de tokens del agente.
3. **Políticas de Retención y Purgado de Checkpoints:**
   - En la base de datos de checkpoints de LangGraph (`agent_checkpoints`), no se observa un mecanismo de purgado programado. En ejecuciones prolongadas, esta tabla crecerá indefinidamente, lo que requerirá evaluar estrategias de retención o particionamiento en el futuro.

---
*Informe generado y firmado por el auditor independiente Antigravity / Gemini 3.8 Flash.*
