<?php

declare(strict_types=1);

namespace App\Adapters\Normalizers;

use Illuminate\Support\Str;

class StimulusPromptExtractor
{
    /**
     * Extract or synthesize a prompt text from heterogeneous webhook payloads.
     */
    public static function extract(array $payload, string $sourceType): string
    {
        // 1. Direct explicit prompts
        foreach (['prompt', 'user_prompt', 'message', 'text', 'content', 'query', 'input'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        // 2. Chatbot nested formats (e.g. { message: { text: "..." } })
        if (isset($payload['message']) && is_array($payload['message'])) {
            if (!empty($payload['message']['text']) && is_string($payload['message']['text'])) {
                return trim($payload['message']['text']);
            }
        }

        // 3. Slack event format (e.g. { event: { text: "..." } })
        if (isset($payload['event']['text']) && is_string($payload['event']['text'])) {
            return trim($payload['event']['text']);
        }

        // 4. GitLab Webhook synthesis
        if ($sourceType === 'gitlab' || isset($payload['object_kind'])) {
            return self::extractGitLabPrompt($payload);
        }

        // 5. GitHub Webhook synthesis
        if ($sourceType === 'github') {
            return self::extractGitHubPrompt($payload);
        }

        // 6. Generic fallback if json object has raw parameters
        if (!empty($payload)) {
            $summary = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return "[External Webhook Stimulus] " . Str::limit($summary, 500);
        }

        return "External stimulus received without prompt text.";
    }

    protected static function extractGitLabPrompt(array $payload): string
    {
        $kind = $payload['object_kind'] ?? 'webhook';
        $project = $payload['project']['name'] ?? $payload['project']['path_with_namespace'] ?? 'Proyecto GitLab';
        $user = $payload['user_name'] ?? $payload['user_username'] ?? 'CI/CD';

        if ($kind === 'push') {
            $ref = $payload['ref'] ?? 'refs/heads/main';
            $commitMsg = $payload['commits'][0]['message'] ?? 'Sin mensaje de commit';
            return "[GitLab CI/CD: Push] {$project} ({$ref}) por {$user}: {$commitMsg}";
        }

        if ($kind === 'pipeline') {
            $status = $payload['object_attributes']['status'] ?? 'unknown';
            $ref = $payload['object_attributes']['ref'] ?? 'unknown';
            return "[GitLab CI/CD: Pipeline] {$project} ({$ref}) estado '{$status}' disparado por {$user}";
        }

        if ($kind === 'merge_request') {
            $title = $payload['object_attributes']['title'] ?? 'Merge Request';
            $action = $payload['object_attributes']['action'] ?? 'actualizado';
            return "[GitLab CI/CD: MR] {$project} MR '{$title}' ({$action}) por {$user}";
        }

        return "[GitLab Webhook: {$kind}] Evento en {$project} por {$user}";
    }

    protected static function extractGitHubPrompt(array $payload): string
    {
        $repo = $payload['repository']['full_name'] ?? 'GitHub Repo';
        $sender = $payload['sender']['login'] ?? 'GitHub User';

        if (isset($payload['commits'])) {
            $commitMsg = $payload['commits'][0]['message'] ?? ($payload['head_commit']['message'] ?? 'Push');
            return "[GitHub CI/CD: Push] {$repo} por {$sender}: {$commitMsg}";
        }

        if (isset($payload['pull_request'])) {
            $action = $payload['action'] ?? 'opened';
            $title = $payload['pull_request']['title'] ?? 'Pull Request';
            return "[GitHub CI/CD: PR] {$repo} PR '{$title}' ({$action}) por {$sender}";
        }

        return "[GitHub Webhook] Evento en {$repo} por {$sender}";
    }
}
