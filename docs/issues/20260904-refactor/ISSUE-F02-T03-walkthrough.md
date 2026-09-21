# Walkthrough: ISSUE-F02-T03 — Modelos de base de datos para tokens de aprovisionamiento (bootstrap_tokens)

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el modelo y la migración para la gestión de tokens de aprovisionamiento (`bootstrap_tokens`) en `jg_dashboard`:

1. **Migración de Base de Datos ([database/migrations/2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php](../../../database/migrations/2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php)):**
   - Tabla `dashboard_bootstrap_tokens` con índice único para `token_hash` (SHA-256).
   - Clave foránea `agent_id` apuntando a `dashboard_agents.agent_id` con eliminación en cascada (`cascadeOnDelete`).
   - Campos de control: `expires_at` (indexado), `consumed` (booleano indexado, por defecto `false`), y `consumed_at` (timestamp nullable).

2. **Modelo Eloquent ([app/Models/BootstrapToken.php](../../../app/Models/BootstrapToken.php)):**
   - Atributos sensibles ocultos (`token_hash` en `$hidden`).
   - Casts de tipos automáticos (`expires_at`, `consumed_at`, `consumed`).
   - Métodos de inspección y validación: `isConsumed()`, `isExpired()`, `isValid()`.
   - Método atómico de consumo de un solo uso: `consume()` (rechaza re-consumo y tokens expirados).
   - Métodos criptográficos y generadores: `hashToken()`, `createTokenForAgent()`.
   - Scopes para consultas eficientes: `valid()`, `consumed()`, `expired()`.
   - Relación `agent()` hacia `App\Models\Agent`.

3. **Actualización de Modelo de Agente ([app/Models/Agent.php](../../../app/Models/Agent.php)):**
   - Adición de la relación `bootstrapTokens()` tipo `HasMany`.

4. **Suite de Pruebas Unitarias ([tests/Unit/BootstrapTokenModelTest.php](../../../tests/Unit/BootstrapTokenModelTest.php)):**
   - 7 pruebas exhaustivas en Pest PHP validando creación, serialización protegida, expiración TTL 24h, consumo de un solo uso, scopes de consulta, prevención de reutilización y generación criptográfica.

5. **Entorno de Pruebas SQLite ([tests/TestCase.php](../../../tests/TestCase.php)):**
   - Incorporación de la tabla `dashboard_bootstrap_tokens` en `setUpDatabaseSchema()`.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentModelTest
  ✓ agent creation and uuid generation                                   0.21s  
  ✓ agent hidden attributes in serialization                             0.18s  
  ✓ agent scopes                                                         0.01s  
  ✓ agent health check recency
  ✓ agent state transitions                                              0.18s  

   PASS  Tests\Unit\BootstrapTokenModelTest
  ✓ bootstrap token creation and relationship with agent                 0.09s  
  ✓ bootstrap token hides token_hash when serialized
  ✓ bootstrap token expiration detection with 24h TTL
  ✓ bootstrap token one-time consumption mechanics
  ✓ expired token cannot be consumed
  ✓ bootstrap token query scopes
  ✓ createTokenForAgent helper generates secure plaintext and persists…  0.01s  

   PASS  Tests\Unit\DatabaseSeederTest
  ✓ seeder throws exception when admin password is not set               0.01s  
  ✓ seeder creates admin user with explicit password                     0.01s  
  ✓ seeder updates existing admin user on password rotation              0.01s  

   PASS  Tests\Unit\EnvironmentConfigTest
  ✓ debug config resolution logic                                        0.01s  

   PASS  Tests\Unit\ModelMappingTest
  ✓ agent task casts and primary key                                     0.01s  
  ✓ agent task scopes and duration                                       0.01s  
  ✓ circuit breaker state casts and scopes
  ✓ notified incident mapping                                            0.01s  
  ✓ infra topology model mapping and counts

   PASS  Tests\Feature\AgentTaskResourceTest
  ✓ root redirects to admin                                              0.03s  
  ✓ unauthenticated user redirected to login                             0.01s  
  ✓ authenticated user can access dashboard                              0.06s  
  ✓ authenticated user can access agent tasks                            0.08s  
  ✓ unauthorized role user cannot access dashboard                       0.01s  
  ✓ user role helper methods
  ✓ hitl actions visibility based on task status                         0.03s  

   PASS  Tests\Feature\DevOps60kApiClientTest
  ✓ check health successful                                              0.02s  
  ✓ check health unreachable                                             0.22s  
  ✓ trigger task sends correct payload and headers                       0.02s  
  ✓ approve execution sends request to endpoint                          0.01s  
  ✓ reject execution sends request to endpoint                           0.01s  

   PASS  Tests\Feature\TopologyMapTest
  ✓ unauthenticated user redirected to login                             0.01s  
  ✓ authenticated user can access topology map                           0.02s  
  ✓ topology map renders database snapshot                               0.02s  
  ✓ livewire component selection and refresh                             0.02s  

  Tests:    37 passed (148 assertions)
  Duration: 1.43s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Estado de la Rama y Próximos Pasos

El issue se encuentra 100% verificado y listo para su cierre formal en GitLab.
Siguiente tarea planificada: **[ISSUE-F02-T04] Modelos de base de datos para solicitudes HITL y eventos de auditoría**.
