# Project Context: [DASH-CODE-01] Implementación Manual de Criptografía JWT sin Rotación ni Blacklist

## Resumen del Componente de Autenticación JWT (`jg_dashboard`)
El servicio de autenticación JWT de `jg_dashboard` gestiona el ciclo de vida de tokens de acceso para operadores, administradores y clientes de la API REST.

### Arquitectura Actualizada:
- **Librería Criptográfica:** `firebase/php-jwt` (^7.2.1).
- **Algoritmo:** HMAC-SHA256 (`HS256`) con requisito estricto de secreto $\ge 256$ bits (32 bytes).
- **Rotación:** Soportada vía cabecera `kid` y mapa de claves `auth.jwt.keys`.
- **Revocación:** Soportada vía identificador único `jti` (UUIDv4) con almacenamiento en tabla PostgreSQL `dashboard_jwt_revocations` y capa de cache L1.
- **Flujo de Logout:** `POST /api/v1/auth/logout` revoca activamente el token bearer utilizado en la sesión.
