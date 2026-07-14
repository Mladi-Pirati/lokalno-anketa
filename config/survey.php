<?php

return [
    // Salt used when hashing submitter IPs (never store raw IPs).
    'hash_salt' => env('SURVEY_HASH_SALT', 'change-me'),
];
