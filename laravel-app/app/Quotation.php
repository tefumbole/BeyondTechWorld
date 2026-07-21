<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Quotation extends Model
{
    const STATUS_PENDING = 1;
    const STATUS_AWAITING = 2;
    const STATUS_APPROVED = 3;
    const STATUS_REJECTED = 4;

    protected $fillable = [
        'reference_no',
        'user_id',
        'biller_id',
        'supplier_id',
        'customer_id',
        'warehouse_id',
        'item',
        'total_qty',
        'total_discount',
        'total_tax',
        'total_price',
        'order_tax_rate',
        'order_tax',
        'order_discount',
        'shipping_cost',
        'grand_total',
        'quotation_status',
        'document',
        'note',
        'client_approval_token',
        'client_signature_path',
        'client_signed_at',
        'client_comment',
        'client_responded_at',
        'approval_sent_at',
    ];

    protected $dates = [
        'client_signed_at',
        'client_responded_at',
        'approval_sent_at',
    ];

    public static function statusLabel($status)
    {
        $map = [
            self::STATUS_PENDING => 'Draft',
            self::STATUS_AWAITING => 'Awaiting Client Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];

        return $map[(int) $status] ?? 'Unknown';
    }

    public function statusLabelText()
    {
        return self::statusLabel($this->quotation_status);
    }

    public function ensureApprovalToken()
    {
        if (empty($this->client_approval_token)) {
            $this->client_approval_token = Str::random(48);
            $this->save();
        }

        return $this->client_approval_token;
    }

    public function approvalUrl()
    {
        $token = $this->ensureApprovalToken();

        return url('quotation-approval/'.$token);
    }

    /**
     * Public URL for the stored client signature (docroot is laravel-app/).
     */
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

    public function biller()
    {
        return $this->belongsTo('App\Biller');
    }

    public function customer()
    {
        return $this->belongsTo('App\Customer');
    }

    public function supplier()
    {
        return $this->belongsTo('App\Supplier');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function warehouse()
    {
        return $this->belongsTo('App\Warehouse');
    }
}
