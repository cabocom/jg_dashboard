# Análisis Técnico: ISSUE-F01-T03 — Pipeline de CI/CD para jg_dashboard

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F01-T03`  
> **Fase:** FASE 01 — Bootstrap, Documentación y Tooling  
> **Prioridad:** Medium  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F01-T01, ISSUE-F01-T02  

---

## 1. Objetivo del Issue

Configurar el pipeline de integración continua y despliegue automatizado (`.gitlab-ci.yml`) para el proyecto `jg_dashboard`, asegurando la ejecución periódica de la suite de pruebas unitarias y de integración sobre el runtime oficial PHP 8.3 / Pest, así como la construcción y publicación automatizada de la imagen Docker en el registro de contenedores de GitLab.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Definición de Etapas (`stages`):**
   - `test`: Ejecución automatizada de pruebas con reporte de resultados.
   - `build`: Construcción y etiquetado de la imagen Docker de producción.
2. **Job de Testing (`test:pest`):**
   - Ejecución sobre imagen base ligera Alpine Linux con extensiones requeridas para Laravel 11 y PostgreSQL.
   - Caché persistente para el directorio `vendor/` optimizando tiempos de pipeline.
   - Preparación de directorios `storage/` y `bootstrap/cache/` con permisos adecuados.
   - Ejecución sin intervención manual: `composer install --no-interaction --prefer-dist` y `./vendor/bin/pest`.
3. **Job de Construcción (`build:docker`):**
   - Utiliza Docker-in-Docker (`docker:24.0.7-dind`).
   - Autenticación segura mediante variables del registro (`REGISTRY_USER`, `REGISTRY_PASSWORD`, `REGISTRY_HOST`).
   - Construcción del Dockerfile multi-stage.
   - Publicación de etiquetas por rama, `:latest` en rama principal y tag de release si aplica.
4. **Criterios de Aceptación:**
   - `.gitlab-ci.yml` configurado y validado sin errores de sintaxis YAML.
   - Job de testing reproduce exactamente las pruebas locales ejecutadas mediante `./scripts/check.sh`.

---

## 3. Decisiones Técnicas y Diseño

- **Alineación con el Stack de jg_dashboard:** A diferencia de la descripción original que hacía referencia errónea a herramientas Python (`ruff`, `mypy`, `pytest`), el pipeline se alinea estrictamente con **PHP 8.3, Composer, Laravel 11 y Pest PHP**.
- **Reglas de Ejecución (`rules`):**
  - Pruebas en Merge Requests y pushes directos a ramas activas.
  - Exclusión de ejecución duplicada en Merge Requests abiertos.
  - Job de build reservado exclusivamente para ramas principales o commits con tag de versión.

---

## 4. Plan de Verificación

1. Validación de sintaxis YAML del pipeline.
2. Ejecución local de la suite de pruebas para asegurar paridad con el entorno CI:
   ```bash
   ./scripts/check.sh
   ```
