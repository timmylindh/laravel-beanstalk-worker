# laravel-beanstalk-worker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/check-code-formatting.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)

Provides functionality to utilize Laravel SQS queues and cron jobs in AWS Elastic Beanstalk worker environments.

The package supports all Laravel queue and cron features, such as retries, backoff, delay, release, max tries, timeout, etc.

## Recommended setup (WORKER_TIMEOUT‑driven)

Set one variable and let the provided deploy hooks align all timeouts automatically:

1. Publish deploy hooks (Amazon Linux 2023):

    ```bash
    php artisan vendor:publish --tag="laravel-beanstalk-worker-deploy"
    chmod +x .platform/hooks/postdeploy/*.sh
    ```

2. In your Elastic Beanstalk Worker environment, set env vars:

    - `IS_WORKER=true`
    - `WORKER_TIMEOUT=<seconds>` (e.g., 300, 900, 1000)
    - `SQS_QUEUE_URL=https://sqs.<region>.amazonaws.com/<account>/<queue>`

3. Point Worker HTTP path to `/worker/queue` (Configuration → Worker → HTTP path).

With only `WORKER_TIMEOUT` set, the hooks will:

-   PHP: `max_execution_time = WORKER_TIMEOUT`
-   PHP‑FPM: `request_terminate_timeout = WORKER_TIMEOUT`
-   Nginx: `fastcgi_read_timeout = WORKER_TIMEOUT + 30s`
-   aws‑sqsd: `inactivity_timeout = WORKER_TIMEOUT + 30s`
-   SQS : `VisibilityTimeout = WORKER_TIMEOUT + 100s`

This avoids manual tuning and prevents premature message redeliveries.

## Installation

Requires:

-   Laravel >= 10
-   PHP >= 8.1

You can install the package via composer:

```bash
composer require timmylindh/laravel-beanstalk-worker
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-beanstalk-worker-config"
```

You can publish ready-to-use Elastic Beanstalk deployment hooks (AL2023) with:

```bash
php artisan vendor:publish --tag="laravel-beanstalk-worker-deploy"
```

This will install:

-   `.platform/hooks/postdeploy/20-php-fpm-timeout.sh` (sets PHP `max_execution_time` and PHP‑FPM `request_terminate_timeout` from `WORKER_TIMEOUT` when `IS_WORKER=true`)
-   `.platform/hooks/postdeploy/30-nginx-fastcgi-timeout.sh` (sets Nginx `fastcgi_read_timeout = WORKER_TIMEOUT + 30s` when `IS_WORKER=true`)
-   `.platform/hooks/postdeploy/40-sqsd-timeouts.sh` (writes aws‑sqsd inactivity_timeout from `WORKER_TIMEOUT + 30s` and attempts a safe reload)
-   `.platform/hooks/postdeploy/50-sqs-visibility-timeout.sh` (optionally sets SQS `VisibilityTimeout = WORKER_TIMEOUT + 100s` via AWS CLI if `SQS_QUEUE_URL` provided)
-   `.platform/nginx/conf.d/fastcgi-timeouts.conf` (placeholder include managed by the hook)

Make the hooks executable:

```bash
chmod +x .platform/hooks/postdeploy/*.sh
```

````

## Usage

### Enable worker

Set the environment variable `IS_WORKER` to enable the worker routes.

```bash
IS_WORKER=true
````

Then in the AWS Elastic Beanstalk worker enviroment point the _HTTP path_ to `/worker/queue`. This will automatically send all SQS messages to the worker.

### Enable cron

In your Laravel root project folder add the file `cron.yaml` with the contents below. This will tell the AWS daemon to publish an SQS cron task message every minute, which will be sent to our worker which will run the scheduled cron jobs.

```yaml
version: 1
cron:
    - name: "cron"
      url: "/worker/cron"
      schedule: "* * * * *"
```

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-beanstalk-worker-config"
```

The configuration contains various properties to control the worker which will run your queued jobs. These are the same as you would provide when running a worker locally using `php artisan queue:work`.

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

### AWS Elastic Beanstalk

Best practice: use the deploy hooks and set only `WORKER_TIMEOUT` (no manual changes required).

If you prefer manual configuration instead of hooks, in the AWS Elastic Beanstalk worker there are other options you can set:

-   `Max retries`: this will be ignored and overriden by the package `max_tries` property. Should be set to a greater value than the largest `max_tries` you expect for any job.
-   `Visibility timeout`: how many seconds to wait for the job to finish before releasing it back onto the queue. Should be greater than the largest `timeout` you expect for any job.
-   `Inactivity timeout`: should be set < `Visibility timeout`.
-   `Fastcgi read timeout`: should be set < `Inactivity timeout`.
-   `Request terminate timeout`: should be set < `Fastcgi read timeout`.
-   `Max execution time`: should be set ≤ `Request terminate timeout`.
-   `Error visibility timeout`: this will be ignored and overriden by the package `backoff`. When a timeout occurs, this will (instead of timeout) control the number of seconds to wait before retrying the job.

So summarized you should set the values as follows: `max_execution_time` ≤ `request_terminate_timeout` < `fastcgi_read_timeout` < `aws-sqsd inactivity_timeout` < `SQS VisibilityTimeout`.

### Fastcgi read timeout

This is how long Nginx will wait without receiving data from PHP‑FPM. To set this value, create a file `.platform/nginx/conf.d/fastcgi-timeouts.conf`

```
fastcgi_read_timeout 1000s;
```

### Request terminate timeout

This is a hard wall‑clock limit for a single request; PHP‑FPM kills the worker process once reached. To set this value, create a file `.platform/hooks/postdeploy/20-php-fpm-timeout.sh`

```
#!/bin/bash
set -euo pipefail

cat >/etc/php-fpm.d/zz-timeout.conf <<'CONF'
[www]
request_terminate_timeout = 1000s
CONF

systemctl restart php-fpm || systemctl restart php82-php-fpm || true
```

and make it executable `chmod +x .platform/hooks/postdeploy/20-php-fpm-timeout.sh`.

## Handling timeouts

To handle timeouts properly you should make sure to set the Elastic Beanstalk properties as follows:

`Max retries` > `max(max_retries|$tries, for any job)`

`Visibility timeout` > `max(timeout|$timeout, for any job)`

`Error visibility timeout` >= 0: this will control the number of seconds to wait before retrying a timed out job.

<hr />

Example: If your longest job is 900s, set `WORKER_TIMEOUT=900`. The hooks will set Nginx fastcgi_read_timeout=930, aws‑sqsd inactivity_timeout=930, and (optionally) SQS VisibilityTimeout ~1000–1100. Keep jobs idempotent and use `$tries` > 1.

#### Programmatic timeouts (optional)

If you publish the deploy hooks, the timeouts are driven by `WORKER_TIMEOUT` automatically on deploy. Alternatively, you can set Worker “Inactivity timeout” and SQS VisibilityTimeout in the EB console and omit the hooks.

See _configuration_ section above for more details.

## How it works

Normally, in a standard server environment, Laravel queue workers are set up by executing the command `php artisan queue:work`. However, setting this up in AWS Elastic Beanstalk can be challenging due to the platform's architecture.

### Worker Environments in Elastic Beanstalk

Elastic Beanstalk supports worker environments that integrate seamlessly with Amazon SQS. These environments include an SQS daemon that continuously polls the queue, fetching and processing jobs by forwarding them to a specified URL endpoint on your application.

### Automatic Message Handling

-   **Successful Processing**: If the application's endpoint returns an HTTP 2xx status code, the daemon interprets this as a successful job completion and automatically deletes the message from the queue.
-   **Failed Processing**: If the endpoint returns a non-2xx status, the daemon re-queues the message. The job will be retried after the `Error visibility timeout` period and will continue until the `Max retries` limit is reached.

### Challenges

A significant challenge in this setup is the loss of control over the number of maximum attempts for each job and the delay before a job is retried (backoff strategy).

### Solution

To address these issues, we utilize a dedicated route `/worker/queue` which is designed to always return an HTTP 2xx status code, regardless of the job's actual outcome. This ensures that the SQS daemon deletes the job after its first dequeue.

-   **Error Handling**: If a job fails, we call `report()` to log the exception, ensuring visibility and traceability of job failures.
-   **Job Retries**: We override Laravel's default `release()` method. This allows us to re-queue a failed job manually with an incremented `attempts` count and a custom `delay`, effectively managing the retry mechanism more precisely.

This approach closely mimics the behavior of running a standard Laravel worker using artisan, while integrating into the Elastic Beanstalk environment.

## Testing

```bash
composer test
```

## Credits

-   [Timmy Lindholm](https://github.com/timmylindh)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
