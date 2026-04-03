# Hotel Management System - Setup Instructions

## Installation Steps

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Configure Environment
Make sure your `.env` file is properly configured with database credentials.

### 3. Run Migrations
```bash
php artisan migrate
```

### 4. Seed Database
```bash
php artisan db:seed
```

This will create:
- 4 roles: Super Admin, Manager, Department Admin, Department User
- 5 departments: Front Office, Restaurant, Store, Housekeeping, Maintenance
- 7 modules: Dashboard, Front Office, Restaurant, Store, Housekeeping, Reports, Settings
- 1 Super Admin user:
  - Email: `admin@hotel.com`
  - Password: `password`

### 5. Start Development Server
```bash
php artisan serve
```

## Access the Application

- **Login URL**: http://localhost:8000/login
- **Register URL**: http://localhost:8000/register
- **Dashboard URL**: http://localhost:8000/dashboard (after login)

## User Roles & Access

### Super Admin
- Email: `admin@hotel.com`
- Password: `password`
- Has access to all modules
- Can manage system configurations, packages, and users

### Manager
- Oversees all departments
- Views reports across all departments
- Manages users within departments
- Has access to all modules except Settings

### Department Admin
- Manages operations of a specific department
- Views department-level reports
- Access depends on assigned modules

### Department User
- Performs daily tasks
- Access limited to permitted features/modules

## Login Flow

1. User enters email address
2. System loads available modules for that user
3. User selects a module to login
4. User enters password
5. Upon successful login, user is redirected to dashboard with selected module context

## Module Access Control

- Modules are assigned to roles via `role_module` pivot table
- Individual users can have direct module access via `module_user` pivot table
- Super Admin automatically has access to all active modules
- Users can only login to modules they have access to

## Next Steps

1. Create additional users via registration or admin panel
2. Assign modules to roles as needed
3. Create department-specific modules
4. Implement module-specific dashboards and features
