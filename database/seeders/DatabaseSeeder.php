<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = config('agent.admin_default_password');

        if (empty($adminPassword)) {
            throw new RuntimeException(
                "Seguridad (CWE-521): La variable de entorno ADMIN_DEFAULT_PASSWORD es obligatoria para inicializar " .
                "el usuario administrador. Configure ADMIN_DEFAULT_PASSWORD en su entorno o archivo .env."
            );
        }

        $adminEmail = config('agent.admin_email', 'admin@javiergalvez.test.local');
        $adminName = config('agent.admin_name', 'Administrador');

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]
        );

        // Seed del Agente principal (agnóstico de plugin)
        $agentSecret = \Illuminate\Support\Facades\Config::get('agent.internal_secret');

        if (empty($agentSecret)) {
            $agentSecret = 'secret_dev_token_12345';
        }

        $agentId = config('agent.default_agent_id', 'a0000000-0000-0000-0000-000000000001');
        $agentName = config('agent.agent_name', 'Agente Conversacional');
        $agentApiUrl = config('agent.base_url', 'http://jg_agente:8000');

        \App\Models\Agent::updateOrCreate(
            ['agent_id' => $agentId],
            [
                'name' => $agentName,
                'version' => '0.1.0',
                'status' => 'ACTIVE',
                'permanent_secret_hash' => hash('sha256', $agentSecret),
                'permanent_secret_encrypted' => $agentSecret,
                'metadata_json' => [
                    'api_url' => $agentApiUrl,
                ],
                'capabilities_json' => [
                    'tools' => [],
                    'engines' => [],
                ],
                'last_heartbeat_at' => now(),
            ]
        );

        // Agente DevOps opcional solo si se solicita explícitamente en orquestaciones multi-agente
        $devopsSecret = \Illuminate\Support\Facades\Config::get('agent.devops_secret');

        if (!empty($devopsSecret)) {
            \App\Models\Agent::updateOrCreate(
                ['agent_id' => 'a0000000-0000-0000-0000-000000000002'],
                [
                    'name' => 'Agente DevOps Docker Raw',
                    'version' => '0.1.0',
                    'status' => 'ACTIVE',
                    'permanent_secret_hash' => hash('sha256', $devopsSecret),
                    'permanent_secret_encrypted' => $devopsSecret,
                    'metadata_json' => [
                        'api_url' => 'http://jg_agente_devops:8000',
                        'plugin' => 'jg_plugin_devops_docker_raw',
                    ],
                    'capabilities_json' => [
                        'plugins' => [
                            [
                                'name' => 'jg_plugin_devops_docker_raw',
                                'version' => '1.0.0',
                                'description' => 'Plugin satélite desacoplado para ejecución de comandos Docker Raw con defensa en profundidad, parser determinista, HITL y Saga Rollback.',
                                'tags' => ['devops', 'docker', 'raw', 'security', 'hitl', 'saga'],
                            ],
                        ],
                        'tools' => [
                            ['name' => 'docker_read', 'description' => 'Ejecución segura de comandos Docker de solo lectura (ps, stats, inspect, logs, top, images, info, version)'],
                            ['name' => 'docker_mutate', 'description' => 'Ejecución de operaciones Docker de mutación de estado con HITL y rollback Saga (restart, stop, start, pause, unpause, rm)'],
                        ],
                        'engines' => [],
                    ],
                    'last_heartbeat_at' => now(),
                ]
            );
        }
    }
}
