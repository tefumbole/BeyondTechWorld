<?php

namespace App\Services;

use App\Event;
use App\EventPublication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventPublicationService
{
    const COUNTDOWN_TARGETS = [
        'loading_at' => 'Equipment loading',
        'setup_start_at' => 'Setup start',
        'event_start_at' => 'Event start',
        'event_end_at' => 'Event end',
        'custom' => 'Custom date & time',
    ];

    const PUBLIC_STATUSES = [
        'coming_soon' => 'Coming Soon',
        'setup_in_progress' => 'Setup in Progress',
        'happening_today' => 'Happening Today',
        'event_in_progress' => 'Event in Progress',
        'completed' => 'Completed',
        'postponed' => 'Postponed',
        'cancelled' => 'Cancelled',
    ];

    /** Events visible on the public website right now. */
    public function publishedQuery()
    {
        $now = now();

        return Event::with('publication')
            ->whereHas('publication', function ($q) use ($now) {
                $q->where('publish_on_website', true)
                    ->where('publication_status', 'published')
                    ->where(function ($w) use ($now) {
                        $w->whereNull('visibility_at')->orWhere('visibility_at', '<=', $now);
                    })
                    ->where(function ($w) use ($now) {
                        $w->whereNull('unpublish_at')->orWhere('unpublish_at', '>', $now);
                    });
            });
    }

    public function isPubliclyVisible(EventPublication $pub)
    {
        if (! $pub->publish_on_website || $pub->publication_status !== 'published') {
            return false;
        }
        $now = now();
        if ($pub->visibility_at && $pub->visibility_at->gt($now)) {
            return false;
        }
        if ($pub->unpublish_at && $pub->unpublish_at->lte($now)) {
            return false;
        }

        return true;
    }

    public function savePublication(Event $event, array $data, $publicFlyerFile = null)
    {
        return DB::transaction(function () use ($event, $data, $publicFlyerFile) {
            $pub = $event->publication ?: new EventPublication(['event_id' => $event->id]);

            if ($publicFlyerFile && $publicFlyerFile->isValid()) {
                $data['public_flyer_path'] = app(EventService::class)->storeFlyer($publicFlyerFile);
            }

            $pub->fill($data);

            if ($pub->publish_on_website && $pub->visibility_at && $pub->visibility_at->isFuture()) {
                $pub->publication_status = 'scheduled';
            } elseif ($pub->publish_on_website && $pub->publication_status === 'scheduled' && (! $pub->visibility_at || $pub->visibility_at->isPast())) {
                // keep scheduled until cron or manual publish
            }

            $pub->save();

            if (! empty($data['public_slug']) && $data['public_slug'] !== $event->slug) {
                $event->slug = Event::uniqueSlug($data['public_slug'], $event->id);
                $event->save();
            }

            return $pub->fresh();
        });
    }

    public function publish(Event $event)
    {
        $pub = $event->publication;
        if (! $pub) {
            throw new \InvalidArgumentException('Publication record missing.');
        }
        if (empty($pub->public_title) || empty($pub->public_description) || ! $event->event_start_at) {
            throw new \InvalidArgumentException('Public title, description and event start date are required before publishing.');
        }
        if (! $pub->public_flyer_path && ! $event->flyer_path) {
            throw new \InvalidArgumentException('A cover image is required before publishing.');
        }

        $pub->publish_on_website = true;
        $pub->publication_status = 'published';
        if (! $pub->visibility_at || $pub->visibility_at->isFuture()) {
            $pub->visibility_at = now();
        }
        $pub->save();

        return $pub;
    }

    public function unpublish(Event $event)
    {
        $pub = $event->publication;
        if ($pub) {
            $pub->publication_status = 'unpublished';
            $pub->save();
        }

        return $pub;
    }

    /** Promote scheduled publications whose visibility date has passed. */
    public function processScheduledPublications()
    {
        $count = 0;
        EventPublication::where('publication_status', 'scheduled')
            ->where('publish_on_website', true)
            ->whereNotNull('visibility_at')
            ->where('visibility_at', '<=', now())
            ->chunk(50, function ($rows) use (&$count) {
                foreach ($rows as $pub) {
                    $pub->publication_status = 'published';
                    $pub->save();
                    $count++;
                }
            });

        return $count;
    }

    public function resolveCountdownTargetAt(Event $event, EventPublication $pub)
    {
        if (! $pub->show_countdown) {
            return null;
        }
        if ($pub->countdown_visible_from && $pub->countdown_visible_from->isFuture()) {
            return null;
        }

        $type = $pub->countdown_target_type ?: 'event_start_at';
        if ($type === 'custom') {
            return $pub->countdown_custom_at;
        }

        return $event->$type ?? null;
    }

    public function computePublicStatus(Event $event, EventPublication $pub)
    {
        if ($pub->public_status_override) {
            return $pub->public_status_override;
        }
        if ($event->internal_status === 'cancelled') {
            return 'cancelled';
        }
        if ($event->internal_status === 'postponed') {
            return 'postponed';
        }

        $tz = $event->timezone ?: 'Africa/Kigali';
        $now = Carbon::now($tz);

        if ($event->event_end_at && $now->gt($event->event_end_at->timezone($tz))) {
            return 'completed';
        }
        if ($event->internal_status === 'event_in_progress') {
            return 'event_in_progress';
        }
        if ($event->event_start_at && $now->isSameDay($event->event_start_at->timezone($tz))) {
            if ($now->gte($event->event_start_at->timezone($tz)) && (! $event->event_end_at || $now->lt($event->event_end_at->timezone($tz)))) {
                return 'event_in_progress';
            }

            return 'happening_today';
        }
        if ($event->setup_start_at && $now->gte($event->setup_start_at->timezone($tz))
            && $event->event_start_at && $now->lt($event->event_start_at->timezone($tz))) {
            return 'setup_in_progress';
        }

        return 'coming_soon';
    }

    public function publicFlyerUrl(Event $event, EventPublication $pub)
    {
        $path = $pub->public_flyer_path ?: $event->flyer_path;
        if (! $path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $path) || strpos($path, '/') === 0) {
            return $path;
        }

        return url('public/' . ltrim($path, '/'));
    }

    public function sanitizePublicHtml($html)
    {
        return strip_tags($html, '<p><br><strong><em><b><i><ul><ol><li><a><h2><h3><span>');
    }
}
