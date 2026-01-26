<?php

namespace App\Http\Routes;

/**
 * Legacy RouteService class for backward compatibility.
 * 
 * Routes are now organized in separate files:
 * - routes/install.php - Installation wizard routes
 * - routes/jobs.php - Job and application routes
 * - routes/profile.php - User profile routes
 * - routes/admin.php - Admin panel routes
 * - routes/web.php - Main entry point that includes all route files
 * 
 * This service is kept for compatibility but no longer actively used.
 * All route registration now happens through route file includes in web.php.
 * 
 * @deprecated Use organized route files instead
 */
class RouteService
{
    /**
     * Register all application routes.
     * 
     * @deprecated Routes are now registered via route file includes in routes/web.php
     */
    public static function register(): void
    {
        // Routes are now handled by individual route files
        // This method is kept for backward compatibility only
        // See routes/web.php for the new structure
    }
}
