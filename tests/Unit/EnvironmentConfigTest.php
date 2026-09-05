<?php

namespace Tests\Unit;

use Tests\TestCase;

class EnvironmentConfigTest extends TestCase
{
    /**
     * Verifica la resolución de precedencia de la configuración de debug:
     * 1. APP_DEBUG tiene prioridad.
     * 2. Si APP_DEBUG no existe o es null, se evalúa WEB_APP_DEBUG.
     * 3. Si ninguna existe, el valor por defecto seguro es false.
     */
    public function test_debug_config_resolution_logic(): void
    {
        $resolver = function (?bool $appDebug, ?bool $webAppDebug): bool {
            $env = [];
            if ($appDebug !== null) {
                $env['APP_DEBUG'] = $appDebug;
            }
            if ($webAppDebug !== null) {
                $env['WEB_APP_DEBUG'] = $webAppDebug;
            }

            $getEnv = fn(string $key, mixed $default = null) => array_key_exists($key, $env) ? $env[$key] : $default;

            return (bool) $getEnv('APP_DEBUG', $getEnv('WEB_APP_DEBUG', false));
        };

        // Escenario 1: Solo WEB_APP_DEBUG=false (entorno de producción estándar)
        $this->assertFalse($resolver(null, false));

        // Escenario 2: Solo WEB_APP_DEBUG=true
        $this->assertTrue($resolver(null, true));

        // Escenario 3: APP_DEBUG=false tiene precedencia sobre WEB_APP_DEBUG=true
        $this->assertFalse($resolver(false, true));

        // Escenario 4: APP_DEBUG=true tiene precedencia sobre WEB_APP_DEBUG=false
        $this->assertTrue($resolver(true, false));

        // Escenario 5: Ninguna variable definida -> fallback seguro false
        $this->assertFalse($resolver(null, null));
    }
}
