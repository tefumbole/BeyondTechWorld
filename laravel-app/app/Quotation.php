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
    /** Finalized without client signature (no approval link). */
    const STATUS_NO_SIGNATURE = 5;
    /** Client submitted a counter-quote; awaiting admin review. */
    const STATUS_CLIENT_QUOTE = 6;

    protected $fillable = [
        'reference_no',
        'user_id',
        'biller_id',
        'supplier_id',
        'customer_id',
        'cc_customer_ids',
        'warehouse_id',
        'item',
        'total_qty',
        'total_discount',
        'total_tax',
        'total_price',
        'order_tax_rate',
        'order_tax',
        'order_discount',
        'show_client_discount',
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
        'approval_sent_by',
    ];

    protected $dates = [
        'client_signed_at',
        'client_responded_at',
        'approval_sent_at',
    ];

    protected $casts = [
        'quotation_status' => 'integer',
    ];

    public static function statusLabel($status)
    {
        $map = [
            self::STATUS_PENDING => 'Draft',
            self::STATUS_AWAITING => 'Awaiting Client Signature',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_NO_SIGNATURE => 'No Signature Required',
            self::STATUS_CLIENT_QUOTE => 'Client Quote',
        ];

        return $map[(int) $status] ?? 'Unknown';
    }

    /** Statuses that can be converted to a sale. */
    public static function saleReadyStatuses()
    {
        return [self::STATUS_APPROVED, self::STATUS_NO_SIGNATURE];
    }

    public function hasClientSignature()
    {
        return ! empty($this->client_signed_at) || ! empty($this->client_signature_path);
    }

    /**
     * Staff who should get a WhatsApp copy: creator and the last person who sent it.
     *
     * @param  int|null  $extraUserId
     * @return \Illuminate\Support\Collection|User[]
     */
    public function staffCopyUsers($extraUserId = null)
    {
        $ids = [(int) $this->user_id];
        if (\Illuminate\Support\Facades\Schema::hasColumn('quotations', 'approval_sent_by')) {
            $ids[] = (int) $this->approval_sent_by;
        }
        $ids[] = (int) $extraUserId;
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return collect();
        }

        return User::whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', false);
            })
            ->get();
    }

    /** Still waiting for the client to sign — exclude already-signed or responded rows. */
    public function scopeAwaitingClientSignature($query)
    {
        return $query->where('quotation_status', self::STATUS_AWAITING)
            ->whereNull('client_signed_at')
            ->whereNull('client_responded_at')
            ->where(function ($q) {
                $q->whereNull('client_signature_path')
                    ->orWhere('client_signature_path', '');
            });
    }

    public function scopeApprovedOrSigned($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('quotation_status', self::saleReadyStatuses())
                ->orWhereNotNull('client_signed_at')
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('client_signature_path')
                        ->where('client_signature_path', '!=', '');
                });
        });
    }

    /**
     * If a client already signed but status was left (or reverted) to awaiting,
     * move those quotations onto Approved so they leave the awaiting list.
     */
    public static function promoteSignedAwaitingToApproved()
    {
        try {
            self::query()
                ->where('quotation_status', self::STATUS_AWAITING)
                ->where(function ($q) {
                    $q->whereNotNull('client_signed_at')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('client_signature_path')
                                ->where('client_signature_path', '!=', '');
                        });
                })
                ->update([
                    'quotation_status' => self::STATUS_APPROVED,
                    'client_approval_token' => null,
                ]);
        } catch (\Throwable $e) {
            \Log::warning('Quotation signed-status heal failed: '.$e->getMessage());
        }
    }

    public function isSaleReady()
    {
        return in_array((int) $this->quotation_status, self::saleReadyStatuses(), true)
            || $this->hasClientSignature();
    }

    public function statusLabelText()
    {
        return self::statusLabel($this->quotation_status);
    }

    public function quotes()
    {
        return $this->hasMany(QuotationQuote::class);
    }

    public function pendingQuote()
    {
        return $this->hasOne(QuotationQuote::class)
            ->where('status', QuotationQuote::STATUS_PENDING)
            ->latest('id');
    }

    public function latestQuote()
    {
        return $this->hasOne(QuotationQuote::class)->latest('id');
    }

    public function ensureApprovalToken()
    {
        if (! empty($this->client_approval_token)) {
            return $this->client_approval_token;
        }
        // Do not reopen a quotation the client already signed or responded to.
        if ($this->hasClientSignature()
            || ! empty($this->client_responded_at)
            || ! in_array((int) $this->quotation_status, [self::STATUS_AWAITING, self::STATUS_PENDING], true)
        ) {
            return $this->client_approval_token;
        }

        return $this->rotateApprovalToken();
    }

    /**
     * Always issue a fresh approval token so any previously sent link stops working.
     */
    public function rotateApprovalToken()
    {
        $this->client_approval_token = Str::random(48);
        $this->save();

        return $this->client_approval_token;
    }

    /**
     * Burn the public approval link after the client has responded.
     */
    public function invalidateApprovalToken()
    {
        $this->client_approval_token = null;
        $this->save();

        return $this;
    }

    public function isOpenForClientApproval()
    {
        return (int) $this->quotation_status === self::STATUS_AWAITING
            && ! empty($this->client_approval_token)
            && empty($this->client_responded_at)
            && ! $this->hasClientSignature();
    }

    public function approvalUrl()
    {
        $token = $this->ensureApprovalToken();

        return url('quotation-approval/'.$token);
    }

    /**
     * Absolute filesystem path for the stored client signature image.
     */
    public function clientSignatureAbsolutePath()
    {
        if (empty($this->client_signature_path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $this->client_signature_path), '/');
        if (strpos($path, 'public/') === 0) {
            $path = substr($path, strlen('public/'));
        }
        $full = public_path($path);

        return is_file($full) ? $full : null;
    }

    /**
     * Data-URI for DomPDF embedding of the client signature.
     */
    public function clientSignatureDataUri()
    {
        $full = $this->clientSignatureAbsolutePath();
        if (! $full) {
            return null;
        }
        $raw = @file_get_contents($full);
        if ($raw === false || $raw === '') {
            return null;
        }
        $mime = 'image/png';
        if (preg_match('/\.jpe?g$/i', $full)) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($raw);
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

    /** @return int[] */
    public function ccCustomerIdList()
    {
        if (empty($this->cc_customer_ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $this->cc_customer_ids))));
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
