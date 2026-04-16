<?php

return [
    // Hidden honeypot field name — looks like a real field to bots
    'field_name' => 'website',

    // Minimum seconds between form render and submission
    // Humans take at least 3s; bots submit instantly
    'min_time' => 3,

    // Maximum seconds between form render and submission before the token is stale
    'max_time' => 3600,
];
