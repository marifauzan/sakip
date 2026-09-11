<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Klien ringan untuk API LLM OpenAI-compatible (api.tokito.xyz/v1).
 * Dipanggil HANYA dari backend; API key tidak pernah sampai ke browser.
 */
class LlmClient
{
    /**
     * Kirim chat completion dan kembalikan JSON terstruktur.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  array<string, mixed>|null  $jsonSchema  Skema JSON output (opsional)
     */
    public function chat(array $messages, ?array $jsonSchema = null): array
    {
        $payload = [
            'model' => config('llm.model'),
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => config('llm.max_output_tokens'),
        ];

        if ($jsonSchema !== null) {
            // Mode structured output (JSON mode).
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
        }

        $response = Http::baseUrl(config('llm.base_url'))
            ->withToken(config('llm.api_key'))
            ->timeout(config('llm.timeout'))
            ->retry(2, 500)
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException(
                'LLM error: '.$response->status().' '.mb_substr($response->body(), 0, 300)
            );
        }

        $content = $response->json('choices.0.message.content');

        if ($jsonSchema !== null) {
            return $this->decodeJson($content);
        }

        return ['text' => $content];
    }

    /**
     * Decode JSON output dengan toleransi (kalau model membungkus dengan ```json).
     *
     * @return array<string, mixed>
     */
    private function decodeJson(?string $content): array
    {
        $content = trim((string) $content);

        // Hapus pembungkus markdown ```json ... ```
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('LLM tidak mengembalikan JSON valid.');
        }

        return $decoded;
    }
}
