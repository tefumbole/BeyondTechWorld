<?php

namespace App\Nbc;

class NbcQuotation extends NbcModel
{
    protected $table = 'nbc_quotations';

    protected $fillable = ['number', 'client_name', 'amount', 'status', 'body', 'author_id'];
}
