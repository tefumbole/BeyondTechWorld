<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsPayment extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_payments';

    protected $guarded = ['id'];
}
