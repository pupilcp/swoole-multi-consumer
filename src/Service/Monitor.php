<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

use Pupilcp\Log\Logger;
use Pupilcp\Smc;

class Monitor
{
    private static $instance  = null;

    private $warningTimes = 3; //达到预警的次数
    private $duringTime   = 600; //达到预警的次数存储的有效时间，秒数

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 监控MQ消息数量是否有积压.
     *
     * @param array $queueConf 队列配置
     *
     * @throws
     *
     * @return array
     */
    public function checkOverStock($queueConf)
    {
        $msgCount = 0;
        $status   = false;
        try {
            $consumer = new Consumer(Smc::selectDriver($queueConf, SMC_MESSAGE_DRIVER));
            $msgCount = $consumer->getMessageCount($queueConf['queueName']);
            $status   = $this->triggerOverStock($msgCount, $queueConf);
        } catch (\AMQPConnectionException $e) {
            $msgCount = null;
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]);
        } catch (\Throwable $e) {
            $msgCount = null;
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]);
        }

        return [
            'msgCount' => $msgCount,
            'status'   => $status,
        ];
    }

    /**
     * MQ数量积压的次数是否达到预警.
     *
     * @param array $queueConfig 当前队列配置
     * @param int   $len         队列当前消息量
     *
     * @throws
     *
     * @return bool 是否达到预警
     */
    private function triggerOverStock($len, $queueConfig)
    {
        try {
            $key   = 'smc_' . $queueConfig['queueName'] . '_warningCount';
            $redis = Smc::getRedis();
            if ($len >= (int) $queueConfig['warningNum']) {
                if ($redis->exists($key)) {
                    $redis->incr($key);
                    if ((int) $redis->get($key) >= $this->warningTimes) {
                        //达到预警次数，预警并清零
                        $redis->del($key);

                        return true;
                    }
                } else {
                    $redis->setex($key, $this->duringTime, 1);
                }
            } else {
                //是否连续监控到达预警次数
                if (isset($queueConfig['isConsecutive'])) {
                    if (1 == (int) $queueConfig['isConsecutive']) {
                        $redis->exists($key) && $redis->del($key);
                    }
                } else {
                    //不存在该参数，默认为连续
                    $redis->exists($key) && $redis->del($key);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        return false;
    }
}
