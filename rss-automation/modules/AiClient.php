<?php
/**
 * AI Client – supports OpenAI and Anthropic
 *
 * Used by MetaAnalyzer, KeywordResearcher, and ArticleRewriter.
 */

class AiClient
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private Logger $log;

    public function __construct(array $config, Logger $log)
    {
        $this->provider = strtolower($config['ai_provider'] ?? 'openai');
        $this->apiKey   = $config['ai_api_key'] ?? '';
        $this->model    = $config['ai_model']   ?? 'gpt-4o-mini';
        $this->log      = $log;
    }

    /**
     * Send a chat completion request.
     *
     * @param  string $systemPrompt  Instructions for the AI.
     * @param  string $userMessage   The input content / question.
     * @param  int    $maxTokens     Maximum tokens to generate.
     * @return string                The AI's response text.
     */
    public function chat(string $systemPrompt, string $userMessage, int $maxTokens = 1500): string
    {
        if (empty($this->apiKey)) {
            $this->log->error("AI API key is not set.");
            return '';
        }

        return match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userMessage, $maxTokens),
            default     => $this->callOpenAI($systemPrompt, $userMessage, $maxTokens),
        };
    }

    // ─── OpenAI ──────────────────────────────────────────────────────────────

    private function callOpenAI(string $system, string $user, int $maxTokens): string
    {
        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ]);

        $response = $this->httpPost(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ["Authorization: Bearer {$this->apiKey}", "Content-Type: application/json"]
        );

        if (!$response) return '';

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    // ─── Anthropic ───────────────────────────────────────────────────────────

    private function callAnthropic(string $system, string $user, int $maxTokens): string
    {
        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $response = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            $payload,
            [
                "x-api-key: {$this->apiKey}",
                "anthropic-version: 2023-06-01",
                "Content-Type: application/json",
            ]
        );

        if (!$response) return '';

        $data = json_decode($response, true);
        return trim($data['content'][0]['text'] ?? '');
    }

    // ─── HTTP helper ─────────────────────────────────────────────────────────

    private function httpPost(string $url, string $payload, array $headers): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->log->error("AI API returned HTTP $code. Response: " . substr((string)$result, 0, 300));
            return false;
        }

        return $result ?: false;
    }
}
