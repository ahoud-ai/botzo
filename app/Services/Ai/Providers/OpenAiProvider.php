<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiChatProvider;
use App\Services\Ai\Contracts\AiEmbeddingProvider;
use App\Services\Ai\Contracts\AiSpeechProvider;
use App\Services\Ai\Contracts\AiTranscriptionProvider;
use App\Services\Ai\Data\AiChatRequest;
use App\Services\Ai\Data\AiChatResult;
use App\Services\Ai\Data\AiSpeechRequest;
use App\Services\Ai\Data\AiSpeechResult;
use App\Services\Ai\Data\AiTranscriptionRequest;
use App\Services\Ai\Data\AiTranscriptionResult;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiChatProvider, AiEmbeddingProvider, AiSpeechProvider, AiTranscriptionProvider
{
    public const NAME = 'openai';

    private const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function chat(AiChatRequest $request): AiChatResult
    {
        $payload = [
            'model' => $request->model,
            'messages' => $request->messages,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ];

        if ($request->audioVoice !== null) {
            $payload['modalities'] = ['text', 'audio'];
            $payload['audio'] = ['voice' => $request->audioVoice, 'format' => 'mp3'];
        }

        $json = $this->post('/chat/completions', $payload, $request->timeoutSeconds);
        $message = data_get($json, 'choices.0.message', []);
        $audioTranscript = data_get($message, 'audio.transcript');

        return new AiChatResult(
            text: (string) ($audioTranscript ?? data_get($message, 'content') ?? ''),
            provider: self::NAME,
            model: (string) data_get($json, 'model', $request->model),
            inputTokens: (int) data_get($json, 'usage.prompt_tokens', 0),
            outputTokens: (int) data_get($json, 'usage.completion_tokens', 0),
            audioId: data_get($message, 'audio.id'),
            audioData: data_get($message, 'audio.data'),
        );
    }

    public function embed(string $text, string $model): array
    {
        $embedding = data_get($this->post('/embeddings', ['input' => $text, 'model' => $model], 30), 'data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new AiProviderException(__('The AI provider returned no embedding.'));
        }

        return $embedding;
    }

    public function speak(AiSpeechRequest $request): AiSpeechResult
    {
        $payload = array_filter([
            'model' => $request->model,
            'input' => $request->text,
            'voice' => $request->voice,
            'response_format' => $request->format,
            'instructions' => $request->instructions,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(max(5, $request->timeoutSeconds))
                ->post(self::BASE_URL.'/audio/speech', $payload);
        } catch (ConnectionException|RequestException $exception) {
            throw new AiProviderException(__('Could not reach the AI provider.'), null, 'connection_failed', $exception);
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        if ($response->body() === '') {
            throw new AiProviderException(__('The AI provider returned no audio.'));
        }

        return new AiSpeechResult(
            audio: $response->body(),
            mimeType: $request->format === 'opus' ? 'audio/ogg' : 'audio/'.$request->format,
            provider: self::NAME,
            model: $request->model,
        );
    }

    public function transcribe(AiTranscriptionRequest $request): AiTranscriptionResult
    {
        $fields = array_filter([
            'model' => $request->model,
            'language' => $request->language,
            'prompt' => $request->prompt,
            'response_format' => 'json',
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(max(5, $request->timeoutSeconds))
                ->attach('file', $request->audio, $request->fileName, ['Content-Type' => $request->mimeType])
                ->post(self::BASE_URL.'/audio/transcriptions', $fields);
        } catch (ConnectionException|RequestException $exception) {
            throw new AiProviderException(__('Could not reach the AI provider.'), null, 'connection_failed', $exception);
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $seconds = data_get($response->json(), 'usage.seconds') ?? data_get($response->json(), 'duration');

        return new AiTranscriptionResult(
            text: trim((string) data_get($response->json(), 'text', '')),
            provider: self::NAME,
            model: $request->model,
            seconds: is_numeric($seconds) ? (float) $seconds : null,
        );
    }

    private function post(string $path, array $payload, int $timeoutSeconds): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(max(5, $timeoutSeconds))
                ->retry(1, 250, throw: false)
                ->post(self::BASE_URL.$path, $payload);
        } catch (ConnectionException|RequestException $exception) {
            throw new AiProviderException(__('Could not reach the AI provider.'), null, 'connection_failed', $exception);
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return (array) $response->json();
    }

    private function failure(Response $response): AiProviderException
    {
        $code = (string) (data_get($response->json(), 'error.code') ?? data_get($response->json(), 'error.type') ?? '');
        $message = (string) data_get($response->json(), 'error.message', 'unknown error');

        return new AiProviderException(
            trim(sprintf('OpenAI request failed (%d): %s %s', $response->status(), $code, $message)),
            $response->status(),
            $code !== '' ? $code : null,
        );
    }
}
