<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcQuotation extends Model
{
    protected $table = 'nbc_quotations';

    protected $fillable = ['number', 'client_name', 'amount', 'status', 'body', 'author_id'];
}
