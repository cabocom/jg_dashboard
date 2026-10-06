# Análisis Técnico: [DASH-OPS-01] Incompatibilidad del Script restart_services.sh en Sistemas Linux

## 1. Contexto y Problema
En `scripts/core/restart_services.sh`, la edición in-place de variables en archivos `.env` utilizaba la sintaxis de `sed` específica de BSD/macOS:
```bash
sed -i '' "s|^GROQ_API_KEY=.*|GROQ_API_KEY=${EFFECTIVE_GROQ_KEY}|" "$AGENTE_DIR/.env"
```

### Impacto:
- En macOS (BSD `sed`), la opción `-i ''` indica edición in-place sin generar archivo de respaldo (*backup*).
- En sistemas Linux (GNU `sed`), el argumento vacío `''` no es interpretado como extensión de respaldo vacía, sino como el primer nombre de archivo a editar. Esto provoca un error inmediato:
  `sed: can't read : No such file or directory`
- Como consecuencia, los despliegues automatizados y ejecuciones en servidores Linux (Ubuntu, Debian, pipelines CI/CD) fallaban al ejecutar el reinicio y sincronización del ecosistema.

## 2. Solución Diseñada
1. **Función Helper Portable (`run_sed_i`):**
   - Implementación de una función auxiliar que detecta el motor de `sed` activo mediante `sed --version >/dev/null 2>&1`:
     - Si tiene éxito (código de salida 0), se trata de GNU `sed` (Linux) y se invoca `sed -i "$pattern" "$file"`.
     - Si falla (código de salida 1 en BSD `sed` de macOS), se invoca `sed -i '' "$pattern" "$file"`.
2. **Aplicación Universal:**
   - Sustitución de todas las llamadas directas `sed -i ''` en `scripts/core/restart_services.sh` por `run_sed_i`.
   - Aplicación análoga en `scripts/core/install_prod.sh` para garantizar portabilidad cruzada completa macOS ↔ Linux.

## 3. Criterios de Aceptación
- [x] Función `run_sed_i` implementada de forma no invasiva y portable.
- [x] Ejecución sin errores en macOS (BSD sed).
- [x] Ejecución sin errores en Linux (GNU sed verificado en contenedor Ubuntu).
- [x] Documentación técnica generada.
