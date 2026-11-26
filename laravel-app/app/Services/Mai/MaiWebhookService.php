<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Mai\MaiWebhook;
use App\Mai\MaiWebhookDelivery;

class MaiWebhookService
{
    public function register($user, $url)
    {
        if (! app(MaiUrlGuard::class)->allows($url)) {
            throw new \InvalidArgumentException('That callback address is not allowed.');
        }
        $hook = new MaiWebhook();
        $hook->user_id = $user->id;
        $hook->url = substr((string) $url, 0, 500);
        $hook->secret = encrypt(bin2hex(random_bytes(24)));
        $hook->active = 1;
        $hook->save();

        return $hook;
    }

    public function dispatch(MaiSession $session, $eventId, array $payload)
    {
        $hooks = MaiWebhook::where('user_id', $session->user_id)->where('active', 1)->get();
        foreach ($hooks as $hook) {
            if ($hook->mai_session_id && (int) $hook->mai_session_id !== (int) $session->id) {
                continue;
            }
            if (! app(MaiUrlGuard::class)->allows($hook->url)) {
                continue;
            }
            $delivery = MaiWebhookDelivery::where('mai_webhook_id', $hook->id)->where('event_id', $eventId)->first();
            if ($delivery) {
                continue;
            }
            $delivery = new MaiWebhookDelivery();
            $delivery->mai_webhook_id = $hook->id;
            $delivery->event_id = substr((string) $eventId, 0, 80);
            $delivery->payload = json_encode($payload);
            $delivery->attempts = 0;
            $delivery->save();
        }
        $this->deliverDue();
    }

    public function deliverDue()
    {
        $due = MaiWebhookDelivery::whereNull('delivered_at')
            ->where('attempts', '<', 5)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->limit(20)
            ->get();
        foreach ($due as $delivery) {
            $this->send($delivery);
        }
    }

    protected function send(MaiWebhookDelivery $delivery)
    {
        $hook = MaiWebhook::find($delivery->mai_webhook_id);
        if (! $hook || ! $hook->active || ! app(MaiUrlGuard::class)->allows($hook->url)) {
            $delivery->attempts = 5;
            $delivery->last_error = 'Callback was rejected.';
            $delivery->save();

            return;
        }
        $body = (string) $delivery->payload;
        $signature = hash_hmac('sha256', $body, $hook->secretPlain());
        $sender = config('cloud.mai_customer.webhook_sender');
        $status = 0;
        if (is_callable($sender)) {
            $status = (int) call_user_func($sender, $hook->url, $body, [
                'X-Mai-Event-Id' => $delivery->event_id,
                'X-Mai-Signature' => 'sha256='.$signature,
            ]);
        }
        $delivery->attempts = (int) $delivery->attempts + 1;
        if ($status >= 200 && $status < 300) {
            $delivery->delivered_at = now();
            $delivery->last_error = null;
        } else {
            $delivery->last_error = 'Callback returned '.$status;
            $delivery->next_attempt_at = now()->addMinutes(min(30, $delivery->attempts * 2));
        }
        $delivery->save();
    }
}
