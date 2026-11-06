# 04. Protocolo Criptográfico Inter-Servicio (HMAC-SHA256) (`jg_dashboard`)

## 1. Contrato Criptográfico Canónico

La comunicación entre el plano de control (`jg_dashboard`) y el plano de ejecución (`jg_agente`) está blindada mediante firmas digitales canónicas computadas con **HMAC-SHA256**. Este esquema impide la falsificación de origen, la manipulación del cuerpo de la petición y ataques de repetición (*replay attacks*).

### 1.1 Estructura de la Cadena Canónica
La cadena canónica se construye concatenando deterministamente cuatro líneas separadas por saltos de línea POSIX (`\n`):

$$\text{CanonicalString} = \text{METHOD} + \text{"\textbackslash n"} + \text{PATH} + \text{"\textbackslash n"} + \text{TIMESTAMP} + \text{"\textbackslash n"} + \text{SHA256(RAW\_BODY)}$$

1. **`METHOD`:** Método HTTP en mayúsculas (`GET`, `POST`, `PUT`, `DELETE`).
2. **`PATH`:** Ruta URL normalizada con barra inicial (`/executions/trigger`, `/api/v1/agents/heartbeat`).
3. **`TIMESTAMP`:** Marca temporal en segundos (epoch UNIX) formateada como cadena de texto.
4. **`SHA256(RAW_BODY)`:** Hash SHA-256 en formato hexadecimal en minúsculas del cuerpo crudo en bytes. Si la petición no tiene cuerpo (ej. `GET`), se utiliza el hash de una cadena vacía:
   $$\text{SHA256}("") = \text{e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855}$$

### 1.2 Algoritmo de Firma
$$\text{Signature} = \text{HMAC-SHA256}(\text{CanonicalString}, \text{SecretKey})_{\text{hex}}$$

---

## 2. Cabeceras HTTP Obligatorias

Toda petición autenticada debe incluir:

| Cabecera | Tipo de Valor | Descripción y Propósito |
| :--- | :--- | :--- |
| **`X-Signature`** | Hexadecimal (64 caracteres) | Firma HMAC-SHA256 en minúsculas. |
| **`X-Timestamp`** | Entero numérico (epoch UNIX) | Marca temporal de emisión de la solicitud. |
| **`X-Agent-ID`** | UUID o Slug | Identificador unívoco del agente involucrado. |
| **`X-Correlation-ID`** | UUID v4 | Identificador de trazabilidad distribuida extremo a extremo. |

> [!IMPORTANT]
> **Corrección de Auditoría:**
> En documentos preliminares se mencionaban cabeceras como `X-Agent-Signature`, `X-Agent-Timestamp` y `X-Agent-Nonce`.
> **En la base de código actual, las cabeceras exactas e invariantes son `X-Signature` y `X-Timestamp`.** No existe cabecera de nonce arbitrario; la protección contra repeticiones se sustenta en la estricta ventana de tolerancia temporal de 300 segundos sobre el timestamp firmado.

---

## 3. Ventana Anti-Replay y Verificación en Tiempo Constante

### 3.1 Tolerancia Temporal
Tanto `VerifyHmacSignature` (PHP) como `verify_control_plane_auth` (Python) verifican el sesgo de reloj:
$$|\text{CurrentEpoch} - \text{RequestTimestamp}| \le 300 \text{ segundos (5 minutos)}$$
Si el desfase temporal supera los 300 segundos, la petición es rechazada de forma inmediata con código `401 Unauthorized`.

### 3.2 Comparación Segura de Firmas
Para mitigar ataques de canal lateral basados en temporización (*timing attacks*):
- En PHP (`jg_dashboard`): Se emplea `hash_equals(strtolower($expectedSignature), strtolower($receivedSignature))`.
- En Python (`jg_agente`): Se emplea `hmac.compare_digest(expected_signature.lower(), received_signature.strip().lower())`.

---

## 4. Dualidad Criptográfica del Secreto Permanente

En la base de datos del Dashboard (`dashboard_agents`), la credencial permanente del agente se persiste bajo dos formas complementarias:

```text
                           Credencial Permanente (sec_...)
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 ▼                                               ▼
         Hash SHA-256                                Cifrado Simétrico AES-256-CBC
   (permanent_secret_hash)                     (permanent_secret_encrypted)
                 │                                               │
                 ▼                                               ▼
    Llamadas Inbound del Agente                    Llamadas Outbound hacia el Agente
(Validación de Heartbeats / Webhooks)            (Firma de Peticiones en AgentHttpClient)
```

1. **`permanent_secret_hash` (Hash Unidireccional SHA-256):**
   - Utilizado en llamadas entrantes (*Inbound*) donde el agente envía su secreto en la cabecera `Authorization: Bearer <secret>` o mediante `X-Signature`.
   - Se compara de forma segura mediante `verifyPermanentSecret()` sin necesidad de desencriptar nada en base de datos.
2. **`permanent_secret_encrypted` (Cifrado Reversible de Aplicación):**
   - Cifrado mediante el cast `'encrypted'` de Eloquent (Laravel `Crypt`, AES-256-CBC con clave `APP_KEY`).
   - Permite al cliente `AgentHttpClient` recuperar la clave en texto plano en memoria binaria (`getDecryptedSecret()`) para calcular las firmas HMAC-SHA256 salientes en cada invocación hacia `jg_agente`.

---

## 5. Resiliencia de Transporte en `AgentHttpClient`

El cliente HTTP inter-servicios (`App\Services\Agents\AgentHttpClient`) implementa políticas de fiabilidad para comunicaciones sobre redes internas:
- **Timeouts Estrictos:** Timeout por defecto de 120 segundos para permitir la finalización de grafos cognitivos complejos y LLMs lentos.
- **Reintentos Automáticos con Backoff:** Reintenta automáticamente hasta 2 veces con intervalo de 200 ms ante excepciones de conexión (`ConnectionException`) o códigos de error HTTP de servidor (`>= 500`).
- **Mapeo Normalizado de Errores de Transporte:**
  - Si la conexión falla por timeout: retorna de forma normalizada HTTP `504 Gateway Timeout`.
  - Si el agente está inaccesible o la red rechaza la conexión: retorna HTTP `503 Service Unavailable`.
- **Doble Verificación de Salud (`checkHealth`):**
  - Invoca `GET /health`.
  - Si el agente objetivo ejecuta una versión previa que responde `404 Not Found`, ejecuta un fallback inmediato a `GET /` garantizando retrocompatibilidad transparente.

---

## 6. Actualización Dinámica de Topología en Heartbeats

Durante la ingesta de telemetría periódica (`POST /api/v1/agents/heartbeat`):
- Si el agente reporta dentro del objeto `metrics` una nueva dirección de red (`metrics.api_url`):
  ```json
  {
    "status": "ACTIVE",
    "metrics": {
      "cpu_percent": 12.4,
      "memory_mb": 256.1,
      "api_url": "http://10.0.1.45:8000"
    }
  }
  ```
- `AgentHeartbeatService` actualiza automáticamente el campo `metadata_json['api_url']` en PostgreSQL.
- **Ventaja Operativa:** Permite que agentes reubicados en clústeres Kubernetes o contenedores Docker con IPs dinámicas continúen recibiendo peticiones del Dashboard sin reconfiguración manual por parte del operador SRE.

---

## 7. Navegación

- [← 03. Gobernanza Human-In-The-Loop (HITL)](03_hitl_governance.md)
- [05. Esquema de Base de Datos →](05_database_schema.md)
