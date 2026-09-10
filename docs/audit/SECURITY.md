# Seguridad

La vulnerabilidad dominante es DASH-001: las rutas de consola sólo requieren usuario autenticado y permiten provisioning, ejecución y decisiones HITL. Un Viewer autenticado puede realizar acciones de mayor privilegio.

`docker-compose.yml` activa `APP_DEBUG=true`, una `APP_KEY` conocida y una contraseña de administrador conocida como valores por defecto. Esto es inseguro si se despliega sin variables externas. El cliente HTTP conserva `internal_secret` conocido y registra cuerpos de respuestas de agente, potencialmente con prompts/resultados sensibles.

Filament incluye CSRF y autenticación propia, pero esto no compensa las rutas web custom sin autorización de rol.
