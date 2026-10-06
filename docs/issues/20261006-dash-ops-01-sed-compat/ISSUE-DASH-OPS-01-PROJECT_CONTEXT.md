# Project Context: [DASH-OPS-01] Incompatibilidad del Script restart_services.sh en Sistemas Linux

## Resumen de Scripts de Operación y Automatización
Los scripts bajo `scripts/core/` gestionan el ciclo de vida, despliegue, reinicio y sincronización de variables de entorno para todo el ecosistema.

### Compatibilidad Multiplataforma:
- **Función Portable `run_sed_i`:** Resuelve la diferencia histórica entre GNU `sed` (Linux / servidores de producción) y BSD `sed` (macOS), garantizando que las tareas de edición in-place de archivos `.env` se ejecuten sin errores en cualquier distribución o sistema operativo.
