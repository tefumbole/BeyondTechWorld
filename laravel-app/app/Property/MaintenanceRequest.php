<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $table = 'property_maintenance_requests';

    protected $guarded = [];
}
