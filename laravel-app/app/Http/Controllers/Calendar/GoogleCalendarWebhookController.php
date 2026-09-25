<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\Request;

class GoogleCalendarWebhookController extends Controller
{
    public function handle(Request $request, CalendarService $calendar)
    {
        $result = $calendar->applyNotification(
            $request->header('X-Goog-Channel-Token'),
            (string) $request->header('X-Goog-Resource-State'),
            (string) $request->header('X-Goog-Resource-URI')
        );
        if (! empty($result['error']) && $result['error'] === 'unauthorized') {
            return response()->json(['ok' => false], 403);
        }

        return response()->json(['ok' => ! empty($result['success'])]);
    }
}
