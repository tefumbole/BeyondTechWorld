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
    const STATUS_HIDDEN = 'HIDDEN';

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

    public function translationMeta()
    {
        $media = $this->media();

        return isset($media['translation']) && is_array($media['translation']) ? $media['translation'] : [];
    }

    /** Text the agent reads. Incoming foreign text is the English translation. */
    public function hubText()
    {
        if ($this->wasDeleted()) {
            return $this->direction === self::DIR_OUT ? 'You deleted this message' : 'This message was deleted';
        }
        $meta = $this->translationMeta();
        if ($this->direction === self::DIR_IN && ! empty($meta['english'])) {
            return (string) $meta['english'];
        }
        if ($this->direction === self::DIR_OUT && ! empty($meta['staff'])) {
            return (string) $meta['staff'];
        }

        $preview = self::listPreview((string) $this->body);
        $type = strtoupper((string) $this->type);
        if (in_array($type, ['IMAGE', 'STICKER'], true) && ($preview === '' || $preview === 'Photo' || $preview === 'Sticker')) {
            return '';
        }
        if ($type === 'VIDEO' && ($preview === '' || $preview === 'Video')) {
            return '';
        }
        if ($type === 'AUDIO' && ($preview === '' || $preview === 'Voice message')) {
            return '';
        }

        return $preview;
    }

    /** Chat-list wording. Authentication templates arrive as a locked placeholder. */
    public static function listPreview($preview)
    {
        $text = trim((string) $preview);
        $labels = [
            '[IMAGE]' => 'Photo',
            '[STICKER]' => 'Sticker',
            '[ALBUM]' => 'Album',
            '[EDIT]' => 'Edited message',
            '[AUDIO]' => 'Voice message',
            '[VOICE]' => 'Voice message',
            '[VIDEO]' => 'Video',
            '[DOCUMENT]' => 'Document',
            '[LOCATION]' => 'Location',
        ];
        $key = strtoupper($text);
        if (isset($labels[$key])) {
            return $labels[$key];
        }
        if ($key === 'PHOTO') {
            return 'Photo';
        }
        $plain = strtoupper(preg_replace('/[^A-Za-z]+/', '', $text));
        if ($plain === 'AUTHENTICATION') {
            return 'Verification code';
        }

        return $text;
    }

    /** Original or sent wording, shown under the English when it differs. */
    public function hubAside()
    {
        if ($this->wasDeleted()) {
            return '';
        }
        $meta = $this->translationMeta();
        if ($this->direction === self::DIR_IN && ! empty($meta['english']) && trim((string) $this->body) !== trim((string) $meta['english'])) {
            $name = ! empty($meta['language_name']) ? $meta['language_name'] : 'original';

            return $name.': '.$this->body;
        }
        if ($this->direction === self::DIR_OUT && ! empty($meta['staff']) && trim((string) $this->body) !== trim((string) $meta['staff'])) {
            $name = ! empty($meta['language_name']) ? $meta['language_name'] : 'their language';

            return 'Sent in '.$name.': '.$this->body;
        }

        return '';
    }

    public function chatMediaUrl()
    {
        $media = $this->media();
        $stored = isset($media['stored']) ? trim((string) $media['stored']) : '';
        if ($stored !== '' && strpos($stored, 'whatsapp-media/') === 0) {
            return route('whatsapp.chats.media', $this->id);
        }
        $url = isset($media['url']) ? trim((string) $media['url']) : '';
        if ($url === '') {
            return '';
        }
        if (strpos($url, 'whatsapp-chat/') === 0) {
            return asset('public/'.$url);
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host !== '' && stripos($host, 'whatsapp.net') !== false) {
            return route('whatsapp.chats.media', $this->id);
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return '';
    }

    public function mediaReason()
    {
        $media = $this->media();
        if (! empty($media['reason'])) {
            return (string) $media['reason'];
        }
        if (! empty($media['unavailable']) && $this->chatMediaUrl() === '') {
            return 'WhatsApp did not send this file to MAI. It is still on the phone.';
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

    public function scopeVisible($query)
    {
        return $query->where('status', '!=', self::STATUS_HIDDEN);
    }

    public function wasenderId()
    {
        $media = $this->media();
        if (! empty($media['wasender_id']) && preg_match('/^\d+$/', (string) $media['wasender_id'])) {
            return (string) $media['wasender_id'];
        }
        if (preg_match('/^\d+$/', (string) $this->provider_message_id)) {
            return (string) $this->provider_message_id;
        }

        return '';
    }

    public function wasDeleted()
    {
        return ! empty($this->media()['deleted']);
    }

    public function wasEdited()
    {
        return ! empty($this->media()['edited']);
    }

    public function isStarred()
    {
        return ! empty($this->media()['starred']);
    }

    public function isForwarded()
    {
        return ! empty($this->media()['forwarded']);
    }

    public function quoteText()
    {
        $quote = $this->media()['quote'] ?? null;
        if (! is_array($quote) || empty($quote['text'])) {
            return '';
        }

        return (string) $quote['text'];
    }

    public function isWebsiteMessage()
    {
        return strpos((string) $this->provider_message_id, 'webmsg:') === 0;
    }

    public function withinWhatsAppWindow()
    {
        $at = $this->sent_at ?: $this->created_at;

        return $at && $at->gt(now()->subMinutes(15));
    }

    public function canEditOnWhatsApp()
    {
        return $this->direction === self::DIR_OUT
            && strtoupper((string) $this->type) === 'TEXT'
            && ! $this->wasDeleted()
            && $this->status !== self::STATUS_FAILED
            && $this->withinWhatsAppWindow()
            && ($this->isWebsiteMessage() || $this->wasenderId() !== '' || $this->maiKey() !== '');
    }

    public function canDeleteForEveryone()
    {
        return $this->direction === self::DIR_OUT
            && ! $this->wasDeleted()
            && $this->withinWhatsAppWindow()
            && ($this->isWebsiteMessage() || $this->wasenderId() !== '' || $this->maiKey() !== '');
    }

    public function maiKey()
    {
        $key = $this->media()['key'] ?? null;
        if (! is_array($key) || empty($key['id'])) {
            return '';
        }

        return (string) $key['id'];
    }
}
