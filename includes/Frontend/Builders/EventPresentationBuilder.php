<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend\Builders;

use Dizzy\Events\Frontend\ViewModels\EventViewData;
use Dizzy\Events\Frontend\ViewModels\OccurrenceViewData;
use Dizzy\Events\Models\Event;
use Dizzy\Events\Models\EventDetails;
use Dizzy\Events\Models\Occurrence;
use Throwable;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Builds frontend event view data.
 *
 * @package Dizzy\Events\Frontend\Builders
 */
final class EventPresentationBuilder
{
    /**
     * @param array<Occurrence> $occurrences
     */
    public function build(
        Event $event,
        EventDetails $details,
        array $occurrences
    ): EventViewData {
        return new EventViewData(
            id: $event->id,
            title: $event->title,
            url: $this->permalink($event->id),
            image: $this->featuredImage($event->id),
            excerpt: $this->excerpt($event),
            artist: $details->artist,
            genre: $details->genre,
            venue: $details->venue,
            address: $details->address,
            mapsUrl: $details->mapsUrl,
            ticketUrl: $details->ticketUrl,
            ticketPrice: $details->ticketPrice,
            featured: $details->featured,
            dates: $this->occurrences($occurrences),
        );
    }

    /**
     * @param array<Occurrence> $occurrences
     * @return array<OccurrenceViewData>
     */
    private function occurrences(array $occurrences): array
    {
        $dates = [];

        foreach ($occurrences as $occurrence) {
            if (! $occurrence instanceof Occurrence) {
                continue;
            }

            try {
                $dates[] = OccurrenceViewData::from($occurrence);
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
            }
        }

        return $dates;
    }

    private function permalink(int $id): string
    {
        $url = get_permalink($id);

        return is_string($url) ? $url : '';
    }

    private function featuredImage(int $id): string
    {
        $image = get_the_post_thumbnail_url($id, 'large');

        return is_string($image) ? $image : '';
    }

    private function excerpt(Event $event): string
    {
        $post = get_post($event->id);

        if ($post instanceof WP_Post) {
            $excerpt = get_the_excerpt($post);

            if (is_string($excerpt) && trim($excerpt) !== '') {
                return $excerpt;
            }
        }

        return wp_trim_words(
            wp_strip_all_tags(strip_shortcodes($event->content)),
            35
        );
    }
}
