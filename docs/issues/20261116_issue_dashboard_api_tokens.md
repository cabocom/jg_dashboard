# Issue 07: [jg_dashboard] Feat: Migración y modelo para tokens de API de clientes externos (dashboard_api_tokens)

- **Título:** `[jg_dashboard] Feat: Migración y modelo para tokens de API de clientes externos (dashboard_api_tokens)`
- **Prioridad y Fase:** Alta | Fase 3: Dashboard Servidor MCP
- **Dependencias:** Ninguna (Base de autenticación para servidor MCP)
- **Contexto Técnico y Archivos Afectados:**
  - Hallazgos del informe: `H-16` (Falta de autenticación y tokens revocables para clientes externos no basados en navegador).
  - Regla estricta 2: Autenticación mediante tokens de aplicación con prefijo `jgmcp_`, hash SHA-256 en base de datos, expiración configurable y scopes (`mcp:read`, `mcp:execute`).
  - Archivos a crear/modificar:
    - `jg_dashboard/database/migrations/2026_11_16_000001_create_dashboard_api_tokens_table.php` (Crear)
    - `jg_dashboard/app/Models/ApiToken.php` (Crear)
    - `jg_dashboard/app/Http/Middleware/AuthenticateApiToken.php` (Crear)
    - `jg_dashboard/bootstrap/app.php` o `app/Http/Kernel.php` (Modificar para registrar alias de middleware)
    - `jg_dashboard/tests/Feature/ApiTokenAuthenticationTest.php` (Crear)

---

## Especificación Técnica

### 1. Migración `dashboard_api_tokens`
```php
Schema::create('dashboard_api_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('name', 128);
    $table->string('token_hash', 64)->unique(); // SHA-256 del token plano
    $table->string('token_prefix', 16)->default('jgmcp_');
    $table->json('scopes_json')->nullable(); // ['mcp:read', 'mcp:execute']
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->boolean('revoked')->default(false);
    $table->timestamps();

    $table->index(['token_hash', 'revoked']);
});
```

### 2. Modelo `App\Models\ApiToken`
- Atributos rellenables: `user_id`, `name`, `token_hash`, `token_prefix`, `scopes_json`, `last_used_at`, `expires_at`, `revoked`.
- Métodos auxiliares:
  - `generate(string $name, ?User $user = null, array $scopes = ['mcp:read', 'mcp:execute'], ?DateTimeInterface $expiresAt = null): array{token: string, model: ApiToken}`:
    - Genera token plano criptográfico con prefijo: `jgmcp_` + 40 caracteres aleatorios (`Str::random(40)`).
    - Almacena `hash('sha256', $plainToken)` en `token_hash`.
    - Retorna el token plano únicamente en el momento de creación.
  - `isValid(): bool`: Verifica que `!$this->revoked` y `($this->expires_at === null || $this->expires_at->isFuture())`.
  - `hasScope(string $scope): bool`: Comprueba coincidencia exacta o comodín `*`.

### 3. Middleware `AuthenticateApiToken`
- Extraer token de cabecera `Authorization: Bearer <token>`.
- Calcular `hash('sha256', $token)`.
- Consultar `ApiToken::where('token_hash', $hash)->first()`.
- Validar operatividad (`isValid()`).
- Actualizar `last_used_at = now()`.
- Resolver el usuario asociado (o usuario de sistema/servicio) y fijarlo en `auth()->setUser($user)`.
- Inyectar el modelo `api_token` en `$request->attributes->set('api_token', $tokenModel)`.

---

## Criterios de Aceptación (Checklist Verificable)

- [ ] Migración ejecutada con éxito en entorno de test SQLite/PostgreSQL.
- [ ] Test unitario que valida que el token se almacena exclusivamente en formato hash SHA-256 y nunca en texto plano.
- [ ] Test de middleware que rechaza peticiones sin cabecera `Authorization` con HTTP 401.
- [ ] Test que rechaza tokens expirados o revocados con HTTP 401.
- [ ] Test que permite el paso con token válido y asocia el `User` correspondiente al contenedor de autenticación.
- [ ] Suite ejecutada con `php artisan test --filter=ApiTokenAuthenticationTest` pasando al 100%.
