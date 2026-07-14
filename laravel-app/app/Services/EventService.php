<?php

namespace App\Services;

use App\Event;
use App\EventPublication;
use App\EventStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function create(array $data, $publishOnWebsite = true)
    {
        return DB::transaction(function () use ($data, $publishOnWebsite) {
            $event = new Event($data);
            $event->created_by = Auth::id();
            $event->updated_by = Auth::id();
            if (empty($event->internal_status)) {
                $event->internal_status = 'draft';
            }
            $event->save();

            $pubData = [
                'event_id' => $event->id,
                'public_title' => $event->name,
                'public_summary' => $event->internal_description,
                'public_venue' => $event->venue,
                'public_location' => trim(($event->city ?: '') . ($event->venue_address ? ', ' . $event->venue_address : ''), ', '),
                'public_contact_name' => $event->client_contact_person,
                'public_contact_phone' => $event->client_telephone,
                'public_contact_email' => $event->client_email,
                'show_event_time' => true,
                'show_countdown' => true,
                'countdown_target_type' => 'event_start_at',
            ];

            if ($publishOnWebsite) {
                $pubData['publish_on_website'] = true;
                $pubData['publication_status'] = 'published';
                $pubData['visibility_at'] = now();
            } else {
                $pubData['publish_on_website'] = false;
                $pubData['publication_status'] = 'draft';
            }

            EventPublication::create($pubData);

            $this->recordStatus($event, null, $event->internal_status, 'Event created');

            return $event->fresh(['customer', 'booking', 'publication']);
        });
    }

    public function update(Event $event, array $data, $statusNote = null)
    {
        return DB::transaction(function () use ($event, $data, $statusNote) {
            $previousStatus = $event->internal_status;
            $data['updated_by'] = Auth::id();

            if (! empty($data['name']) && empty($data['slug'])) {
                $data['slug'] = Event::uniqueSlug($data['name'], $event->id);
            }

            $event->fill($data);
            $event->save();

            if (isset($data['internal_status']) && $data['internal_status'] !== $previousStatus) {
                $this->recordStatus($event, $previousStatus, $data['internal_status'], $statusNote);
            }

            return $event->fresh(['customer', 'booking', 'publication']);
        });
    }

    public function recordStatus(Event $event, $previous, $new, $note = null)
    {
        EventStatusHistory::create([
            'event_id' => $event->id,
            'previous_status' => $previous,
            'new_status' => $new,
            'changed_by' => Auth::id(),
            'note' => $note,
            'changed_at' => now(),
        ]);
    }

    public function storeFlyer($file)
    {
        $dir = public_path('images/events');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $file->move($dir, $name);

        return 'images/events/' . $name;
    }

    public function dashboardStats()
    {
        $today = now()->startOfDay();
        $weekAhead = now()->addDays(7)->endOfDay();

        $base = Event::query();

        return [
            'total_upcoming' => (clone $base)->whereIn('internal_status', [
                'planning', 'approved', 'team_confirmed', 'ready_for_event',
            ])->where('event_start_at', '>=', $today)->count(),
            'today' => (clone $base)->whereDate('event_start_at', $today)->count(),
            'in_progress' => (clone $base)->where('internal_status', 'event_in_progress')->count(),
            'completed' => (clone $base)->where('internal_status', 'completed')->count(),
            'cancelled' => (clone $base)->where('internal_status', 'cancelled')->count(),
            'postponed' => (clone $base)->where('internal_status', 'postponed')->count(),
            'draft' => (clone $base)->where('internal_status', 'draft')->count(),
            'starting_soon' => (clone $base)->whereBetween('event_start_at', [$today, $weekAhead])
                ->whereNotIn('internal_status', ['cancelled', 'completed'])->count(),
            'published_public' => EventPublication::where('publication_status', 'published')
                ->where('publish_on_website', true)->count(),
            'draft_public' => EventPublication::where('publication_status', 'draft')->count(),
        ];
    }
}
