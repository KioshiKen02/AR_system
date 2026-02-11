<?php

namespace App\Models\MasterfileModels;

class TenantUser extends User
{
    protected $connection = 'tenant';
    protected $table = 'users';

    /**
     * Override roles relationship to use the default (tenant) connection
     */
    public function roles()
    {
        // Since we are in TenantUser, we want roles from the same tenant connection.
        // Role model by default doesn't have a connection set, so it uses default.
        // In tenant context, default IS tenant.
        
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
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
    
    /**
     * Override permissions to use the default (tenant) connection
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'user_id', 'id');
    }

    /**
     * Override rolePermissions to use the default (tenant) connection
     */
    public function rolePermissions()
    {
        return $this->hasManyThrough(
            Permission::class,
            Role::class,
            'user_id', // Foreign key on the roles table? No, this relation in User.php was complicated.
            // Let's look at User.php implementation again.
            // User.php:
            // return $this->hasManyThrough(
            //    get_class($permission),
            //    get_class($role),
            //    'user_id', // Foreign key on the roles table ?? Role doesn't have user_id.
            //    'role_id', // Foreign key on the role_user_permissions table ??
            //    'id',      // Local key on the user table
            //    'id'       // Local key on the role_user_permissions table ??
            // );
            
            // Actually, looking at User.php logic:
            // It joins permissions through role? 
            // Permission table has user_id and role_id.
            // User hasMany Permissions directly.
            
            // The User.php rolePermissions() implementation seems suspect or specific:
            // return $this->hasManyThrough(Permission::class, Role::class, ...)
            // Permissions are linked to User directly in `permissions` table.
            
            // Let's just rely on permissions() relationship which is simpler.
            // But if I need to override it to ensure connection safety:
        );
        
        // Retaining simple permissions() is likely enough if Permission model is standard.
        // But let's check User.php implementation to be safe.
        return parent::rolePermissions();
    }
}
