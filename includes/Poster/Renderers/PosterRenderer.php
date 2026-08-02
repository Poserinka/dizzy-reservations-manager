<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Renderers;

use RuntimeException;

defined('ABSPATH') || exit;

final class PosterRenderer
{
    /** @param array{width:int,height:int,dpi:int} $format @param array{accent:array{0:int,1:int,2:int}} $template */
    public function render(int $attachmentId, array $format, array $template, array $content): void
    {
        $path = get_attached_file($attachmentId);

        if (! is_string($path) || $path === '' || ! is_file($path) || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The server image library is not available.');
        }

        $bytes = file_get_contents($path);
        $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;

        if ($source === false) {
            throw new RuntimeException('The generated image could not be opened.');
        }

        $width = (int) $format['width'];
        $height = (int) $format['height'];
        $canvas = imagecreatetruecolor($width, $height);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);
        $sourceX = max(0, (int) (($sourceWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) (($sourceHeight - $cropHeight) / 2));

        imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);

        imagealphablending($canvas, true);
        $panelHeight = (int) round($height * 0.39);
        $panelTop = $height - $panelHeight;
        $panel = imagecolorallocatealpha($canvas, 4, 5, 9, 22);
        imagefilledrectangle($canvas, 0, $panelTop, $width, $height, $panel);

        [$red, $green, $blue] = $template['accent'];
        $accent = imagecolorallocate($canvas, $red, $green, $blue);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 224, 224, 224);
        $margin = (int) round($width * 0.075);
        imagefilledrectangle($canvas, $margin, $panelTop + (int) ($panelHeight * 0.09), $margin + (int) ($width * 0.16), $panelTop + max(8, (int) ($height * 0.006)), $accent);
        $this->drawWatermark($canvas, $width, $height, (int) $format['dpi'] >= 300);

        $font = $this->fontPath();
        $title = trim((string) ($content['title'] ?? ''));
        $date = trim((string) ($content['date'] ?? ''));
        $venue = trim((string) ($content['venue'] ?? 'Jazzcafé Dizzy Rotterdam'));
        $titleSize = max(32, (int) round($width * 0.064));
        $metaSize = max(20, (int) round($width * 0.026));
        $titleY = $panelTop + (int) ($panelHeight * 0.25);

        $titleY = $this->drawWrapped($canvas, $title, $margin, $titleY, $width - (2 * $margin), $titleSize, $white, $font, 3);
        $metaY = max($titleY + (int) ($height * 0.018), $panelTop + (int) ($panelHeight * 0.7));
        $this->drawText($canvas, strtoupper($date), $margin, $metaY, $metaSize, $accent, $font);
        $this->drawText($canvas, $venue, $margin, $metaY + (int) ($metaSize * 1.65), $metaSize, $muted, $font);

        if (function_exists('imageresolution')) {
            imageresolution($canvas, (int) $format['dpi'], (int) $format['dpi']);
        }

        if (! imagepng($canvas, $path, 7)) {
            imagedestroy($canvas);
            throw new RuntimeException('The final poster could not be saved.');
        }

        imagedestroy($canvas);
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $path));
    }

    private function fontPath(): string
    {
        $font = (string) apply_filters('dizzy_events_poster_font_path', '');

        return $font !== '' && is_readable($font) ? $font : '';
    }

    private function drawWrapped($image, string $text, int $x, int $y, int $maxWidth, int $size, int $color, string $font, int $maxLines): int
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);

            if ($line !== '' && $this->textWidth($candidate, $size, $font) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }

            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        foreach (array_slice($lines, 0, $maxLines) as $lineText) {
            $this->drawText($image, $lineText, $x, $y, $size, $color, $font);
            $y += (int) round($size * 1.25);
        }

        return $y;
    }

    private function drawText($image, string $text, int $x, int $y, int $size, int $color, string $font): void
    {
        if ($font !== '' && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }

        $baseWidth = max(1, strlen($text) * imagefontwidth(5));
        $baseHeight = imagefontheight(5);
        $scale = max(1, $size / $baseHeight);
        $textImage = imagecreatetruecolor($baseWidth, $baseHeight);
        imagecolortransparent($textImage, imagecolorallocate($textImage, 0, 0, 0));
        $components = imagecolorsforindex($image, $color);
        $temporaryColor = imagecolorallocate($textImage, (int) $components['red'], (int) $components['green'], (int) $components['blue']);
        imagestring($textImage, 5, 0, 0, $text, $temporaryColor);
        imagecopyresampled(
            $image,
            $textImage,
            $x,
            max(0, $y - $size),
            0,
            0,
            (int) round($baseWidth * $scale),
            $size,
            $baseWidth,
            $baseHeight
        );
        imagedestroy($textImage);
    }

    private function textWidth(string $text, int $size, string $font): int
    {
        if ($font !== '' && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            return is_array($box) ? abs($box[2] - $box[0]) : 0;
        }

        return (int) round(strlen($text) * imagefontwidth(5) * max(1, $size / imagefontheight(5)));
    }

    private function drawWatermark($canvas, int $canvasWidth, int $canvasHeight, bool $isPrint): void
    {
        $enabled = (bool) get_option(
            $isPrint ? 'dizzy_events_watermark_print' : 'dizzy_events_watermark_social',
            ! $isPrint
        );

        if (! $enabled) {
            return;
        }

        $logoId = (int) get_option('dizzy_events_watermark_image_id', 0);

        if ($logoId <= 0) {
            $logoId = (int) get_theme_mod('custom_logo', 0);
        }

        $path = $logoId > 0 ? get_attached_file($logoId) : '';

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return;
        }

        $bytes = file_get_contents($path);
        $logo = is_string($bytes) ? @imagecreatefromstring($bytes) : false;

        if ($logo === false) {
            return;
        }

        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $sizeMode = (string) get_option('dizzy_events_watermark_size_mode', 'scaled');
        $targetWidth = match ($sizeMode) {
            'original' => $logoWidth,
            'custom' => (int) get_option('dizzy_events_watermark_custom_width', 400),
            default => (int) round($canvasWidth * ((int) get_option('dizzy_events_watermark_scale', 35) / 100)),
        };
        $targetWidth = max(1, min($canvasWidth, $targetWidth));
        $targetHeight = max(1, (int) round($logoHeight * ($targetWidth / $logoWidth)));
        $targetHeight = min($canvasHeight, $targetHeight);
        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, $logoWidth, $logoHeight);
        imagedestroy($logo);

        $opacity = max(0, min(100, (int) get_option('dizzy_events_watermark_opacity', 85)));
        $this->applyOpacity($scaled, $opacity);

        $alignment = (string) get_option('dizzy_events_watermark_alignment', 'top_center');
        [$vertical, $horizontal] = array_pad(explode('_', $alignment, 2), 2, 'center');
        $x = match ($horizontal) {
            'left' => 0,
            'right' => $canvasWidth - $targetWidth,
            default => (int) round(($canvasWidth - $targetWidth) / 2),
        };
        $y = match ($vertical) {
            'top' => 0,
            'bottom' => $canvasHeight - $targetHeight,
            default => (int) round(($canvasHeight - $targetHeight) / 2),
        };
        $offsetX = (float) get_option('dizzy_events_watermark_offset_x', 0);
        $offsetY = (float) get_option('dizzy_events_watermark_offset_y', 0);

        if ((string) get_option('dizzy_events_watermark_offset_unit', 'percentages') === 'percentages') {
            $offsetX = $canvasWidth * ($offsetX / 100);
            $offsetY = $canvasHeight * ($offsetY / 100);
        }

        $x = max(0, min($canvasWidth - $targetWidth, $x + (int) round($offsetX)));
        $y = max(0, min($canvasHeight - $targetHeight, $y + (int) round($offsetY)));
        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, $x, $y, 0, 0, $targetWidth, $targetHeight);
        imagedestroy($scaled);
    }

    private function applyOpacity($image, int $opacity): void
    {
        if ($opacity >= 100) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $visible = (127 - $alpha) * ($opacity / 100);
                $newAlpha = 127 - (int) round($visible);
                imagesetpixel($image, $x, $y, ($rgba & 0xFFFFFF) | ($newAlpha << 24));
            }
        }
    }
}
