<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Models;

defined('ABSPATH') || exit;

readonly class Poster
{
    public function __construct(
        public int $id = 0,
        public ?int $eventId = null,
        public ?int $attachmentId = null,
        public string $prompt = '',
        public string $imageUrl = '',
        public string $status = 'draft',
    ) {
    }
}
