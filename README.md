# laravel-beanstalk-worker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/check-code-formatting.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)

Provides functionality to utilize Laravel SQS queues and cron jobs in AWS Elastic Beanstalk worker environments.

The package supports all Laravel queue and cron features, such as retries, backoff, delay, release, max tries, etc.

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

## Usage

### Enable worker

Set the environment variable `IS_WORKER` to enable the worker routes.

```bash
IS_WORKER=true
```

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

    // The maximum number of times a job may be attempted.
    "max_tries" => env("WORKER_MAX_TRIES", 1),
];
```

The `timeout` worker property is controlled by the Elastic Beanstalk `Visibility timeout`, `Inactivity timeout` and `Max execution time` properties. See below.

### AWS Elastic Beanstalk

In the AWS Elastic Beanstalk worker there are other options you can set.

-   `Max retries`: this will be ignored and overriden by the package `max_tries` property.
-   `Visibility timeout`: how many seconds to wait for the job to finish before releasing it back onto the queue. This corresponds to the worker `retry_after` property.
-   `Inactivity timeout`: should be set same as `Visibility timeout`.
-   `Max execution time`: should be set same as `Visibility timeout`.
-   `Error visibility timeout`: this will be ignored and overriden by the package `backoff` property unless a timeout occurs.

## Managing Laravel Queue Jobs in AWS Elastic Beanstalk

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
