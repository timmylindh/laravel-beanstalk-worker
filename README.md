# laravel-beanstalk-worker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/timmylindh/laravel-beanstalk-worker/check-code-formatting.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/timmylindh/laravel-beanstalk-worker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/timmylindh/laravel-beanstalk-worker.svg?style=flat-square)](https://packagist.org/packages/timmylindh/laravel-beanstalk-worker)

Provides functionality to utilize Laravel SQS queues and cron jobs in AWS Elastic Beanstalk worker environments.

The package supports all Laravel queue and cron features, such as retries, backoff, delay, release, max tries, etc.
  
## Installation

Requires:
- Laravel >= 10
- PHP >= 8.1

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

Then in the AWS Elastic Beanstalk worker enviroment point the *HTTP path* to `/worker/queue`. This will automatically send all SQS messages to the worker.
   
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
```

### AWS Elastic Beanstalk

In the AWS Elastic Beanstalk worker there are other options you can set.

- `Max retries`: this will be ignored and overriden by the package `max_tries` property.
- `Visibility timeout`: this needs to be >= `timeout` that you set in the package, otherwise SQS may release the job back to the queue before the job has finished running.
- `Error visibility timeout`: this will be ignored and overriden by the package `backoff` property.

## Testing

```bash
composer test
```

## Credits

-   [Timmy Lindholm](https://github.com/timmylindh)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
