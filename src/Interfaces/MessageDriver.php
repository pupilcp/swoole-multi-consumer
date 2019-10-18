<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Interfaces;

interface MessageDriver
{
    /**
     * 订阅.
     *
     * @param array $callbackConf 回调参数配置
     * @param array $queueConf    消息队列配置
     */
    public function subscribe(array $callbackConf, array $queueConf = []);

    /**
     * 取消订阅.
     */
    public function unsubscribe();

    public function getMessageCount($queue);
}
