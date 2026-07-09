<?php

namespace App\Models\MasterfileModels;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Message;
use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'username',
        'password',
        'role',
        'status',
        'bu_assign',
        'app_setting_id',
        'theme',
        'managers_key_code',
        'created_by',
        'is_online',
        'last_seen_at',
        'allow_hrms_bypass',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'allow_hrms_bypass' => 'boolean',
        ];
    }

    public function appSetting()
    {
        return $this->belongsTo(AppSetting::class, 'app_setting_id');
    }

    /**
     * The user may belong to multiple app settings (tenants).
     */
    public function appSettings()
    {
        return $this->belongsToMany(AppSetting::class, 'app_setting_user', 'user_id', 'app_setting_id')
            ->withTimestamps();
    }

    public function roles()
    {
        $role = new Role();
        $role->setConnection(config('database.default'));
        
        return $this->belongsToMany(get_class($role), 'role_user', 'user_id', 'role_id')
            ->using(new class extends \Illuminate\Database\Eloquent\Relations\Pivot {
                protected $connection;
                public function __construct() {
                    $this->connection = config('database.default');
                    parent::__construct();
                }
            })
            ->withPivot([
            'can_view',
            'can_insert',
            'can_update',
            'can_delete',
            'can_print',
            'can_tag',
            'can_reprint'
        ]);
    }

    public function rolePermissions()
    {
        // Force Permission model to use current default connection (tenant)
        $permission = new Permission();
        $permission->setConnection(config('database.default'));

        // Force Role model to use current default connection (tenant)
        $role = new Role();
        $role->setConnection(config('database.default'));

        return $this->hasManyThrough(
            get_class($permission),
            get_class($role),
            'user_id', // Foreign key on the roles table
            'role_id', // Foreign key on the role_user_permissions table
            'id',      // Local key on the user table
            'id'       // Local key on the role_user_permissions table
        );
    }

    public function permissions()
    {
        $instance = new Permission();
        $instance->setConnection(config('database.default')); 
        
        return $this->newHasMany(
            $instance->newQuery(), $this, $instance->getTable().'.user_id', 'id'
        );
    }

    /**
     * Get messages sent by the user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get messages received by the user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get all messages (sent and received) for the user
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id')
            ->union($this->hasMany(Message::class, 'receiver_id'));
    }

    /**
     * Get unread messages count
     */
    public function unreadMessagesCount(): int
    {
        return $this->receivedMessages()->whereNull('read_at')->count();
    }

    /**
     * Get conversation with another user
     */
    public function conversationWith(User $user)
    {
        return Message::betweenUsers($this->id, $user->id)
            ->orderBy('created_at', 'asc');
    }

    /**
     * Update user's online status
     */
    public function updateOnlineStatus(bool $isOnline = true): void
    {
        $this->update([
            'is_online' => $isOnline,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Mark user as offline
     */
    public function markOffline(): void
    {
        $this->updateOnlineStatus(false);
    }

    /**
     * Check if user is online
     */
    public function isOnline(): bool
    {
        return $this->is_online ?? false;
    }

    // public function 
}
