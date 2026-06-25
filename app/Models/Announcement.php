<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $fillable = [
        'title',
        'message',
        'applies_to_all',
        'is_active',
        'show_banner',
        'show_modal',
        'is_dismissible',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applies_to_all' => 'boolean',
        'is_active' => 'boolean',
        'show_banner' => 'boolean',
        'show_modal' => 'boolean',
        'is_dismissible' => 'boolean',
    ];

    public function appSettings()
    {
        return $this->belongsToMany(AppSetting::class, 'announcement_app_setting', 'announcement_id', 'app_setting_id');
    }
}
