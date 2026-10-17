<?php

namespace App\Gbhs;

use Illuminate\Database\Eloquent\Model;

class CustomerGroupMembership extends Model
{
    protected $table = 'customer_group_memberships';

    protected $guarded = ['id'];
}
