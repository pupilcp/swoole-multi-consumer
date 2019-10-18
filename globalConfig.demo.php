<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

define('SMC_AMQP_CONSUME', 1); //rabbitmq
define('SMC_REDIS_CONSUME', 2); //redis
define('SMC_SERVER_VERSION', '1.0.0');
define('SMC_MESSAGE_DRIVER', SMC_AMQP_CONSUME); //消息驱动， 1.0.0暂时仅仅支持rabbitmq

return [
    //通用配置
    'global' => [
        'enableNotice'        => true, //是否开启预警通知
        'dingDingToken'       => '钉钉机器人token', //钉钉机器人token
        'queueCfgCallback'    => ['\Pupilcp\Service\Test', 'loadQueueConfig'], //系统会检测此回调方法，实现队列配置热加载，格式：call_user_func_array方法的第一个参数
        'logPath'             => '日志文件目录', //可选，日志文件路径，最好自定义
        'logFileName'         => '日志文件名称', //可选，
        //'smcServerStatusTime' => 120, //可选，定时监测smc-server状态的时间间隔，默认为120，单位：秒
        //'queueStatusTime'     => 60, //可选，定时监测消息队列数据积压的状态，自动伸缩消费者，默认为60，单位：秒
        //'checkConfigTime'     => 60, //可选，定时监测队列相关配置状态的时间间隔，结合queueCfgCallback实现热加载，默认为60，单位：秒
    ],
    //redis连接信息，用于消息积压预警和进程信息的记录
    'redis' => [
        'host'     => '192.168.1.5', //redis服务地址
        'port'     => '6379', //端口号
        'database' => 1,
        'timeout'  => 5,
        //'password' => '', //不用密码请注释该配置
    ],
];
