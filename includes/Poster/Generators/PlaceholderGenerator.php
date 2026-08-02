<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Generators;

use Dizzy\Events\Poster\Contracts\PosterGenerator;

defined('ABSPATH') || exit;

final class PlaceholderGenerator implements PosterGenerator
{
    public function generate(string $prompt, array $options = []): string
    {
        return '';
    }
}
