<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

define('SMC_AMQP_CONSUME', 1); //rabbitmq
define('SMC_MESSAGE_DRIVER', SMC_AMQP_CONSUME); //消息驱动， 暂时仅仅支持rabbitmq
define('SMC_APP_PATH', __DIR__ . DIRECTORY_SEPARATOR);

return [
    //通用配置
    'global' => [
        'masterProcessName'      => 'smc-server-master', //主进程名称
        'enableNotice'           => true, //是否开启预警通知
        'dingDingToken'          => '钉钉机器人token', //钉钉机器人token
        'queueCfgCallback'       => ['\Pupilcp\Service\Test', 'loadQueueConfig'], //系统会检测此回调方法，实现队列配置热加载，格式：call_user_func_array方法的第一个参数，如果不使用热加载，可以使用以下的 amqp 配置的示例
        'logPath'                => '日志文件目录', //日志文件路径
		'childProcessMaxExecTime'  => 86400, //子进程最大执行时间，避免运行时间过长，释放内存，单位：秒
        //'baseApplication'        => null, //框架执行命令行，默认为yii1：\Pupilcp\Base\BaseApplication，其它框架请继承\Pupilcp\Base\BaseApplication
        //'smcServerStatusTime' => 120, //可选，定时监测smc-server状态的时间间隔，默认为null，不开启
        //'queueStatusTime'     => 60, //可选，定时监测消息队列数据积压的状态，自动伸缩消费者，默认为null，不开启
        //'checkConfigTime'     => 60, //可选，定时监测队列相关配置状态的时间间隔，结合queueCfgCallback实现热加载，默认为null，不开启
    ],
    //redis连接信息，用于消息积压预警和进程信息的记录
    'redis' => [
        'host'     => '192.168.1.5', //redis服务地址
        'port'     => '6379', //端口号
        'database' => 1,
        'timeout'  => 5,
        //'password' => '', //不用密码请注释该配置
    ],
	'amqp' => [
		//消息服务连接配置
		'connection' => [
			'host'            => '192.168.109.130',
			'user'            => 'phpadmin',
			'pass'            => 'phpadmin',
			'port'            => '5672',
			'vhost'           => 'php', //default vhost
			'exchange'        => 'php.amqp.ext',
			'timeout'         => 3,
		],
		'queues' => [
			'DIS_SYNC_PACKAGE_SIP' => [
				'queueName'      => 'DIS_SYNC_PACKAGE_SIP', //队列名称
				'routeKey'       => 'DIS_SYNC_PACKAGE_SIP_RK', //路由key
				'vhost'          => 'php', //队列所在的vhost
				'prefetchCount'  => 5, //默认为10，不需要的话去掉该选项或设置为null
				'minConsumerNum' => 3,  //最小消费者数量
				'maxConsumerNum' => 10,  //最大消费者数量，系统限制最大20
				'warningNum'     => 30, //达到预警的消息数量，请合理设置，建议不少于1000
				'callback'       => ['hello', 't'], //程序执行job，[command,action]
			],
		]
	]
];
