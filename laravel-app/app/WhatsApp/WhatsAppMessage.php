<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    const DIR_IN = 'INCOMING';
    const DIR_OUT = 'OUTGOING';

    const STATUS_QUEUED = 'QUEUED';
    const STATUS_SENT = 'SENT';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_READ = 'READ';
    const STATUS_PLAYED = 'PLAYED';
    const STATUS_FAILED = 'FAILED';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id', 'contact_id', 'direction', 'type', 'provider_message_id',
        'body', 'media_json', 'status', 'sender_type', 'sender_user_id',
        'queued_at', 'sent_at', 'delivered_at', 'read_at', 'played_at', 'failed_at', 'error',
    ];

    protected $dates = [
        'queued_at', 'sent_at', 'delivered_at', 'read_at', 'played_at', 'failed_at',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function media()
    {
        $raw = $this->media_json;
        if (! $raw) {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function chatMediaUrl()
    {
        $media = $this->media();
        $url = isset($media['url']) ? trim((string) $media['url']) : '';
        if ($url === '') {
            return '';
        }
        if (strpos($url, 'whatsapp-chat/') === 0) {
            return asset('public/'.$url);
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return '';
    }

    public function chatMediaName()
    {
        $media = $this->media();
        if (! empty($media['file_name'])) {
            return (string) $media['file_name'];
        }
        if (! empty($media['path'])) {
            return (string) $media['path'];
        }

        return '';
    }

    public function ticks()
    {
        if ($this->direction !== self::DIR_OUT) {
            return '';
        }
        if ($this->status === self::STATUS_FAILED) {
            return 'failed';
        }
        if (in_array($this->status, [self::STATUS_READ, self::STATUS_PLAYED], true)) {
            return 'read';
        }
        if ($this->status === self::STATUS_DELIVERED) {
            return 'delivered';
        }
        if ($this->status === self::STATUS_SENT) {
            return 'sent';
        }

        return 'queued';
    }
}
