<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class VerifyLogoutRoute extends Command
{
    protected $signature = 'logout:verify';
    protected $description = 'Verify that the logout route is registered and show its URL';

    public function handle(): int
    {
        $this->info('Checking logout route...');

        try {
            $route = Route::getRoutes()->getByName('logout');
        } catch (\Exception $e) {
            $this->error('Route named "logout" not found.');
            return self::FAILURE;
        }

        if (!$route) {
            $this->error('Route named "logout" not found.');
            return self::FAILURE;
        }

        $methods = implode('|', $route->methods());
        $uri = $route->uri();
        $action = $route->getActionName();

        $this->info("Route 'logout' is registered:");
        $this->line("  Method: {$methods}");
        $this->line("  URI: {$uri}");
        $this->line("  Action: {$action}");
        $this->line('  Full URL: ' . url($uri));
        $this->newLine();
        $this->info('To test from terminal (requires session cookie):');
        $this->line('  php artisan route:list --name=logout');
        $this->line('  curl -X POST -b "your-session-cookie" ' . url($uri));

        return self::SUCCESS;
    }
}
