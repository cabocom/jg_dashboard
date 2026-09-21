# Contexto del Proyecto: [DASH-CODE-03] Código Muerto Inseguro con Autenticación Inconsistente (DevOps60kApiClient)

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#54`
- **Código:** `DASH-CODE-03`
- **Severidad:** 🟢 BAJO
- **CWE:** CWE-561 (Dead Code), CWE-287 (Improper Authentication)
- **Componentes Afectados:**
  - [`jg_dashboard/app/Services/DevOps60kApiClient.php`](../../../app/Services/DevOps60kApiClient.php) (Eliminado)
  - [`jg_dashboard/tests/Feature/DevOps60kApiClientTest.php`](../../../tests/Feature/DevOps60kApiClientTest.php) (Eliminado)
  - [`jg_dashboard/README.md`](../../../README.md)

---

## Descripción de la Vulnerabilidad
Permanecía en el repositorio un cliente HTTP legacy (`DevOps60kApiClient`) correspondiente a la versión preliminar de la plataforma anterior al Control Plane actual.
Dicho cliente utilizaba un esquema de autenticación obsoleto consistente en transmitir credenciales estáticas en la cabecera `X-API-Key` y apuntaba a un endpoint monohost (`devops60k_app:8000`), divergiendo del estándar criptográfico HMAC-SHA256 establecido de manera uniforme en `AgentHttpClient`.
El cliente carecía de cualquier invocación o referencia en las rutas, controladores o comandos activos del sistema.

## Impacto en Seguridad
1. **Deuda Técnica y Confusión Arquitectónica:** Coexistencia de dos clientes HTTP para comunicarse con agentes con protocolos incompatibles (HMAC canónico vs API key estática en texto plano).
2. **Riesgo de Reintroducción:** Facilidad para que futuros desarrolladores o integraciones instanciaran inadvertidamente el cliente legacy, debilitando el perímetro criptográfico del sistema.
