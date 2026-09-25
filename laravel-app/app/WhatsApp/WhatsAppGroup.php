<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGroup extends Model
{
    const OFF = 'OFF';
    const MONITOR = 'MONITOR';
    const MENTION = 'MENTION_ONLY';
    const ACTIVE = 'ACTIVE';

    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'group_jid', 'name', 'description', 'enabled', 'mode', 'category', 'organization',
        'raw_retention_days', 'summary_retention_days', 'action_retention_days',
        'allow_inventory', 'allow_events', 'allow_finance', 'allow_payroll', 'allow_internship',
        'memory_json', 'last_summary_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'allow_inventory' => 'boolean',
        'allow_events' => 'boolean',
        'allow_finance' => 'boolean',
        'allow_payroll' => 'boolean',
        'allow_internship' => 'boolean',
    ];

    protected $dates = ['last_summary_at'];

    public function messages()
    {
        return $this->hasMany(WhatsAppGroupMessage::class, 'group_id');
    }

    public function memory()
    {
        $decoded = json_decode((string) $this->memory_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setMemory(array $memory)
    {
        $this->memory_json = json_encode($memory);
    }
}
