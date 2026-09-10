# Arquitectura observada

Laravel/Filament actúa como Control Plane con PostgreSQL independiente y `AgentHttpClient` por agente. La separación de datos está prevista en Compose con dos bases distintas. Sin embargo, conviven caminos heredados de `DevOps60kApiClient` que aún alimentan widgets y recursos Filament, con endpoint, esquema y clave por defecto distintos del contrato actual `jg_agente`.

La consola `/console` está detrás de `auth`, pero no de RBAC. Sus métodos delegan directamente en los servicios de registro, ejecución y HITL; por tanto la frontera de responsabilidades queda rota por el controlador web.
