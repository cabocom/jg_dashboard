# Guía de Implementación y Walkthrough: [ISSUE-F06-T01] Cliente HTTP interno para comunicación con jg_agente

**Issue ID:** ISSUE-F06-T01  
**Fase:** FASE 06  
**Fecha:** 5 de septiembre de 2026  

---

## 1. Resumen de Cambios

Se implementó el cliente HTTP interno (`AgentHttpClient`) en `jg_dashboard` para comunicar de forma segura, autenticada y tolerante a fallos el Control Plane con los agentes del Execution Plane (`jg_agente`).

### Componentes Creados y Modificados:
1. `config/agent.php`: Archivo de configuración centralizada de conexión hacia `jg_agente`.
2. `app/Exceptions/AgentHttpException.php`: Clases de excepción de dominio (`AgentHttpException`, `AgentConnectionException`, `AgentTimeoutException`).
3. `app/Services/Agents/AgentHttpClient.php`: Servicio de cliente HTTP con firmado HMAC, reintentos y mapeo de endpoints.
4. `tests/Unit/AgentHttpClientTest.php`: Suite unitaria con 12 tests automatizados usando `Http::fake()`.
5. `README.md` & `PROJECT_CONTEXT.md`: Actualización del roadmap de Fase 06 y contexto arquitectónico a la versión 3.8.0.

---

## 2. Ejemplos de Uso

### 2.1 Uso Básico desde un Controlador o Servicio
```php
use App\Services\Agents\AgentHttpClient;

$client = new AgentHttpClient();

// Chequeo de salud del agente
$health = $client->checkHealth();
if ($health['success']) {
    // Agente operativo
}

// Disparo de una ejecución cognitiva (AgentInput)
$response = $client->triggerExecution([
    'user_prompt' => 'Obtener reporte meteorológico de Madrid',
    'user_id' => 'usr_operator_123',
    'thread_id' => 'th_cluster_01',
]);
```

### 2.2 Uso Enlazado a un Agente Registrado en BD (`Agent`)
```php
use App\Models\Agent;
use App\Services\Agents\AgentHttpClient;

$agent = Agent::find('00000000-0000-0000-0000-000000000001');

// El factory resuelve dinámicamente la URL desde metadata_json y asigna cabecera X-Agent-ID
$client = AgentHttpClient::forAgent($agent);

// Consultar estado de una ejecución
$status = $client->getExecutionStatus('thread_xyz_123');

// Aprobar una ejecución en HITL
$approval = $client->approveExecution('thread_xyz_123', [
    'approver_id' => 'usr_admin',
    'reason' => 'Plan de ejecución validado y sin riesgos',
]);
```

---

## 3. Verificación Automatizada

La verificación se realizó mediante la suite de pruebas automatizadas:
```bash
./scripts/check.sh
```

Resultados obtenidos:
- 139 tests pasando satisfactoriamente.
- 0 advertencias o errores en la integración con Laravel 11.
