# Hotel Management System - Architecture Overview

## Core Principle: Single Hotel System

**IMPORTANT**: This system manages **ONE hotel only**. There is:
- ❌ No hotel switching
- ❌ No tenant isolation  
- ❌ No multi-tenancy
- ✅ One database
- ✅ One hotel profile
- ✅ Always exactly one hotel record

The `hotels` table exists only to:
- Store hotel identity (name, contact, email, address)
- Store branding & configuration (logo, colors, fonts)
- Store feature toggles (enabled_modules, enabled_departments)

## Database Structure

### Core Tables
- `hotels` - Single hotel record (id=1 always)
- `users` - All users for the hotel
- `roles` - User roles (Super Admin, Manager, Department Admin, Department User)
- `departments` - Hotel departments (Front Office, Restaurant, Store, etc.)
- `modules` - System modules (Dashboard, Front Office, Restaurant, etc.)
- `system_configurations` - System-wide settings (not hotel-specific)

### Relationships
- Users belong to one Role and one Department
- Roles have many Modules (via role_module pivot)
- Users can have direct Module access (via module_user pivot)
- Hotel has enabled_modules and enabled_departments (JSON arrays)

## Role-Based Access Control

### Super Admin (System Owner)
- **Full access to everything**
- Can enable/disable modules
- Can reset stock, sales, expenses, HR, or entire system
- Can modify any user (except cannot remove Super Admin)
- Can change hotel branding and system behavior
- **Module Access**: ALL active modules (not filtered by hotel enabled_modules)
- Seeded at install time
- Cannot be removed

### Manager
- Manages all departments
- Views all reports
- Manages users (except Super Admin)
- **Module Access**: Only hotel-enabled modules (filtered by hotel.enabled_modules)

### Department Admin
- Manages a single department
- Views department reports
- **Module Access**: Role-based modules + hotel-enabled modules

### Department User
- Performs daily operations only
- **Module Access**: Role-based modules + hotel-enabled modules

## Module Access Logic

### Super Admin
```php
// Returns ALL active modules, regardless of hotel.enabled_modules
Module::where('is_active', true)->orderBy('order')->get();
```

### Manager
```php
// Returns ONLY hotel-enabled modules
Module::whereIn('id', $hotel->enabled_modules)
    ->where('is_active', true)
    ->orderBy('order')
    ->get();
```

### Department Admin/User
```php
// Returns role-based modules filtered by hotel-enabled modules
$roleModules = $user->role->modules;
$userModules = $user->modules;
$allModules = $roleModules->merge($userModules)->unique('id');

// Filter by hotel.enabled_modules
if (!empty($hotel->enabled_modules)) {
    $allModules = $allModules->filter(function($module) use ($hotel) {
        return in_array($module->id, $hotel->enabled_modules);
    });
}
```

## Key Models

### Hotel Model
- `Hotel::getHotel()` - Always returns the single hotel record (creates if doesn't exist)
- Stores: name, contact, email, address, logo, branding, enabled_modules, enabled_departments

### User Model
- `getAccessibleModules()` - Returns modules based on role and hotel configuration
- `hasModuleAccess($moduleId)` - Checks if user can access a specific module
- Role checking methods: `isSuperAdmin()`, `isManager()`, `isDepartmentAdmin()`, `isDepartmentUser()`

## System Configuration

Hotel-specific configuration is stored in the `hotels` table.
System-wide configuration (if needed) can be stored in `system_configurations` table.

## Installation

1. Run migrations: `php artisan migrate`
2. Run seeders: `php artisan db:seed`
   - Creates single hotel record
   - Creates roles
   - Creates departments
   - Creates modules
   - Creates Super Admin user

## Notes

- The `package_id` field on users table exists but is not used for multi-tenancy
- All queries are scoped to the single hotel implicitly
- No tenant isolation code needed
- Super Admin is the system owner, not a tenant admin
