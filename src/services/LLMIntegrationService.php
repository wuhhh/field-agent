<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use yii\base\Exception;

/**
 * LLM Integration service for generating field configurations from natural language
 */
class LLMIntegrationService extends Component
{
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI = 'openai';

    /**
     * Call Anthropic Claude API with structured output
     */
    public function callAnthropic(string $systemPrompt, string $userPrompt, array $schema, bool $debug = false): array
    {
        $apiKey = $this->getApiKey('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new Exception("Anthropic API key not found in environment variables");
        }

        $payload = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt
				],
				// TODO: Try assistant prefill here
				// [
				//     'role' => 'assistant',
				//     'content' => '{ "name": ',
				// ],
            ]
            // Note: Anthropic doesn't support response_format yet
            // We rely on system prompt instructions for JSON output
        ];

        if ($debug) {
            $this->logDebug("=== ANTHROPIC REQUEST ===");
            $this->logDebug("URL: https://api.anthropic.com/v1/messages");
            $this->logDebug("Model: " . $payload['model']);
            $this->logDebug("Max Tokens: " . $payload['max_tokens']);
            $this->logDebug("System Prompt: " . substr($systemPrompt, 0, 200) . "...");
            $this->logDebug("User Prompt: $userPrompt");
            $this->logDebug("Full Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
        }

        $response = $this->makeHttpRequest('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ], $payload, $debug);

        if ($debug) {
            $this->logDebug("=== ANTHROPIC RESPONSE ===");
            $this->logDebug("Full Response: " . json_encode($response, JSON_PRETTY_PRINT));
        }

        if (!isset($response['content'][0]['text'])) {
            throw new Exception("Invalid response from Anthropic API");
        }

        $rawContent = $response['content'][0]['text'];

        if ($debug) {
            $this->logDebug("Raw Content: $rawContent");
        }

        $jsonResponse = json_decode($rawContent, true);
        if (!$jsonResponse) {
            throw new Exception("Failed to parse JSON from Anthropic response: $rawContent");
        }

        if ($debug) {
            $this->logDebug("Parsed JSON: " . json_encode($jsonResponse, JSON_PRETTY_PRINT));
        }

        return $jsonResponse;
    }

    /**
     * Call OpenAI API with operations schema
     */
    public function callOpenAI(string $systemPrompt, string $userPrompt, array $schema, bool $debug): array
    {
        $apiKey = $this->getApiKey('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new Exception("OpenAI API key not found in environment variables");
        }

        $payload = [
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'operations_configuration',
                    'schema' => $schema
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.1
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $response = $this->makeHttpRequest(
            'https://api.openai.com/v1/chat/completions',
            $headers,
            $payload,
            $debug
        );

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected OpenAI API response structure");
        }

        $content = $response['choices'][0]['message']['content'];
        $parsedContent = json_decode($content, true);

        if (!$parsedContent) {
            throw new Exception("Failed to parse OpenAI response as JSON: $content");
        }

        return $parsedContent;
    }

    /**
     * Make HTTP request to LLM API
     */
    private function makeHttpRequest(string $url, array $headers, array $payload, bool $debug = false): array
    {
        $jsonPayload = json_encode($payload);

        if ($debug) {
            $this->logDebug("=== HTTP REQUEST ===");
            $this->logDebug("URL: $url");
            $this->logDebug("Headers: " . implode(', ', array_map(function($h) {
                return strpos($h, 'x-api-key') === 0 ? 'x-api-key: ***' :
                       (strpos($h, 'Authorization') === 0 ? 'Authorization: Bearer ***' : $h);
            }, $headers)));
            $this->logDebug("Payload Size: " . strlen($jsonPayload) . " bytes");
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($debug) {
            $this->logDebug("=== HTTP RESPONSE ===");
            $this->logDebug("Duration: {$duration}ms");
            $this->logDebug("HTTP Code: $httpCode");
            $this->logDebug("Response Size: " . strlen($response) . " bytes");
            if ($error) {
                $this->logDebug("cURL Error: $error");
            }
        }

        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }

        if ($httpCode !== 200) {
            if ($debug) {
                $this->logDebug("HTTP Error Details:");
                $this->logDebug("- Status Code: $httpCode");
                $this->logDebug("- Response Body: $response");

                // Try to parse error response for more details
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['error'])) {
                    $this->logDebug("- Error Type: " . ($errorData['error']['type'] ?? 'unknown'));
                    $this->logDebug("- Error Message: " . ($errorData['error']['message'] ?? 'no message'));
                }
            }
            throw new Exception("HTTP request failed with code $httpCode: $response");
        }

        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse) {
            throw new Exception("Failed to decode JSON response: $response");
        }

        if (isset($decodedResponse['error'])) {
            throw new Exception("API error: " . $decodedResponse['error']['message'] ?? 'Unknown error');
        }

        return $decodedResponse;
    }

    /**
     * Get API key from Craft config system
     */
    private function getApiKey(string $keyName): ?string
    {
        // Map environment variable names to config keys
        $configKeyMap = [
            'ANTHROPIC_API_KEY' => 'anthropicApiKey',
            'OPENAI_API_KEY' => 'openaiApiKey',
        ];

        $configKey = $configKeyMap[$keyName] ?? null;
        if (!$configKey) {
            $this->logDebug("No config mapping found for: $keyName");
            return null;
        }

        // Get from Craft config system
        $config = Craft::$app->getConfig()->getConfigFromFile('field-agent');
        $key = $config[$configKey] ?? null;

        if ($key) {
            // $this->log("API key found via Craft config system");
            return $key;
        }

        $this->logDebug("API key not found for: $keyName (config key: $configKey)");
        return null;
    }

    /**
     * Log debug message to Craft logs and console
     */
    private function logDebug(string $message): void
    {
        $this->log($message, '[DEBUG]');
    }

	/**
	 * Log message to Craft logs and console
	 */
	private function log(string $message, string $prefix = ""): void
	{
		// Log to Craft's log system (storage/logs/web.log)
		Craft::info($message, 'field-agent');

		// Also echo to console if in CLI mode for immediate visibility
		if (Craft::$app instanceof \craft\console\Application) {
			echo ($prefix ? "[$prefix] " : "") . $message . "\n";
		}
	}

    /**
     * Test API connection and response with a simple prompt
     */
    public function testConnection(string $provider = self::PROVIDER_ANTHROPIC, bool $debug = false): array
    {
        try {
			$prompt = "This is a test prompt to check API connectivity. You should respond with a simple JSON object containing a 'response' key with the value 'success'.";
			$schema = [
				'type' => 'object',
				'properties' => [
					'response' => [
						'type' => 'string',
						'enum' => ['success']
					]
				],
				'required' => ['response']
			];

			if ($debug) {
				$this->logDebug("=== LLM TEST CONNECTION DEBUG ===");
				$this->logDebug("Provider: $provider");
				$this->logDebug("Test Prompt: $prompt");
			}

			// Call the appropriate LLM provider
			$response = match ($provider) {
				self::PROVIDER_ANTHROPIC => $this->callAnthropic("Test connection system prompt", $prompt, $schema, $debug),
				self::PROVIDER_OPENAI => $this->callOpenAI("Test connection system prompt", $prompt, $schema, $debug),
				default => throw new Exception("Unsupported LLM provider: $provider")
			};

			if ($debug) {
				$this->logDebug("=== LLM TEST CONNECTION RESPONSE DEBUG ===");
				$this->logDebug("Response: " . json_encode($response, JSON_PRETTY_PRINT));
			}

			// Check if the response contains the expected success message
			if (!isset($response['response']) || $response['response'] !== 'success') {
				throw new Exception("Unexpected response from LLM: " . json_encode($response));
			}

            return [
                'success' => true,
                'provider' => $provider,
                'message' => 'API connection successful'
			];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }
}
