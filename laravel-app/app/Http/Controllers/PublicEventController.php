<?php

namespace App\Http\Controllers;

use App\Event;
use App\Services\EventPublicationService;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function index(Request $request, EventPublicationService $pubService)
    {
        $pubService->processScheduledPublications();

        $query = $pubService->publishedQuery();

        if ($request->filled('type')) {
            $query->where('event_type', $request->type);
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', $q)->orWhereHas('publication', function ($p) use ($q) {
                    $p->where('public_title', 'like', $q)->orWhere('public_summary', 'like', $q);
                });
            });
        }
        if ($request->filled('location')) {
            $query->whereHas('publication', function ($p) use ($request) {
                $p->where('public_location', 'like', '%' . $request->location . '%');
            });
        }

        $now = now();
        $filter = $request->get('filter', 'upcoming');

        if ($filter === 'featured') {
            $query->whereHas('publication', function ($p) {
                $p->where('is_featured', true);
            });
        } elseif ($filter === 'ongoing') {
            $query->where('event_start_at', '<=', $now)
                ->where(function ($w) use ($now) {
                    $w->whereNull('event_end_at')->orWhere('event_end_at', '>=', $now);
                });
        } elseif ($filter === 'past') {
            $query->whereNotNull('event_end_at')->where('event_end_at', '<', $now);
        } else {
            // Upcoming / default: future, ongoing, or published with no date yet
            $query->where(function ($w) use ($now) {
                $w->whereNull('event_start_at')
                    ->orWhere('event_start_at', '>=', $now)
                    ->orWhere(function ($x) use ($now) {
                        $x->where('event_start_at', '<=', $now)
                            ->where(function ($y) use ($now) {
                                $y->whereNull('event_end_at')->orWhere('event_end_at', '>=', $now);
                            });
                    });
            });
        }

        $events = $query->orderBy('event_start_at')->get()->map(function (Event $event) use ($pubService) {
            $pub = $event->publication;

            return [
                'event' => $event,
                'pub' => $pub,
                'flyer' => $pubService->publicFlyerUrl($event, $pub),
                'public_status' => $pubService->computePublicStatus($event, $pub),
                'countdown_at' => $pubService->resolveCountdownTargetAt($event, $pub),
            ];
        });

        $featured = $pubService->publishedQuery()
            ->whereHas('publication', function ($p) {
                $p->where('is_featured', true);
            })
            ->orderBy('event_start_at')
            ->limit(4)
            ->get();

        return view('beyond.events', [
            'events' => $events,
            'featured' => $featured,
            'filter' => $filter,
            'pubService' => $pubService,
        ]);
    }

    public function show($slug, EventPublicationService $pubService)
    {
        $pubService->processScheduledPublications();

        $event = Event::with('publication')->where('slug', $slug)->firstOrFail();
        $pub = $event->publication;

        if (! $pub || ! $pubService->isPubliclyVisible($pub)) {
            abort(404);
        }

        $publicStatus = $pubService->computePublicStatus($event, $pub);
        $countdownAt = $pubService->resolveCountdownTargetAt($event, $pub);

        $related = $pubService->publishedQuery()
            ->where('id', '!=', $event->id)
            ->where('event_type', $event->event_type)
            ->orderBy('event_start_at')
            ->limit(3)
            ->get();

        return view('beyond.event_detail', [
            'event' => $event,
            'pub' => $pub,
            'flyer' => $pubService->publicFlyerUrl($event, $pub),
            'publicStatus' => $publicStatus,
            'countdownAt' => $countdownAt,
            'related' => $related,
            'pubService' => $pubService,
        ]);
    }

    public function apiList(EventPublicationService $pubService)
    {
        $pubService->processScheduledPublications();

        $items = $pubService->publishedQuery()
            ->orderBy('event_start_at')
            ->get()
            ->map(function (Event $event) use ($pubService) {
                $pub = $event->publication;

                return [
                    'slug' => $event->slug,
                    'title' => $pub->public_title ?: $event->name,
                    'summary' => $pub->public_summary,
                    'event_type' => $event->event_type,
                    'event_start_at' => optional($event->event_start_at)->toIso8601String(),
                    'venue' => $pub->public_venue,
                    'location' => $pub->public_location,
                    'flyer' => $pubService->publicFlyerUrl($event, $pub),
                    'is_featured' => (bool) $pub->is_featured,
                    'public_status' => $pubService->computePublicStatus($event, $pub),
                    'url' => url('/events/' . $event->slug),
                ];
            });

        return response()->json(['events' => $items]);
    }

    public function apiShow($slug, EventPublicationService $pubService)
    {
        $event = Event::with('publication')->where('slug', $slug)->firstOrFail();
        $pub = $event->publication;
        if (! $pub || ! $pubService->isPubliclyVisible($pub)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'slug' => $event->slug,
            'title' => $pub->public_title,
            'summary' => $pub->public_summary,
            'description' => $pub->public_description,
            'event_type' => \App\Event::TYPES[$event->event_type] ?? $event->event_type,
            'event_start_at' => optional($event->event_start_at)->toIso8601String(),
            'event_end_at' => optional($event->event_end_at)->toIso8601String(),
            'venue' => $pub->public_venue,
            'location' => $pub->public_location,
            'flyer' => $pubService->publicFlyerUrl($event, $pub),
            'registration_url' => $pub->registration_url,
            'ticket_url' => $pub->ticket_url,
            'public_status' => $pubService->computePublicStatus($event, $pub),
            'countdown' => $pub->show_countdown ? [
                'target' => optional($pubService->resolveCountdownTargetAt($event, $pub))->toIso8601String(),
                'timezone' => $event->timezone,
                'completion_message' => $pub->countdown_completion_message,
            ] : null,
        ]);
    }
}
