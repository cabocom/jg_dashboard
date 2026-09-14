# Análisis Técnico: [DASH-SEC-01] JWT Secret con Fallback Hardcodeado Predecible en JwtService.php

## 1. Causa Raíz
En `app/Services/Auth/JwtService.php`, el operador coalescente `??` finalizaba en una cadena estática hardcodeada:
```php
$resolvedSecret = $secret 
    ?? config('auth.jwt.secret') 
    ?? config('app.key') 
    ?? 'default_jg_dashboard_jwt_secret_32_bytes_long';
```
Esta práctica infringe las directrices CWE-798 (Uso de Credenciales Hardcodeadas) y CWE-1188 (Inicialización Insegura de Recursos por Defecto). La presencia de un fallback predecible invalida las garantías criptográficas de integridad y autenticidad provistas por firmas HMAC-SHA256 en tokens JWT.

## 2. Puntos Débiles Adicionales Detectados
1. **Validación Inexistente:** No se verificaba si el valor resuelto era una cadena no vacía o si contenía solo espacios en blanco.
2. **Manejo Laxo de Prefijo `base64:`:** Si el secreto configurado era `base64:`, la decodificación resultaba en una cadena vacía y el código preservaba el literal `'base64:'` como secreto en vez de validar la validez del decodificado.
3. **Ausencia de Excepción Temprana (Fail-Fast):** En ausencia de configuración, el servicio operaba en modo inseguro en vez de detener inmediatamente el arranque de la aplicación o la ejecución de la petición.

## 3. Solución Implementada
1. **Eliminación del Fallback:** Retirado `'default_jg_dashboard_jwt_secret_32_bytes_long'` del encadenamiento coalescente.
2. **Validación Estricta:** Comprobación de que `$resolvedSecret` no sea nulo, no sea una cadena vacía ni contenga únicamente espacios en blanco, y no coincida con el valor predeterminado inseguro conocido.
3. **Lanzamiento de `RuntimeException`:** Si no hay un secreto configurado válido, se lanza inmediatamente:
   ```php
   throw new RuntimeException(
       'JWT secret is not configured or uses an insecure default. Please set JWT_SECRET or APP_KEY in your environment configuration.'
   );
   ```
4. **Decodificación Segura de `base64:`:** Tras remover el prefijo `base64:`, se valida que la decodificación sea exitosa y produzca una cadena no vacía; de lo contrario, se lanza una excepción descriptiva.
5. **Documentación en `.env.example`:** Documentado explícitamente `JWT_SECRET=` con advertencia CWE-798.
