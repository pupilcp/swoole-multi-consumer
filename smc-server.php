<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

require_once 'vendor/autoload.php';

$globalConfig = include 'globalConfig.php';
try {
    $app = new \Pupilcp\App($globalConfig);
    $app->run();
} catch (\Throwable $e) {
    $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
    var_dump('Error: ' . $error);
    \Pupilcp\Smc::$logger->log('Error: ' . $error, \Pupilcp\Log\Logger::LEVEL_ERROR);
}
