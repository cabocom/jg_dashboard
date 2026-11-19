<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Agents\Concerns;

trait HasAgentInspection
{
    public function checkHealth(?string $baseUrl = null): array
    {
        $result = $this->sendRequest('GET', '/health', [], [], $baseUrl);
        if (! $result['success'] && $result['status_code'] === 404) {
            $result = $this->sendRequest('GET', '/', [], [], $baseUrl);
        }

        return $result;
    }

    public function getCapabilities(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/capabilities', [], [], $baseUrl);
    }

    public function getMetrics(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/metrics', [], [], $baseUrl);
    }
}
