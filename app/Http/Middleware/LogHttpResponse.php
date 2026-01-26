<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogHttpResponse
{
    /**
     * Handle an incoming request and log the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if logging is enabled
        if (!Config::get('http_logging.enabled', true)) {
            return $next($request);
        }
        
        // Check if this path should be excluded
        if ($this->shouldExclude($request)) {
            return $next($request);
        }
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
        $statusCode = $response->getStatusCode();
        
        // Check if this response should be logged based on status code
        if (!$this->shouldLog($statusCode)) {
            return $response;
        }
        
        $method = $request->method();
        $path = $request->path();
        $requestId = $request->attributes->get('request_id', 'unknown');
        
        // Determine log level based on status code
        $logLevel = $this->getLogLevel($statusCode);
        
        // Build context
        $context = [
            'request_id' => $requestId,
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ];
        
        // Add route name if available
        if ($route = $request->route()) {
            $context['route_name'] = $route->getName();
        }
        
        // Add additional context for errors
        if ($statusCode >= 400) {
            $context['url'] = $request->fullUrl();
            $context['user_agent'] = $this->sanitizeUserAgent($request->userAgent());
            
            // Add referer for 404s to track broken links
            if ($statusCode === 404 && $request->header('referer')) {
                $context['referer'] = $request->header('referer');
            }
        }
        
        // Add performance warning for slow requests
        $slowThreshold = Config::get('http_logging.slow_request_threshold', 1000);
        if ($duration > $slowThreshold) {
            $context['performance_warning'] = 'slow_request';
        }
        
        // Log the response
        $message = $this->buildLogMessage($method, $path, $statusCode, $duration);
        Log::log($logLevel, $message, $context);
        
        return $response;
    }
    
    /**
     * Check if the request path should be excluded from logging.
     */
    private function shouldExclude(Request $request): bool
    {
        $excludedPaths = Config::get('http_logging.exclude_paths', []);
        $path = $request->path();
        
        foreach ($excludedPaths as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if response should be logged based on status code.
     */
    private function shouldLog(int $statusCode): bool
    {
        // Always log 4xx and 5xx errors
        if ($statusCode >= 400) {
            return true;
        }
        
        // Check config for success and redirect logging
        if ($statusCode >= 300 && $statusCode < 400) {
            return Config::get('http_logging.log_redirects', true);
        }
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return Config::get('http_logging.log_success', true);
        }
        
        return true;
    }
    
    /**
     * Determine the appropriate log level based on status code.
     */
    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',      // 5xx: Server errors
            $statusCode >= 400 => 'warning',    // 4xx: Client errors
            $statusCode >= 300 => 'info',       // 3xx: Redirects
            $statusCode >= 200 => 'info',       // 2xx: Success
            default => 'debug',                 // 1xx: Informational
        };
    }
    
    /**
     * Build a human-readable log message.
     */
    private function buildLogMessage(string $method, string $path, int $statusCode, float $duration): string
    {
        $statusText = $this->getStatusText($statusCode);
        return sprintf(
            '%s %s → %d %s (%.2fms)',
            $method,
            $path,
            $statusCode,
            $statusText,
            $duration
        );
    }
    
    /**
     * Get status code text description.
     */
    private function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            // 2xx Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            
            // 3xx Redirection
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            
            // 4xx Client Errors
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            
            // 5xx Server Errors
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            
            default => '',
        };
    }
    
    /**
     * Sanitize user agent to prevent log injection and truncate length.
     */
    private function sanitizeUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }
        
        // Remove control characters and newlines
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);
        
        // Truncate to reasonable length
        return substr($sanitized ?? '', 0, 255);
    }
}
