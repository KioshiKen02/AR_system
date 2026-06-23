<?php

namespace App\Models\UtilityModels;

use Illuminate\Database\Eloquent\Model;

class WHTClearedItems extends Model
{
    protected $connection = 'tenant';
    protected $table = "wht_cleared_items";

    protected $fillable = [
        'wht_clearing_no',
        'payment_no',
        'wht_no',
        'type',
        'document_no',
        'receipt_date',
        'amount',
        'status',
        'remarks',
    ];

    public function whtCleared()
    {
        return $this->belongsTo(WHTCleared::class, 'wht_clearing_no', 'wht_clearing_no');
    }
}
