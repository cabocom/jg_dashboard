# Walkthrough: ISSUE-F03-T04 — Script de inicialización para creación del usuario administrador inicial

> **Estado:** COMPLETADO Y VALIDADO  
> **Fecha:** 5 de septiembre de 2026  
> **Comando de Verificación:** `./scripts/check.sh`  

---

## 1. Resumen de Cambios Realizados

Se ha implementado el comando CLI y el script de arranque para la inicialización idempotente del usuario administrador en `jg_dashboard`:

1. **Comando Artisan ([app/Console/Commands/InitAdminCommand.php](../../../app/Console/Commands/InitAdminCommand.php)):**
   - Comando `dashboard:init-admin` con opciones `--email`, `--password`, `--name`, `--force`.
   - Soporte para variables de entorno `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_DEFAULT_PASSWORD`.
   - Creación segura con hash Bcrypt/Argon2id.
   - Comportamiento idempotente: omite la creación si el usuario ya existe y solo rota credenciales si se suministra el flag `--force`.

2. **Script de Shell ([scripts/init_admin.sh](../../../scripts/init_admin.sh)):**
   - Script ejecutable con permisos `+x` para aprovisionamiento directo en contenedores Docker y flujos de automatización.

3. **Seeder de Base de Datos ([database/seeders/DatabaseSeeder.php](../../../database/seeders/DatabaseSeeder.php)):**
   - Actualizado para resolver variables de entorno opcionales sin alterar la compatibilidad previa.

4. **Suite de Pruebas Automatizadas ([tests/Feature/InitAdminCommandTest.php](../../../tests/Feature/InitAdminCommandTest.php)):**
   - 5 pruebas en Pest PHP verificando:
     - Creación mediante argumentos CLI.
     - Idempotencia en ejecuciones repetidas.
     - Actualización con opción `--force`.
     - Falla ante falta de contraseña.
     - Uso correcto de variables de entorno cuando no se especifican opciones.

---

## 2. Resultados de la Verificación Automatizada

Ejecución de `./scripts/check.sh`:

```text
========================================
🔍 Ejecutando verificación de jg_dashboard...
========================================

1️⃣  [Pest] Suite de pruebas PHP y Filament...

   PASS  Tests\Unit\AgentModelTest
   PASS  Tests\Unit\ApprovalAndAuditModelTest
   PASS  Tests\Unit\BootstrapTokenModelTest
   PASS  Tests\Unit\DatabaseSeederTest
   PASS  Tests\Unit\EnvironmentConfigTest
   PASS  Tests\Unit\JwtAndAuthServiceTest
   PASS  Tests\Unit\ModelMappingTest
   PASS  Tests\Feature\AgentTaskResourceTest
   PASS  Tests\Feature\AuthApiTest

   PASS  Tests\Feature\InitAdminCommandTest
  ✓ dashboard:init-admin creates new admin user with cli options         0.01s  
  ✓ dashboard:init-admin is idempotent and skips creation if user exist… 0.01s  
  ✓ dashboard:init-admin with force option updates existing admin passw… 0.01s  
  ✓ dashboard:init-admin fails when password is not provided in cli or…  0.01s  
  ✓ dashboard:init-admin uses environment variables when options are om… 0.01s  

   PASS  Tests\Feature\DevOps60kApiClientTest
   PASS  Tests\Feature\RbacMiddlewareTest
   PASS  Tests\Feature\TopologyMapTest

  Tests:    73 passed (322 assertions)
  Duration: 1.86s

========================================
✅ ¡Todas las comprobaciones de jg_dashboard pasaron con éxito!
========================================
```

---

## 3. Conclusión de la Fase 03

Con la entrega de este issue, la **FASE 03 (Autenticación de Usuarios y RBAC)** queda completada al 100%. Todos los requerimientos de seguridad, emisión de JWT, middlewares RBAC (ADMIN, OPERATOR, VIEWER), endpoints de auth y aprovisionamiento inicial de administradores se encuentran verificados y operativos.
