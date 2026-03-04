<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $connection = 'tenant';
    protected $table = 'sequence';

    protected $primaryKey = 'sequence_id';

    public $timestamps = false;

    protected $fillable = [
        'for_column',
        'number',
        'year',
        'lpad',
        'pad_string',
        'description',
    ];
}
