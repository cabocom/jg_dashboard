<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
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
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($adminPassword)) {
            throw new RuntimeException(
                "Seguridad (CWE-521): La variable de entorno ADMIN_DEFAULT_PASSWORD es obligatoria para inicializar " .
                "el usuario administrador SRE. Configure ADMIN_DEFAULT_PASSWORD en su entorno o archivo .env."
            );
        }

        $adminEmail = env('ADMIN_EMAIL') ?: 'admin@devops60k.local';
        $adminName = env('ADMIN_NAME') ?: 'SRE Admin';

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]
        );

        // Seed del Agente principal (agnóstico de plugin)
        $agentSecret = \Illuminate\Support\Facades\Config::get('agent.internal_secret')
            ?: env('JG_AGENTE_SECRET', env('JG_CONVERSATIONAL_SECRET'));

        if (empty($agentSecret)) {
            $agentSecret = 'secret_dev_token_12345';
        }

        $agentId = env('AGENT_ID', 'a0000000-0000-0000-0000-000000000001');
        $agentName = env('AGENT_NAME', env('CONVERSATIONAL_AGENT_NAME', 'Agente Conversacional'));
        $agentApiUrl = env('JG_AGENTE_API_URL', 'http://jg_agente:8000');

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
        $devopsSecret = \Illuminate\Support\Facades\Config::get('agent.devops_secret')
            ?: env('JG_DEVOPS_SECRET', env('JG_DEVOPS_AGENT_SECRET', env('DEVOPS60K_SECRET')));

        if (!empty($devopsSecret) && env('ENABLE_DEVOPS_AGENT') === 'true') {
            \App\Models\Agent::updateOrCreate(
                ['agent_id' => 'a0000000-0000-0000-0000-000000000002'],
                [
                    'name' => 'Agente DevOps',
                    'version' => '0.1.0',
                    'status' => 'ACTIVE',
                    'permanent_secret_hash' => hash('sha256', $devopsSecret),
                    'permanent_secret_encrypted' => $devopsSecret,
                    'metadata_json' => [
                        'api_url' => 'http://jg_agente_devops:8000',
                        'plugin' => 'devops',
                    ],
                    'capabilities_json' => [
                        'tools' => [
                            ['name' => 'docker_list_containers', 'description' => 'Lista de contenedores Docker'],
                            ['name' => 'docker_inspect_container', 'description' => 'Inspección de contenedor Docker'],
                            ['name' => 'docker_restart', 'description' => 'Reinicio seguro de contenedor con compensación'],
                        ],
                        'engines' => ['dag_planner', 'infra_discovery', 'devops_verifier'],
                    ],
                    'last_heartbeat_at' => now(),
                ]
            );
        }
    }
}
