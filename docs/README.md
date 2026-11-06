# Documentación Técnica — `jg_dashboard`

Control Plane (Plano de Control Central, Gobernanza HITL y Pasarela de I/O) del ecosistema `javiergalvez-ia`.

---

## 🌐 Estructura Multilingüe

La documentación técnica del Control Plane está organizada por directorios de idioma (ISO 639-1):

- 🇪🇸 **[Español (Fuente Canónica / SSOT)](es/)**:
  - [01. Arquitectura del Control Plane](es/01_control_plane.md) — Separación de planos, pipeline de servicios y PostgreSQL aislado.
  - [02. Control de Acceso y Roles (RBAC)](es/02_rbac_tenancy.md) — Modelo de roles `admin`, `operator` y `viewer`, JwtService y auditoría inmutable.
  - [03. Gobernanza Human-In-The-Loop (HITL)](es/03_hitl_governance.md) — Lock pesimista, reconciliación ante fallos 404/409 y rescate de solicitudes huérfanas.
  - [04. Protocolo Criptográfico Inter-Servicio](es/04_hmac_handshake.md) — Firmas HMAC-SHA256, dualidad de almacenamiento de secretos y resiliencia HTTP.
  - [05. Esquema de Base de Datos](es/05_database_schema.md) — Diccionario de datos de las 9 entidades relacionales de `jg_dashboard`.
  - [06. Observabilidad y Resiliencia](es/06_observability_resilience.md) — Circuit Breakers, Mapa Topológico, Deduplicación de Incidentes y CLI SRE.
  - [07. Pasarela de Adaptadores y Webhooks](es/07_adapters_webhooks.md) — Normalizador de estímulos externos (`InputNormalizer`), formateo adaptativo (`ClientOutputFormatter`) y controlador webhook.

---

## Compilación Unificada

Para compilar la documentación consolidada de `jg_dashboard` para IAs externas y desarrolladores:
```bash
# Compilar versión canónica (español)
./workspace.sh docs jg_dashboard --locale=es

# Compilar versión en inglés (con fallback automático)
./workspace.sh docs jg_dashboard --locale=en

# Compilar todos los idiomas
./workspace.sh docs jg_dashboard --locale=all
```
