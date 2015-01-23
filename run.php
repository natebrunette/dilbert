<?php

use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Debug\ErrorHandler;
use Tebru\DilbertPics\Application;
use Tebru\Retrofit\Retrofit;

const LOG_NAME = 'app';
const LOG_LOCATION = '/var/log/app.log';
const LOG_EMAIL_TO = 'n+dilberterror@tebru.net';
const LOG_MESSAGE = 'An error occurred';
const LOG_EMAIL_FROM = 'system@dilbertpics.com';

require 'vendor/autoload.php';

$logger = new Logger(LOG_NAME);
$logger->pushHandler(new StreamHandler(__DIR__ . LOG_LOCATION));
$logger->pushHandler(new NativeMailerHandler(LOG_EMAIL_TO, LOG_MESSAGE, LOG_EMAIL_FROM));

$errorHandler = ErrorHandler::register();
$errorHandler->setDefaultLogger($logger);

$retrofit = new Retrofit(__DIR__ . '/cache');
$retrofit->load();

$app = new Application($logger);
$app->main($argv);
