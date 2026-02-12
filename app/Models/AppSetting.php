<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'bu_id',
        'app_name',
        'base_url',
        'db_driver',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'description',
        'is_active',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(\App\Models\MasterfileModels\User::class, 'app_setting_id');
    }
}
