<?php

use Illuminate\Support\Facades\Route;
use Timmylindh\LaravelBeanstalkWorker\Http\Controllers\WorkerController;

Route::post('/worker/queue', [WorkerController::class, 'queue']);
Route::post('/worker/cron', [WorkerController::class, 'cron']);
