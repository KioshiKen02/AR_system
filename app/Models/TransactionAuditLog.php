<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAuditLog extends Model
{
    protected $connection = 'tenant';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
