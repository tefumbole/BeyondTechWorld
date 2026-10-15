<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotMessageTemplate extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_message_templates';

    protected $fillable = ['key', 'body', 'hours_before'];

    public static function defaults()
    {
        return [
            'receipt' => [
                'hours_before' => null,
                'body' => "{company}\nWi-Fi receipt\nPackage: {package}\nAmount: {amount} XAF\nUsername: {username}\nPassword: {password}\nExpires: {expires}\nHelp: {support}",
            ],
            'expiry' => [
                'hours_before' => null,
                'body' => "{company}\nYour Wi-Fi access has ended.\nPackage: {package}\nBuy again: {portal}",
            ],
            'data_cap' => [
                'hours_before' => null,
                'body' => "{company}\nYou have used the data included in {package}. The session is now closed.\nBuy again: {portal}",
            ],
            'reminder' => [
                'hours_before' => 1,
                'body' => "{company}\nYour Wi-Fi ({package}) expires at {expires}.\nBuy again: {portal}",
            ],
        ];
    }
}
