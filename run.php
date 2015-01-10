<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tebru\DilbertPics\Application;
use Tebru\DilbertPics\Exception\AppException;

const LOG_NAME = 'app';
const LOG_LOCATION = 'var/log/app.log';
const LOG_EMAIL_TO = 'n+dilberterror@tebru.net';
const LOG_MESSAGE = 'An error occurred';
const LOG_EMAIL_FROM = 'system@dilbertpics.com';

require 'vendor/autoload.php';

AnnotationRegistry::registerAutoloadNamespace('JMS\Serializer\Annotation', 'vendor/jms/serializer/src');

$logger = new Logger(LOG_NAME);
$logger->pushHandler(new StreamHandler(LOG_LOCATION));
$logger->pushHandler(new NativeMailerHandler(LOG_EMAIL_TO, LOG_MESSAGE, LOG_EMAIL_FROM));

$app = new Application($logger);
try {
    $app->main($argv);
} catch (AppException $e) {
    $logger->log($e->getCode(), $e->getMessage(), ['exception' => $e]);
} catch (Exception $e) {
    $logger->log(Logger::ERROR, 'An unexpected error occurred', ['exception' => $e]);
}
