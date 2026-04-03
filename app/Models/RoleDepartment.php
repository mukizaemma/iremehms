<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleDepartment extends Model
{
    const SCOPE_GLOBAL = 'global';
    const SCOPE_DEPARTMENT = 'department';

    protected $fillable = [
        'role_id',
        'department_id',
        'scope',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isGlobal(): bool
    {
        return $this->scope === self::SCOPE_GLOBAL;
    }

    public function isDepartmentScoped(): bool
    {
        return $this->scope === self::SCOPE_DEPARTMENT;
    }
}
