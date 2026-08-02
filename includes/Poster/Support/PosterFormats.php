<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Support;

defined('ABSPATH') || exit;

final class PosterFormats
{
    /** @return array<string, array{label:string,width:int,height:int,dpi:int,ai_size:string}> */
    public static function all(): array
    {
        return [
            'social_square' => ['label' => __('Instagram / Facebook square (1080 × 1080 PNG)', 'dizzy-events-manager'), 'width' => 1080, 'height' => 1080, 'dpi' => 72, 'ai_size' => '1024x1024'],
            'social_portrait' => ['label' => __('Instagram / Facebook portrait (1080 × 1350 PNG)', 'dizzy-events-manager'), 'width' => 1080, 'height' => 1350, 'dpi' => 72, 'ai_size' => '1024x1536'],
            'social_story' => ['label' => __('Instagram / Facebook Story / Reel (1080 × 1920 PNG)', 'dizzy-events-manager'), 'width' => 1080, 'height' => 1920, 'dpi' => 72, 'ai_size' => '1024x1536'],
            'print_a4' => ['label' => __('Print A4 portrait (300 DPI)', 'dizzy-events-manager'), 'width' => 2480, 'height' => 3508, 'dpi' => 300, 'ai_size' => '1024x1536'],
        ];
    }

    /** @return array{label:string,width:int,height:int,dpi:int,ai_size:string} */
    public static function get(string $key): array
    {
        $formats = self::all();

        return $formats[self::sanitize($key)];
    }

    public static function sanitize(string $key): string
    {
        $legacy = [
            'instagram_square' => 'social_square',
            'facebook_square' => 'social_square',
            'instagram_portrait' => 'social_portrait',
            'facebook_portrait' => 'social_portrait',
            'instagram_story' => 'social_story',
            'facebook_story' => 'social_story',
        ];
        $key = $legacy[$key] ?? $key;

        return isset(self::all()[$key]) ? $key : 'social_square';
    }
}
