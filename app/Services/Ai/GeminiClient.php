<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Gemini (Google Generative Language API) client.
 *
 * Deliberately thin: the forecaster uses the model for two text jobs only —
 * sorting clients into demand segments from their free-text names/notes, and
 * writing a manager-facing summary. No forecast number ever comes from here,
 * so every caller must keep working when this returns null. `enabled()` is
 * checked by callers rather than throwing, because a missing API key is a
 * normal deployment state for this app, not an error.
 */
class GeminiClient
{
    public function enabled(): bool
    {
        return (bool) config('forecasting.ai.enabled') && filled(config('services.gemini.key'));
    }

    /**
     * Send a prompt and return the raw text response, or null on any failure.
     *
     * @param  bool  $json  ask the model to emit application/json
     */
    public function generate(string $prompt, bool $json = false, ?string $systemInstruction = null): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $model   = config('services.gemini.model');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                // Segment classification must be reproducible run to run, so
                // temperature stays at 0 for every call this app makes.
                'temperature' => 0.0,
            ],
        ];

        if ($json) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        try {
            $response = Http::timeout((int) config('services.gemini.timeout', 45))
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->acceptJson()
                ->post("{$baseUrl}/models/{$model}:generateContent", $payload);

            if ($response->failed()) {
                Log::warning('Gemini request failed', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $text  = collect($parts)->pluck('text')->filter()->implode('');

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini request threw', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Prompt for JSON and decode it.
     *
     * Models occasionally wrap JSON in a ```json fence even when asked not to,
     * so the fence is stripped before decoding rather than failing the call.
     */
    public function generateJson(string $prompt, ?string $systemInstruction = null): ?array
    {
        $text = $this->generate($prompt, json: true, systemInstruction: $systemInstruction);

        if ($text === null) {
            return null;
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;

        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }
}
