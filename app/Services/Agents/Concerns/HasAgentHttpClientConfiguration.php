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

use Illuminate\Support\Facades\Config;

trait HasAgentHttpClientConfiguration
{
    public function withBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    public function withSecret(string $secret): self
    {
        $this->internalSecret = $secret;

        return $this;
    }

    public function withAgentId(string $agentId): self
    {
        $this->agentId = $agentId;

        if ($agentId === 'a0000000-0000-0000-0000-000000000002') {
            $this->internalSecret = Config::get('agent.devops_secret');
        } elseif ($agentId === 'a0000000-0000-0000-0000-000000000001') {
            $this->internalSecret = Config::get('agent.conversational_secret') ?: Config::get('agent.internal_secret');
        } else {
            $this->internalSecret = Config::get('agent.secrets.'.$agentId) ?: Config::get('agent.internal_secret');
        }

        return $this;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
