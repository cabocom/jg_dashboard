<?php

declare(strict_types=1);

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\DTO\Execution;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

readonly class TriggerExecutionInput
{
    public function __construct(
        public string $prompt,
        public ?string $agentId = null,
        public ?string $threadId = null,
        public array $parameters = [],
        public bool $isAsync = false,
        public array $metadata = [],
        public string $correlationId = '',
        public ?string $operatorHeader = null,
        public ?string $clientIp = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'prompt' => 'required_without:user_prompt|nullable|string|min:1',
            'user_prompt' => 'required_without:prompt|nullable|string|min:1',
            'agent_id' => 'nullable|string',
            'thread_id' => 'nullable|string',
            'parameters' => 'nullable|array',
            'async' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $prompt = $validated['prompt'] ?? $validated['user_prompt'];
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();

        return new self(
            prompt: $prompt,
            agentId: $validated['agent_id'] ?? null,
            threadId: !empty($validated['thread_id']) ? $validated['thread_id'] : null,
            parameters: (array) ($validated['parameters'] ?? []),
            isAsync: $request->boolean('async'),
            metadata: (array) ($validated['metadata'] ?? []),
            correlationId: $correlationId,
            operatorHeader: $request->header('X-Operator'),
            clientIp: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}

