<?php

return [
    /*
     * Whether the application is a worker or not.
     * This will determine whether to register the worker routes or not.
     */
    'is_worker' => env('IS_WORKER', false),

    // The number of seconds to wait before retrying a job that encountered an uncaught exception.
    'backoff' => env('WORKER_BACKOFF', 0),

    // The amount of memory the worker may consume.
    'memory' => env('WORKER_MEMORY', 128),

    // The number of seconds a child process can run before it is killed.
    'timeout' => env('WORKER_TIMEOUT', 60),

    // The maximum number of times a job may be attempted.
    'max_tries' => env('WORKER_MAX_TRIES', 1),
];
