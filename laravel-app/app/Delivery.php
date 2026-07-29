<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Delivery extends Model
{
    protected $fillable = [
        'reference_no',
        'sale_id',
        'user_id',
        'address',
        'delivered_by',
        'recieved_by',
        'delivered_by_customer_id',
        'received_by_customer_id',
        'file',
        'status',
        'note',
        'client_signature_token',
        'client_signature_path',
        'client_signed_at',
        'signature_sent_at',
        'signer_name',
    ];

    protected $dates = [
        'client_signed_at',
        'signature_sent_at',
    ];

    public function sale()
    {
        return $this->belongsTo('App\Sale');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function deliveredByCustomer()
    {
        return $this->belongsTo('App\Customer', 'delivered_by_customer_id');
    }

    public function receivedByCustomer()
    {
        return $this->belongsTo('App\Customer', 'received_by_customer_id');
    }

    public function isSignaturePending()
    {
        return empty($this->client_signed_at);
    }

    public function isSigned()
    {
        return ! empty($this->client_signed_at);
    }

    public function ensureSignatureToken()
    {
        if (empty($this->client_signature_token)) {
            return $this->rotateSignatureToken();
        }

        return $this->client_signature_token;
    }

    public function rotateSignatureToken()
    {
        $this->client_signature_token = Str::random(48);
        $this->save();

        return $this->client_signature_token;
    }

    public function invalidateSignatureToken()
    {
        $this->client_signature_token = null;
        $this->save();

        return $this;
    }

    public function isOpenForClientSignature()
    {
        return empty($this->client_signed_at)
            && ! empty($this->client_signature_token);
    }

    public function signatureUrl()
    {
        $token = $this->ensureSignatureToken();

        return url('delivery-sign/'.$token);
    }

    public function clientSignatureUrl()
    {
        if (empty($this->client_signature_path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $this->client_signature_path), '/');
        if (strpos($path, 'public/') === 0) {
            return url($path);
        }

        return url('public/'.$path);
    }

    public function statusLabel()
    {
        if ((int) $this->status === 1) {
            return 'Packing';
        }
        if ((int) $this->status === 2) {
            return 'Delivering';
        }

        return 'Delivered';
    }
}
