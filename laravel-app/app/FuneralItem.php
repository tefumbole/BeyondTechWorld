<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FuneralItem extends Model
{
    protected $table = 'funeral_items';

    protected $fillable = [
        'campaign_id', 'category', 'name', 'target_amount', 'is_open', 'sort',
    ];

    public function campaign()
    {
        return $this->belongsTo(FuneralCampaign::class, 'campaign_id');
    }

    public function pledges()
    {
        return $this->hasMany(FuneralPledge::class, 'item_id');
    }

    public function committedAmount()
    {
        return (int) $this->pledges
            ->whereIn('status', [FuneralPledge::STATUS_PLEDGED, FuneralPledge::STATUS_PAID])
            ->sum('amount');
    }

    public function remainingAmount()
    {
        if ($this->is_open || $this->target_amount === null) {
            return null;
        }

        return max(0, (int) $this->target_amount - $this->committedAmount());
    }

    public function isCovered()
    {
        if ($this->is_open || $this->target_amount === null) {
            return false;
        }

        return $this->remainingAmount() <= 0;
    }

    public function pledgerFirstNames()
    {
        $names = [];
        foreach ($this->pledges as $pledge) {
            if (! in_array($pledge->status, [FuneralPledge::STATUS_PLEDGED, FuneralPledge::STATUS_PAID], true)) {
                continue;
            }
            $first = trim((string) preg_split('/\s+/', trim((string) $pledge->name), 2)[0]);
            if ($first !== '' && ! in_array($first, $names, true)) {
                $names[] = $first;
            }
        }

        return $names;
    }
}
