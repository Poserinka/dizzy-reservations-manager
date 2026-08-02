<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Services;

use Dizzy\Events\Poster\Contracts\PosterGenerator;
use Dizzy\Events\Poster\Models\Poster;
use Dizzy\Events\Poster\Repositories\PosterRepository;
use Dizzy\Events\Poster\Renderers\PosterRenderer;
use Dizzy\Events\Poster\Support\PosterFormats;
use Dizzy\Events\Poster\Support\PosterTemplates;
use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

final class PosterService
{
    public function __construct(
        private readonly PosterRepository $repository,
        private readonly PosterGenerator $generator,
        private readonly PosterRenderer $renderer,
    ) {
    }

    public function create(array $data): Poster
    {
        $formatKey = PosterFormats::sanitize((string) ($data['format'] ?? 'social_square'));
        $templateKey = PosterTemplates::sanitize((string) ($data['template'] ?? 'classic'));
        $format = PosterFormats::get($formatKey);
        $template = PosterTemplates::get($templateKey);
        $imageUrl = isset($data['image_url']) && is_string($data['image_url'])
            ? trim($data['image_url'])
            : '';

        if ($imageUrl === '') {
            $data['image_url'] = $this->generator->generate((string) ($data['prompt'] ?? ''), ['size' => $format['ai_size']]);

            $imageUrl = trim((string) $data['image_url']);
        }

        if ($imageUrl === '') {
            throw new RuntimeException('Poster generation returned no image.');
        }

        $attachmentId = $this->importMedia(
            $imageUrl,
            (int) ($data['event_id'] ?? 0)
        );

        if ($attachmentId === 0) {
            throw new RuntimeException('Generated poster could not be imported into the media library.');
        }

        try {
            $this->renderer->render($attachmentId, $format, $template, [
                'title' => (string) ($data['title'] ?? ''),
                'date' => (string) ($data['date'] ?? ''),
                'venue' => (string) ($data['venue'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            wp_delete_attachment($attachmentId, true);
            throw $exception;
        }

        update_post_meta($attachmentId, '_dizzy_poster_format', $formatKey);
        update_post_meta($attachmentId, '_dizzy_poster_template', $templateKey);
        update_post_meta($attachmentId, '_dizzy_poster_dpi', (int) $format['dpi']);

        $localUrl = wp_get_attachment_url($attachmentId);

        if (! is_string($localUrl) || $localUrl === '') {
            throw new RuntimeException('Imported poster has no media URL.');
        }

        $data['image_url'] = $localUrl;
        $data['attachment_id'] = $attachmentId;

        try {
            $poster = $this->repository->create($data);
        } catch (Throwable $exception) {
            wp_delete_attachment($attachmentId, true);

            throw $exception;
        }

        do_action(
            'dizzy_events_poster_created',
            $poster
        );

        return $poster;
    }

    private function importMedia(string $url, int $postId): int
    {
        if (str_starts_with($url, 'data:image/')) {
            return $this->importBase64Media($url, $postId);
        }

        if (! function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = media_sideload_image(
            $url,
            $postId,
            null,
            'id'
        );

        return is_wp_error($attachmentId) ? 0 : (int) $attachmentId;
    }

    private function importBase64Media(string $dataUrl, int $postId): int
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#s', $dataUrl, $matches)) {
            return 0;
        }

        $contents = base64_decode($matches[2], true);

        if ($contents === false || $contents === '') {
            return 0;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $upload = wp_upload_bits(
            'dizzy-poster-' . wp_generate_uuid4() . '.' . $extension,
            null,
            $contents
        );

        if (! empty($upload['error']) || empty($upload['file'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => (string) ($upload['type'] ?? 'image/' . $matches[1]),
                'post_title' => get_the_title($postId) . ' poster',
                'post_status' => 'inherit',
            ],
            (string) $upload['file'],
            $postId,
            true
        );

        if (is_wp_error($attachmentId)) {
            wp_delete_file((string) $upload['file']);

            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata(
            (int) $attachmentId,
            (string) $upload['file']
        );

        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $attachmentId, $metadata);
        }

        return (int) $attachmentId;
    }
}
