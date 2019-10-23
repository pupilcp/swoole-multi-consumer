<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Driver;

use Pupilcp\Interfaces\MessageDriver;
use Pupilcp\Library\AmqpLib;
use Pupilcp\Log\Logger;
use Pupilcp\Smc;

class AmqpDriver implements MessageDriver
{
    private $amqp   = null;

    /**
     * AmqpDriver constructor.
     *
     * @param array $params 连接参数集合
     *
     * @throws
     */
    public function __construct(array $params)
    {
        try {
            $this->amqp = AmqpLib::getInstance($params['host'], $params['port'], $params['user'], $params['pass'], $params['vhost'], $params['exchange'], $params['timeout'] ?? null);
        } catch (\Throwable $e) {
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * subscribe 订阅.
     *
     * @param array $callbackConf 回调配置
     * @param array $queueConf    队列配置
     *
     * @throws
     */
    public function subscribe(array $callbackConf, array $queueConf = [])
    {
        try {
            $this->amqp->getConnection();
            $this->amqp->consume(function (\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($callbackConf) {
            	try{
					call_user_func_array($callbackConf, [$envelope->getBody()]);
					$queue->ack($envelope->getDeliveryTag());
				}catch (\Throwable $e){
					Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
				}
            }, $queueConf);
        } catch (\Throwable $e) {
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * subscribe 取消订阅.
     */
    public function unsubscribe()
    {
        // TODO: Implement unsubscribe() method.
    }

    /**
     * 获取队列的消息总数.
     *
     * @param string $queue 队列名
     *
     * @throws
     *
     * @return int
     */
    public function getMessageCount($queue)
    {
        try {
            $this->amqp->getConnection();

            return $this->amqp->getMessageCount($queue);
        } catch (\Throwable $e) {
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }
}
