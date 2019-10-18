<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

use Pupilcp\Log\Logger;
use Pupilcp\Smc;
use RedisClient\ClientFactory;

class Redis
{
    public $redis            = null;
    private static $instance = null;

    private function __construct($config)
    {
    	try{
			$redisConf = [
				'server'   => $config['host'] . ':' . $config['port'],
				'timeout'  => $config['timeout'],
				'database' => $config['database'],
			];
			if (isset($config['password'])) {
				$redisConf['password'] = $config['password'];
			}
			$this->redis = ClientFactory::create($redisConf);
		}catch (\Throwable $e){
			Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
			throw $e;
		}
    }

    private function __clone()
    {
    }

    public static function getInstance($config, $single = false)
    {
        //多进程下使用单例模式操作redis出现异常结果
        if ($single) {
            if (!self::$instance instanceof self) {
                self::$instance = new self($config);
            }

            return self::$instance;
        } else {
            return new self($config);
        }
    }
}
