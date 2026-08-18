<?php

namespace App\Services;

use App\Ai\Agents\OperationsCopilotAgent;
use App\Models\AiInteraction;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

class AiCopilotService
{
    /**
     * @return array{ok: bool, answer?: string, message?: string}
     */
    public function ask(string $question, User $user, int $eventId): array
    {
        $startedAt = microtime(true);

        try {
            $response = app(OperationsCopilotAgent::class)->prompt($question);

            $this->logInteraction(
                user: $user,
                eventId: $eventId,
                question: $question,
                status: 'success',
                durationMs: $this->elapsedMs($startedAt),
                toolCalls: $response->toolCalls
                    ->map(fn ($call) => ['tool' => $call->name, 'arguments' => $call->arguments])
                    ->all(),
                responseSummary: $response->text,
                provider: $response->meta->provider,
                model: $response->meta->model,
            );

            return ['ok' => true, 'answer' => $response->text];
        } catch (Throwable $e) {
            report($e);

            $this->logInteraction(
                user: $user,
                eventId: $eventId,
                question: $question,
                status: 'failed',
                durationMs: $this->elapsedMs($startedAt),
                errorMessage: Str::limit($e->getMessage(), 500),
            );

            return [
                'ok' => false,
                'message' => 'The AI assistant is temporarily unavailable. Please try again shortly.',
            ];
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function logInteraction(
        User $user,
        int $eventId,
        string $question,
        string $status,
        int $durationMs,
        array $toolCalls = [],
        ?string $responseSummary = null,
        ?string $errorMessage = null,
        ?string $provider = null,
        ?string $model = null,
    ): void {
        AiInteraction::create([
            'user_id' => $user->id,
            'event_id' => $eventId,
            'question' => $question,
            'tool_calls' => $toolCalls,
            'response_summary' => $responseSummary,
            'status' => $status,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'provider' => $provider,
            'model' => $model,
        ]);
    }
}
