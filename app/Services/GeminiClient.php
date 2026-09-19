<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiNotConfiguredException;
use App\Exceptions\AiChat\GeminiRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class GeminiClient
{
    /**
     * @param array<int, array{role: string, parts: array<int, array{text: string}>}> $contents
     *
     * @return array{content: string, model: string, input_tokens: ?int, output_tokens: ?int}
     */
    public function generate(array $contents, string $systemPrompt): array
    {
        $apiKey = config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new GeminiNotConfiguredException;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('ai-chat.gemini.base_url'), '/'))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->timeout((int) config('ai-chat.gemini.timeout'))
                ->post("/v1beta/models/{$model}:generateContent", [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => $contents,
                ]);
        } catch (ConnectionException $exception) {
            throw new GeminiRequestException(previous: $exception);
        }

        if ($response->failed()) {
            throw new GeminiRequestException(upstreamStatus: $response->status());
        }

        $parts = $response->json('candidates.0.content.parts');
        $content = is_array($parts)
            ? collect($parts)->pluck('text')->filter(fn ($text) => is_string($text))->implode('')
            : '';

        if ($content === '') {
            throw new GeminiRequestException(upstreamStatus: $response->status());
        }

        return [
            'content' => $content,
            'model' => (string) ($response->json('modelVersion') ?: $model),
            'input_tokens' => $this->nullableInt($response->json('usageMetadata.promptTokenCount')),
            'output_tokens' => $this->nullableInt($response->json('usageMetadata.candidatesTokenCount')),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
