# HTTP Response Logging

This application includes comprehensive HTTP response logging to help monitor application health, debug issues, and track performance.

## Features

- **Automatic Status Code Logging**: All HTTP responses are logged with their status code
- **Response Time Tracking**: Every request duration is measured in milliseconds
- **Smart Log Levels**: Different log levels based on status codes (errors, warnings, info)
- **Performance Monitoring**: Slow requests are automatically flagged
- **Contextual Information**: Request ID, user ID, IP, route name, and more
- **Configurable**: Fine-tune what gets logged via configuration

## Log Format

Example log entries:

```
[2026-01-26 10:00:00] local.INFO: GET jobs/show → 200 OK (45.23ms) {"request_id":"uuid","method":"GET","path":"jobs/show","status_code":200,"duration_ms":45.23,"ip":"127.0.0.1","user_id":1,"route_name":"jobs.show"}

[2026-01-26 10:00:01] local.WARNING: GET invalid/path → 404 Not Found (12.50ms) {"request_id":"uuid","method":"GET","path":"invalid/path","status_code":404,"duration_ms":12.50,"ip":"127.0.0.1","url":"http://localhost/invalid/path","user_agent":"Mozilla/5.0...","referer":"http://localhost/home"}

[2026-01-26 10:00:02] local.ERROR: POST applications/create → 500 Internal Server Error (1205.80ms) {"request_id":"uuid","method":"POST","path":"applications/create","status_code":500,"duration_ms":1205.80,"ip":"127.0.0.1","user_id":1,"url":"...","user_agent":"...","performance_warning":"slow_request"}
```

## Log Levels

The middleware automatically assigns appropriate log levels based on HTTP status codes:

- **ERROR** (500-599): Server errors
- **WARNING** (400-499): Client errors (bad requests, not found, unauthorized, etc.)
- **INFO** (200-399): Successful responses and redirects
- **DEBUG** (100-199): Informational responses

## Configuration

Configure HTTP logging in `config/http_logging.php` or via environment variables:

### Environment Variables

```env
# Enable/disable HTTP logging
HTTP_LOGGING_ENABLED=true

# Log successful 2xx responses (may want to disable in high-traffic production)
HTTP_LOGGING_SUCCESS=true

# Log 3xx redirect responses
HTTP_LOGGING_REDIRECTS=true

# Threshold in milliseconds for flagging slow requests
HTTP_LOGGING_SLOW_THRESHOLD=1000
```

### Configuration File

Edit `config/http_logging.php`:

```php
return [
    'enabled' => env('HTTP_LOGGING_ENABLED', true),
    'log_success' => env('HTTP_LOGGING_SUCCESS', true),
    'log_redirects' => env('HTTP_LOGGING_REDIRECTS', true),
    'slow_request_threshold' => env('HTTP_LOGGING_SLOW_THRESHOLD', 1000),

    // Exclude paths from logging (e.g., health checks, high-frequency endpoints)
    'exclude_paths' => [
        'up',           // Laravel health check
        'livewire/*',   // Livewire requests
    ],
];
```

## Logged Context

Each log entry includes:

- **request_id**: Unique identifier for request tracing (X-Request-Id header)
- **method**: HTTP method (GET, POST, PUT, DELETE, etc.)
- **path**: Request path
- **status_code**: HTTP response status code
- **duration_ms**: Request processing time in milliseconds
- **ip**: Client IP address
- **user_id**: Authenticated user ID (if available)
- **route_name**: Named route (if available)

### Additional Context for Errors (4xx, 5xx)

- **url**: Full URL including query parameters
- **user_agent**: Client user agent (sanitized)
- **referer**: Referring page (for 404s to track broken links)

### Performance Warnings

Requests exceeding the slow threshold will include:

- **performance_warning**: "slow_request" flag

## Use Cases

### Monitoring Production Health

Monitor logs for:

- Spike in 5xx errors (server issues)
- Increase in 4xx errors (broken links, API changes)
- Performance degradation (slow request warnings)

### Debugging Issues

- Use `request_id` to trace a specific request through logs
- Check status codes and durations for specific routes
- Identify which users are experiencing errors

### Performance Optimization

- Find slow endpoints by searching for `performance_warning`
- Analyze average response times by route
- Identify pages that need caching or optimization

### Security Monitoring

- Track 401/403 errors (unauthorized access attempts)
- Monitor 404 patterns (path scanning, reconnaissance)
- Detect unusual request patterns by IP

## Production Recommendations

### High-Traffic Applications

To reduce log volume in production:

```env
# Only log errors and redirects, skip successful responses
HTTP_LOGGING_SUCCESS=false
HTTP_LOGGING_REDIRECTS=false
HTTP_LOGGING_SLOW_THRESHOLD=2000
```

### Log Analysis

Consider using log aggregation tools like:

- **ELK Stack** (Elasticsearch, Logstash, Kibana)
- **Splunk**
- **Datadog**
- **New Relic**
- **AWS CloudWatch**

These tools can parse structured logs and provide:

- Real-time dashboards
- Alerting on error spikes
- Performance analytics
- Request tracing

## Excluding Paths

To exclude specific paths from logging (e.g., health checks that run every second):

```php
// config/http_logging.php
'exclude_paths' => [
    'up',
    'health',
    'livewire/*',
    'api/polling',
],
```

## Implementation Details

The logging is implemented in [`app/Http/Middleware/LogHttpResponse.php`](../app/Http/Middleware/LogHttpResponse.php) and registered in [`bootstrap/app.php`](../bootstrap/app.php) as a global web middleware.

The middleware:

1. Runs after the request is processed
2. Measures response time
3. Checks configuration for what to log
4. Sanitizes sensitive data
5. Logs with appropriate level and context

## Security Considerations

The middleware automatically:

- Sanitizes user agents to prevent log injection
- Truncates long strings
- Removes control characters and newlines
- Does NOT log sensitive data (passwords, tokens, etc.)

## Related Features

- **Request ID Middleware**: Adds unique ID to each request for tracing
- **Audit Logging**: Database-backed audit trail for security events
- **Application Logging**: Business logic events logged separately
