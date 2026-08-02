<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Generators;

use Dizzy\Events\Poster\Contracts\PosterGenerator;

defined('ABSPATH') || exit;

final class OpenAIImageGenerator implements PosterGenerator
{
    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    public function generate(string $prompt, array $options = []): string
    {
        if ($this->apiKey === '' || trim($prompt) === '') {
            return '';
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'  => 'gpt-image-1',
                    'prompt' => $prompt,
                    'size'   => in_array(($options['size'] ?? ''), ['1024x1024', '1024x1536', '1536x1024'], true)
                        ? $options['size']
                        : '1024x1024',
                ]),
                'timeout' => 60,
            ]
        );

        if (is_wp_error($response)) {
            return '';
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        if (! is_array($body) || ! isset($body['data'][0]) || ! is_array($body['data'][0])) {
            return '';
        }

        $image = $body['data'][0];

        if (isset($image['b64_json']) && is_string($image['b64_json']) && $image['b64_json'] !== '') {
            return 'data:image/png;base64,' . $image['b64_json'];
        }

        return isset($image['url']) && is_string($image['url'])
            ? $image['url']
            : '';
    }
}
