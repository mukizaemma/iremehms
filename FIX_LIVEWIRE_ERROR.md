# Fix Livewire ComponentRegistry Error

The error "Target class [Livewire\Mechanisms\ComponentRegistry] does not exist" is typically caused by:
1. Cache issues
2. Autoloader not regenerated
3. Livewire not properly installed

## Steps to Fix:

1. **Clear all caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   php artisan route:clear
   ```

2. **Regenerate autoloader:**
   ```bash
   composer dump-autoload
   ```

3. **If the error persists, reinstall Livewire:**
   ```bash
   composer remove livewire/livewire
   composer require livewire/livewire
   php artisan vendor:publish --tag=livewire:config
   ```

4. **Clear optimized files:**
   ```bash
   php artisan optimize:clear
   ```

5. **Restart your web server** (if using XAMPP, restart Apache)

## Note:
If you're trying to create a "sub stock", note that we've replaced the old substock system with **Stock Locations**. 

- To create a sub-location: Go to "Stock Locations" → Click "Sub" button next to a main location
- Stock items are now assigned to locations (main or sub) when creating/editing them
