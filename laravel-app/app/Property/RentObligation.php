<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class RentObligation extends Model
{
    protected $guarded = [];

    public function balance()
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }
}
