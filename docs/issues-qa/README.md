# Backlog QA & Seguridad Consolidado — `jg_dashboard`

**Repositorio GitLab:** `https://gitlab.com/javiergalvez-ia/jg_dashboard`  
**Responsable Asignado:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)  
**Total de Issues en `jg_dashboard`:** **3**

## Índice de Issues de Remediación

| ID | Severidad | Categoría | Título | Consenso Modelos | Archivo Local | Issue GitLab |
| :--- | :---: | :--- | :--- | :--- | :--- | :--- |
| `ISSUE-32` | **Critical** | `Seguridad / Fiabilidad-Bug` | fix(auth): Imponer política Fail-Closed en JwtBlacklistService, actualizar ApprovalRequest en AgentTaskTable y eliminar env() fuera de config/ | Detectado por: `claude-sonnet` (`JwtBlacklistService` y `env()`), `argon` (`AgentTaskTable` y `env()`) | [ISSUE-32_jwt_blacklist_fail_closed_agent_task_table_hitl_env.md](./ISSUE-32_jwt_blacklist_fail_closed_agent_task_table_hitl_env.md) | [#62](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/62) |
| `ISSUE-33` | **Major** | `DRY-Duplicación / Fiabilidad-Bug` | refactor(hitl): Eliminar clon de 150 líneas entre ApproveHitlRequestAction y RejectHitlRequestAction, eliminar N+1 HTTP en HitlApprovalFormatter y conectar CircuitBreakerService | Detectado por: `argon`, `hydrogen`, `claude-sonnet`, `flash-3.8` | [ISSUE-33_deduplicate_hitl_actions_remove_n1_wire_circuit_breaker.md](./ISSUE-33_deduplicate_hitl_actions_remove_n1_wire_circuit_breaker.md) | [#63](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/63) |
| `ISSUE-34` | **Critical** | `Complejidad-CodeSmell` | refactor(solid): Modularizar monolito public/js/console.js (2,747 líneas) y segregar las 7 clases PHP con >10 métodos públicos | Detectado por: `hydrogen` (`public/js/console.js`), `argon` (7 clases con >10 métodos públicos y violación SRP en `AgentController`) | [ISSUE-34_modularize_console_js_monolith_and_segregate_classes.md](./ISSUE-34_modularize_console_js_monolith_and_segregate_classes.md) | [#64](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/64) |
