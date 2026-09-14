# Cierre de Issue: [DASH-SEC-01] JWT Secret con Fallback Hardcodeado Predecible en JwtService.php

## Resolución
Se ha subsanado la vulnerabilidad crítica de credenciales hardcodeadas por defecto en el servicio de tokens JWT de `jg_dashboard` (Work item [#47](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/47)):

1. **Eliminación del Fallback Estático (CWE-798 / CWE-1188):**
   - Se removió definitivamente la clave fija `'default_jg_dashboard_jwt_secret_32_bytes_long'` del constructor de `JwtService`.
   - Si no se encuentra configurada `auth.jwt.secret` ni `app.key`, el servicio lanza de forma inmediata una excepción crítica `\RuntimeException`.

2. **Validación Exhaustiva de Secreto Criptográfico:**
   - Se prohíben cadenas vacías, espacios en blanco y el uso del secreto estático por defecto conocido.
   - En caso de utilizar prefijos `base64:`, se valida que la decodificación sea válida y no resulte en una cadena vacía.

3. **Pruebas y Documentación:**
   - Añadidas pruebas unitarias en `tests/Unit/JwtAndAuthServiceTest.php` validando el lanzamiento de `RuntimeException` ante secretos no configurados, vacíos o que utilicen el fallback prohibido.
   - Documentada la variable `JWT_SECRET` en `.env.example`.
   - Suite completa de pruebas unitarias y de integración de `jg_dashboard` superada con 275 tests y 1330 aserciones pasadas al 100%.
   - Verificación global `check_unit.sh` superada exitosamente.
