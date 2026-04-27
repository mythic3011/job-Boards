<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Adjust these values to change rate limiting across the application.
    | All per-minute limits use a 1-minute window. Per-hour limits noted inline.
    |
    */

    // Auth
    'login'              => 20,   // per minute, per identifier+IP
    'two_factor'         => 20,   // per minute, per session
    'register'           => 15,   // per hour, per IP
    'password_reset'     => 15,   // per hour, per email+IP

    // Admin
    'admin'              => 60,   // per minute

    // Jobs & Applications
    'job_create'         => 30,   // per minute
    'job_apply'          => 15,   // per minute
    'my_applications'    => 120,  // per minute
    'application_action' => 30,   // per minute (approve/reject)
    'cv_download'        => 60,   // per minute

    // General
    'file_upload'        => 30,   // per minute
    'api'                => 240,  // per minute
];
