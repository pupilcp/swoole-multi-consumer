<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

use Pupilcp\Interfaces\MessageDriver;

class Consumer
{
    private $messageDriver = null;

    /**
     * Consumer constructor.
     *
     * @param MessageDriver $messageDriver 消息消费驱动
     */
    public function __construct(MessageDriver $messageDriver)
    {
        $this->messageDriver = $messageDriver;
    }

    /**
     * Consume.
     *
     * @param array $callbackConf 消费回调配置
     * @param array $queueConf    消息队列配置
     */
    public function consume(array $callbackConf, array $queueConf = [])
    {
        $this->messageDriver->subscribe($callbackConf, $queueConf);
    }

    /**
     * Consume.
     *
     * @param mixed $queueName
     *
     * @return int
     */
    public function getMessageCount($queueName)
    {
        return $this->messageDriver->getMessageCount($queueName);
    }
}
