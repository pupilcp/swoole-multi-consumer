<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Base;

class BaseApplication
{
    public function __construct()
    {
    }

    public function run($jobArray)
    {
        if (!defined('APP_PATH') || !defined('YCF_CONFIG_PATH') || !defined('YCF_PROTECTED_PATH')) {
            throw new \Exception('Undefined APP_PATH OR YCF_CONFIG_PATH OR YCF_PROTECTED_PATH');
        }
        if (defined('YII_ENV') && YII_ENV == 'development') {
            $name = 'console-dev.php';
        } elseif (defined('YII_ENV') && YII_ENV == 'local') {
            $name = 'console-local.php';
        } else {
            $name = 'console.php';
        }
        require_once APP_PATH . '/../framework/yii.php';
        $config           =  YCF_CONFIG_PATH . '/' . $name;
        $_SERVER['argv']  = ['yiic', $jobArray['command'], $jobArray['action'], $jobArray['msg']];
        $application      = new \CConsoleApplication($config);
        $application->processRequest(false);
        \YiiBase::setApplication(null);
        $application = null;
    }
}
