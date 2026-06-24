<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    protected $connection = 'mysql';

    protected $table = 'business_units';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'bu_code',
        'bu_name',
        'bu_type',
        'seq_id',
        'bu_seq_code',
        'bu_cus_seq',
        'bu_sup_seq',
        'server',
        'status',
        'prefix',
        'si_prefix',
        'pi_raw_prefix',
        'pi_sup_prefix',
    ];
}

