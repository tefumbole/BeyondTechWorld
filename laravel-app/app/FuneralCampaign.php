<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FuneralCampaign extends Model
{
    protected $table = 'funeral_campaigns';

    protected $fillable = [
        'slug', 'title', 'honoree_name', 'age', 'funeral_at',
        'target_amount', 'admin_name', 'admin_phone', 'enabled',
    ];

    protected $dates = ['funeral_at'];

    public function items()
    {
        return $this->hasMany(FuneralItem::class, 'campaign_id')->orderBy('sort');
    }

    public function pledges()
    {
        return $this->hasMany(FuneralPledge::class, 'campaign_id');
    }

    public function raisedAmount()
    {
        return (int) $this->pledges
            ->whereIn('status', [FuneralPledge::STATUS_PLEDGED, FuneralPledge::STATUS_PAID])
            ->sum('amount');
    }

    public function raisedPercent()
    {
        $target = (int) $this->target_amount;
        if ($target <= 0) {
            return 0;
        }

        return min(100, round(($this->raisedAmount() / $target) * 100, 1));
    }
}
