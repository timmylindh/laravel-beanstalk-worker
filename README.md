# laravel-beanstalk-worker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/check-code-formatting.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)

Provides functionality to utilize Laravel SQS queues and cron jobs in AWS Elastic Beanstalk worker environments.

The package supports all Laravel queue and cron features, such as retries, backoff, delay, release, max tries, timeout, etc.

## Installation

Requirements:

-   Laravel >= 10
-   PHP >= 8.1

Install the package:

```bash
composer require timmylindh/laravel-beanstalk-worker
```

Publish config (optional):

```bash
php artisan vendor:publish --tag="laravel-beanstalk-worker-config"
```

Publish EB deploy hooks (Amazon Linux 2023):

```bash
php artisan vendor:publish --tag="laravel-beanstalk-worker-deploy"
chmod +x .platform/hooks/postdeploy/*.sh
```

## Automatic setup

Recommended. Set one variable and let the hooks align all timeouts automatically.

1. In Elastic Beanstalk (Worker env), set environment variables:

-   `IS_WORKER=true`
-   `WORKER_TIMEOUT=<seconds> (e.g., 300, 900, 1000)`
-   `SQS_QUEUE_URL=https://sqs.<region>.amazonaws.com/<account>/<queue>`

2. Set Worker HTTP path to `/worker/queue` (Configuration → Worker → HTTP path).

Hooks automatically configured from WORKER_TIMEOUT:

-   PHP: `max_execution_time = WORKER_TIMEOUT`
-   PHP‑FPM: `request_terminate_timeout = WORKER_TIMEOUT`
-   Nginx: `fastcgi_read_timeout = WORKER_TIMEOUT + 30s`
-   SQS: VisibilityTimeout to `WORKER_TIMEOUT + 100s`
-   SQSD: `Visibility Timeout = WORKER_TIMEOUT + 100s`
-   SQSD: `Inactivity Timeout = WORKER_TIMEOUT + 30s`

### Cron (optional)

Add a periodic task that runs `php artisan schedule:run` via the worker:

1. Create `cron.yaml` in your app root:

```yaml
version: 1
cron:
    - name: "cron"
      url: "/worker/cron"
      schedule: "* * * * *"
```

2. Ensure `IS_WORKER=true`. The package exposes `/worker/cron` and will run your Laravel scheduler.

## Manual setup

Use only if you don’t publish the hooks. Configure to avoid premature redeliveries:

-   Set Worker HTTP path to `/worker/queue`.
-   Set env `IS_WORKER=true`.
-   SQS `VisibilityTimeout = WORKER_TIMEOUT + ~100s`
-   SQSD `VisibilityTimeout = WORKER_TIMEOUT + ~100s`
-   SQSD `InactivityTimeout = WORKER_TIMEOUT + ~30s`
-   Nginx `fastcgi_read_timeout = WORKER_TIMEOUT + ~30s`
-   PHP‑FPM `request_terminate_timeout = WORKER_TIMEOUT`
-   PHP `max_execution_time = WORKER_TIMEOUT`

## Configuration

`config/worker.php` (publish to customize):

```php
return [
    /*
     * Whether the application is a worker or not.
     * This will determine whether to register the worker routes or not.
     */
    "is_worker" => env("IS_WORKER", false),

    // The number of seconds to wait before retrying a job that encountered an uncaught exception.
    "backoff" => env("WORKER_BACKOFF", 0),

    // The number of seconds a job may run before timing out.
    "timeout" => env("WORKER_TIMEOUT", 60),

    // The maximum number of times a job may be attempted.
    "max_tries" => env("WORKER_MAX_TRIES", 1),
];
```

Jobs can still set `$timeout` and `$tries`. Ensure `$timeout <= WORKER_TIMEOUT`.

## How it works

-   Flow

    -   aws‑sqsd (EB worker daemon) polls SQS and POSTs messages to your app at `/worker/queue`.
    -   The package deserializes the job and executes it using Laravel’s worker semantics.
    -   The endpoint always returns HTTP 2xx, so aws‑sqsd deletes the SQS message after delivery.

-   Retries and attempts

    -   On failure, the job is re‑queued via Laravel `release()` with your chosen backoff; the payload carries an incremented `attempts` count.
    -   On the next delivery, total attempts = payload attempts + SQS receive count. Use `$tries`/`WORKER_MAX_TRIES` as usual.
    -   Keep jobs idempotent; SQS is at‑least‑once and duplicates can occur.

-   Timeouts

    -   `WORKER_TIMEOUT` defines the max runtime per job at the worker level; job‑level `$timeout` may be set but should not exceed `WORKER_TIMEOUT`.
    -   The provided hooks align infra timeouts from `WORKER_TIMEOUT` (PHP, PHP‑FPM, Nginx, aws‑sqsd) and set SQS VisibilityTimeout with a small buffer.

-   Cron
    -   If you add `cron.yaml` pointing to `/worker/cron`, the worker runs `php artisan schedule:run` on the specified cadence.
