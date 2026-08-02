<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Support;

defined('ABSPATH') || exit;

final class PosterTemplates
{
    /** @return array<string, array{label:string,accent:array{0:int,1:int,2:int},style:string}> */
    public static function all(): array
    {
        return [
            'classic' => ['label' => __('Classic', 'dizzy-events-manager'), 'accent' => [222, 184, 92], 'style' => 'timeless jazz photography, warm gold accents, elegant and restrained'],
            'food' => ['label' => __('Food', 'dizzy-events-manager'), 'accent' => [227, 111, 67], 'style' => 'lively food and music atmosphere, warm terracotta accents, inviting and editorial'],
            'club' => ['label' => __('Club', 'dizzy-events-manager'), 'accent' => [181, 82, 255], 'style' => 'energetic late-night jazz club, purple neon accents, bold and cinematic'],
        ];
    }

    /** @return array{label:string,accent:array{0:int,1:int,2:int},style:string} */
    public static function get(string $key): array
    {
        $templates = self::all();

        return $templates[$key] ?? $templates['classic'];
    }

    public static function sanitize(string $key): string
    {
        return isset(self::all()[$key]) ? $key : 'classic';
    }
}
