# [ISSUE-32] [jg_dashboard] fix(auth): Imponer política Fail-Closed en JwtBlacklistService, actualizar ApprovalRequest en AgentTaskTable y eliminar env() fuera de config/

- **ID Maestro QA:** `ISSUE-32`
- **Proyecto Destino:** `javiergalvez-ia/jg_dashboard`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::critical, security, bug`
- **GitLab Issue Remoto:** [jg_dashboard#62](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/62)

---

- **Severidad y Categoría:** `Critical` | `Seguridad` / `Fiabilidad-Bug`
- **Consenso de Auditores:** Detectado por: `claude-sonnet` (`JwtBlacklistService` y `env()`), `argon` (`AgentTaskTable` y `env()`)
- **Evidencia Exacta:**
  - `jg_dashboard/app/Services/Auth/JwtBlacklistService.php:68-70, 84-86, 108-110, 118-134`
  - `jg_dashboard/app/Filament/Resources/AgentTaskResource/Tables/AgentTaskTable.php:143-177`
  - `jg_dashboard/app/Services/Agents/AgentHttpClient.php:57, 110, 113, 116`, `AgentHmacSigner.php:94`, `HasAgentSecrets.php:70, 75, 79`, `TriggerAgentExecutionAction.php:84`, `InitAdminCommand.php:45, 49, 53`, `DatabaseSeeder.php:26-80`
  ```php
  // jg_dashboard/app/Services/Auth/JwtBlacklistService.php:131-133
      } catch (Throwable) {
          return false;
      }
  ```
- **Solución Técnica Propuesta:**
  1. En `JwtBlacklistService::checkDatabaseAndWarmCache` (`:118-134`), registrar `Log::error(...)` ante cualquier `Throwable` de BD y retornar `true` (**Fail-Closed: denegar token si no se puede verificar la lista de revocación**) o relanzar excepción de servicio no disponible (HTTP 503). Registrar también `Log::error` en `revokeJti()` (`:84-86`).
  2. En `AgentTaskTable::makeApproveHitlAction` (`:143-177`) y `makeRejectHitlAction` (`:195-236`), delegar directamente en `app(ApproveHitlRequestAction::class)->execute(...)` y `app(RejectHitlRequestAction::class)->execute(...)` para garantizar que `ApprovalRequest`, `AgentTask` y `AuditEvent` se actualicen atómicamente con `lockForUpdate()`.
  3. Reemplazar todas las llamadas a `env()` dentro de `app/` y `database/seeders/` por lecturas `config('agent....')` / `config('services....')`.
- **Criterios de Aceptación (Checklist verificable):**
  - [x] Test en `tests/Feature/JwtAndAuthServiceTest.php` verificando que ante caída de la tabla `jwt_revocations`, `isTokenRevoked` actúa en modo *Fail-Closed*.
  - [x] Aprobar una tarea desde `AgentTaskTable` deja el `ApprovalRequest` en estado `APPROVED` y registra el `AuditEvent`.
  - [x] `grep -rn "env(" app/` en `jg_dashboard` devuelve `0` resultados.
