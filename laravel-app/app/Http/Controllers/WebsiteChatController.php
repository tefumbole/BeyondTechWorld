<?php

namespace App\Http\Controllers;

use App\Services\Assistant\AssistantRuntimeSettings;
use App\Services\WebsiteChatService;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebsiteChatController extends Controller
{
    protected $chat;

    public function __construct(WebsiteChatService $chat)
    {
        $this->chat = $chat;
    }

    public function session(Request $request)
    {
        if ($deny = $this->guardEnabled()) {
            return $deny;
        }
        if ($hit = $this->rateLimit('website_chat_session:'.$request->ip(), 20, 60)) {
            return $hit;
        }

        $result = $this->chat->openOrResume(
            $request->input('token'),
            $request->input('path') ?: $request->header('X-Page-Path')
        );

        return $this->jsonResult($result);
    }

    public function messages(Request $request)
    {
        if ($deny = $this->guardEnabled()) {
            return $deny;
        }
        if ($hit = $this->rateLimit('website_chat_poll:'.$request->ip(), 120, 60)) {
            return $hit;
        }

        $token = (string) $request->query('token', $request->input('token'));
        $after = (int) $request->query('after', $request->input('after', 0));
        $result = $this->chat->messages($token, $after);

        return $this->jsonResult($result);
    }

    public function postMessage(Request $request)
    {
        if ($deny = $this->guardEnabled()) {
            return $deny;
        }
        $ip = $request->ip();
        if ($hit = $this->rateLimit('website_chat_msg:'.$ip, 30, 60)) {
            return $hit;
        }
        $token = (string) $request->input('token');
        $dailyKey = 'website_chat_daily:'.($token ?: $ip).':'.date('Ymd');
        $daily = (int) Cache::get($dailyKey, 0);
        $dailyMax = max(20, (int) config('assistant.website_max_turns_per_day', 80));
        if ($daily >= $dailyMax) {
            return response()->json(['success' => false, 'error' => 'Daily message limit reached.'], 429);
        }

        $result = $this->chat->visitorMessage(
            $token,
            $request->input('body'),
            $request->input('path') ?: $request->header('X-Page-Path')
        );

        if (! empty($result['success'])) {
            Cache::put($dailyKey, $daily + 1, now()->endOfDay());
        }

        return $this->jsonResult($result);
    }

    public function minimize(Request $request)
    {
        $result = $this->chat->minimize((string) $request->input('token'));

        return response()->json($result);
    }

    public function config()
    {
        return response()->json([
            'success' => true,
            'enabled' => $this->chat->enabled(),
            'name' => $this->chat->assistantName(),
            'subtitle' => 'BeyondTechWorld Assistant',
            'greeting' => $this->chat->greetingText(),
            'greeting_delay_ms' => (int) WhatsAppSetting::getValue('website_ai_greeting_delay_ms', '600'),
            'handover_enabled' => AssistantRuntimeSettings::flag('website_ai_handover_enabled', true),
            'continue_whatsapp' => $this->chat->continueWhatsAppEnabled(),
            'avatar' => asset('branding/mbole-ai.png'),
        ]);
    }

    protected function guardEnabled()
    {
        if (! $this->chat->enabled()) {
            return response()->json(['success' => false, 'error' => 'Website assistant is disabled.'], 503);
        }

        return null;
    }

    protected function rateLimit($key, $max, $seconds)
    {
        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return response()->json(['success' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
        }
        Cache::put($key, $count + 1, now()->addSeconds($seconds));

        return null;
    }

    protected function jsonResult(array $result)
    {
        $code = isset($result['code']) ? (int) $result['code'] : (empty($result['success']) ? 400 : 200);
        unset($result['code']);

        return response()->json($result, $code);
    }
}
