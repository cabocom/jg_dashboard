# Pruebas

Ejecución observada: `./vendor/bin/pest --compact` → **219 passed, 1043 assertions**.

Esto valida muchos servicios y endpoints, no la seguridad de la consola contra un usuario Viewer, la verificación de HMAC en el receptor, carreras de doble aprobación, ni un escenario real con FastAPI/PostgreSQL. El README afirma 35 pruebas; esa cifra está desactualizada frente a la suite observada.
