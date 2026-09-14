# Walkthrough: [DASH-SEC-01] JWT Secret con Fallback Hardcodeado Predecible en JwtService.php

## Resumen de Cambios

### 1. `jg_dashboard/app/Services/Auth/JwtService.php`
- Se eliminó el valor hardcodeado por defecto `'default_jg_dashboard_jwt_secret_32_bytes_long'`.
- Se implementó validación rigurosa de `$resolvedSecret` asegurando que no sea nulo, vacío, espacios en blanco o el fallback inseguro prohibido.
- Se añadió validación estricta de la decodificación en caso de utilizar prefijos `base64:`.
- Se arroja `\RuntimeException` descriptiva si el secreto no está configurado o es inválido.

### 2. `jg_dashboard/.env.example`
- Se documentó la variable de entorno `JWT_SECRET=` indicando que es requerida o derivada de `APP_KEY`, y que los fallbacks estáticos están estrictamente prohibidos.

### 3. `jg_dashboard/tests/Unit/JwtAndAuthServiceTest.php`
- Se incorporaron 5 nuevos casos de prueba unitaria:
  - Intento de instanciación sin secretos configurados arroja `RuntimeException`.
  - Intento de instanciación con secreto vacío o espacios en blanco arroja `RuntimeException`.
  - Intento de instanciación con la clave hardcodeada prohibida arroja `RuntimeException`.
  - Intento de instanciación con `base64:` vacío arroja `RuntimeException`.
  - Fallback legítimo a `app.key` cuando `auth.jwt.secret` no está definido funciona correctamente y genera tokens válidos.

---

## Verificación de Pruebas

```bash
# Tests unitarios específicos de JwtService
cd jg_dashboard && ./vendor/bin/pest tests/Unit/JwtAndAuthServiceTest.php
# Resultado: 12 passed (45 assertions)

# Suite completa de Pest en jg_dashboard
cd jg_dashboard && ./vendor/bin/pest
# Resultado: 275 passed (1330 assertions)
```
