<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp;

use Pupilcp\Log\Logger;
use Pupilcp\Service\Process;

class App
{
    /**
     * App constructor.
     *
     * @param array $globalConfig
     *
     * @throws
     */
    public function __construct($globalConfig)
    {
        $this->init($globalConfig);
    }

    /**
     * 启动服务
     */
    public function run()
    {
        //定时器默认创建协程，由于协程内不能fork子进程，关闭协程
        swoole_async_set(['enable_coroutine' => false]);
        $process = new Process();
        $process->run();
    }

    /**
     * 初始化.
     *
     * @param mixed $globalConfig
     *
     * @throws
     */
    private function init($globalConfig)
    {
        try {
            //1.初始化日志组件
            Smc::$logger = Logger::getLogger($globalConfig['global']['logPath'] ?? null, $globalConfig['global']['logFileName'] ?? null);
            //2.初始化配置
            if (!isset($globalConfig['global']['queueCfgCallback']) || empty($globalConfig['global']['queueCfgCallback'])) {
                throw new \Exception('queueCfgCallback配置缺失', 10001);
            }
            if ($globalConfig['global']['enableNotice'] && empty($globalConfig['global']['dingDingToken'])) {
                throw new \Exception('dingDingToken配置缺失', 10002);
            }
            if (!isset($globalConfig['redis']) || empty($globalConfig['redis'])) {
                throw new \Exception('redis配置缺失', 10003);
            }
            if (empty($globalConfig['redis']['host']) || empty($globalConfig['redis']['port']) || empty($globalConfig['redis']['database'])) {
                throw new \Exception('请完善redis配置', 10004);
            }
            $globalConfig['global']['uniqueServiceId'] = uniqid() . rand(100000, 999999); //增加启动服务的唯一标识，避免启动多个相同服务导致异常
            Smc::setGlobalConfig($globalConfig);
            Smc::$logger->log('globalConfig: ' . json_encode($globalConfig));
            $config = call_user_func_array($globalConfig['global']['queueCfgCallback'], []);
            $this->verifyQueueConfig($config);
            Smc::setConfig($config);
        } catch (\Throwable $e) {
            Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * 校验queue配置是否正确.
     *
     *
     * @param mixed $config
     *
     * @throws
     */
    private function verifyQueueConfig($config)
    {
        if (!isset($config['connection']) || empty($config['connection'])) {
            throw new \Exception('请添加队列connection配置');
        }
        if (isset($config['queues']) && !empty($config['queues'])) {
            foreach ($config['queues'] as $k => $queue) {
                if (!isset($queue['minConsumerNum']) || !isset($queue['maxConsumerNum'])) {
                    throw new \Exception(sprintf('队列：%s，请先设置minConsumerNum和maxConsumerNum', $k));
                }
                if ((int) $queue['minConsumerNum'] <= 0 || (int) $queue['maxConsumerNum'] <= 0) {
                    throw new \Exception(sprintf('队列：%s，请正确设置minConsumerNum和maxConsumerNum', $k));
                }
                if ($queue['minConsumerNum'] > $queue['maxConsumerNum']) {
                    throw new \Exception(sprintf('队列：%s，minConsumerNum不能大于maxConsumerNum', $k));
                }
                if ($queue['maxConsumerNum'] > 20) {
                    throw new \Exception(sprintf('队列：%s，maxConsumerNum不能大于20', $k));
                }
            }
        } else {
            throw new \Exception('请添加queues列表配置');
        }
    }
}
