<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

use Pupilcp\App;
use Pupilcp\Library\AmqpLib;
use Pupilcp\Log\Logger;
use Pupilcp\Smc;

class Process
{
    /**
     * Process constructor.
     */
    private $mpid                  = null;
    private $masterPidFile         = null;
    private $works                 = [];
    private $smcServerStatusTime   = 120; //单位：s
    private $queueStatusTime       = 60; //单位：s
    private $checkConfigTime       = 60; //单位：s
    private $masterProcessName     = null;
    private $minConsumerNum        = 1;
    private $maxConsumerNum        = 20;
    private $startTime             = null;
    private $timerPid              = null;
    private $masterPath            = null;
    private $serverStatusFile      = 'smc-server.status';

    private $rebootChildProcessFlag = 'rebootChildProcessFlag';
    private $swooleTable            = null;

    public function __construct()
    {
        $globalConfig              = Smc::getGlobalConfig()['global'];
        $masterProcessName         = $globalConfig['masterProcessName'];
        $masterPath                = $globalConfig['logPath'] . DIRECTORY_SEPARATOR . $masterProcessName;
        if (!is_dir($masterPath) || !file_exists($masterPath)) {
            mkdir($masterPath, 0755, true);
        }
        $this->masterPidFile = $masterPath . DIRECTORY_SEPARATOR . 'master.pid';
        $this->masterPath    = $masterPath;
    }

    /**
     * 启动服务.
     */
    public function start()
    {
        try {
            $this->init();
            $this->createSwooleTable();
            $this->initConsumers();
            $this->registerSignal();
            $this->registerTimer();
        } catch (\Throwable $e) {
            printf($e->getMessage());
            Smc::$logger->log($e->getMessage(), Logger::LEVEL_INFO);
        }
    }

    /**
     * 停止服务.
     */
    public function stop()
    {
        $mpid = null;
        if (file_exists($this->masterPidFile)) {
            $mpid = (int) file_get_contents($this->masterPidFile);
        }
        if (empty($mpid)) {
            exit('smc-server服务未启动' . PHP_EOL);
        }
        if (\Swoole\Process::kill($mpid, 0)) {
            \Swoole\Process::kill($mpid, SIGTERM);
        } else {
            exit('smc-server服务未启动' . PHP_EOL);
        }
    }

    /**
     * 重启多进程.
     */
    public function restart()
    {
        $mpid = null;
        if (file_exists($this->masterPidFile)) {
            $mpid = (int) file_get_contents($this->masterPidFile);
        }
        if (empty($mpid)) {
            exit('smc-server服务未启动' . PHP_EOL);
        }
        if (\Swoole\Process::kill($mpid, 0)) {
            \Swoole\Process::kill($mpid, SIGUSR1);
        } else {
            exit('smc-server服务未启动' . PHP_EOL);
        }
    }

    /**
     * 查询状态.
     */
    public function getStatus()
    {
        $mpid = null;
        if (file_exists($this->masterPidFile)) {
            $mpid = (int) file_get_contents($this->masterPidFile);
        }
        if (empty($mpid)) {
            exit('smc-server服务未启动' . PHP_EOL);
        }
        if (\Swoole\Process::kill($mpid, 0)) {
            $statusLog = $this->masterPath . DIRECTORY_SEPARATOR . $this->serverStatusFile;
            $content   = null;
            if (is_file($statusLog)) {
                $content = file_get_contents($statusLog);
            }
            $smcServerStatusTime = Smc::getGlobalConfig()['global']['smcServerStatusTime'];
            printf($content ? $content : (($smcServerStatusTime ? ('暂无状态数据，请在' . $smcServerStatusTime . '秒后再查看') : '请开启定时监测smc-server状态[配置：smcServerStatusTime]')));
        } else {
            exit('smc-server服务未启动' . PHP_EOL);
        }
    }

    /**
     * 创建共享内存.
     */
    private function createSwooleTable()
    {
        $table = new \Swoole\Table(1024);
        $table->column('reboot', \Swoole\Table::TYPE_INT, 1);
        $table->create();
        $this->swooleTable = $table;
    }

    /**
     * 主进程不存在，子进程也退出.
     */
    private function checkMpid(&$worker)
    {
        if (!\Swoole\Process::kill($this->mpid, 0)) {
            $worker->exit();
            Smc::$logger->log('Master process exited, I [' . $worker->pid . '] also quit', Logger::LEVEL_ERROR);
        }
    }

    private function init()
    {
        $globalConfig = Smc::getGlobalConfig()['global'];
        if (is_file($this->masterPidFile)) {
            $oldmasterPid = file_get_contents($this->masterPidFile);
            if ($oldmasterPid && is_numeric($oldmasterPid) && \Swoole\Process::kill($oldmasterPid, 0)) {
                throw new \Exception('smc-server服务正在运行', 10001);
                //\Swoole\Process::kill($oldmasterPid);
            }
        }
        if (is_file($this->masterPath . DIRECTORY_SEPARATOR . $this->serverStatusFile)) {
            file_put_contents($this->masterPath . DIRECTORY_SEPARATOR . $this->serverStatusFile, '');
        }
        $this->mpid                = posix_getpid();
        $this->startTime           = time();
        file_put_contents($this->masterPidFile, $this->mpid);
        $this->checkConfigTime     = $globalConfig['checkConfigTime'] ?? $this->checkConfigTime;
        $this->smcServerStatusTime = $globalConfig['smcServerStatusTime'] ?? $this->smcServerStatusTime;
        $this->queueStatusTime     = $globalConfig['queueStatusTime'] ?? $this->queueStatusTime;
        $this->renameProcessName($globalConfig['masterProcessName']);
        $this->masterProcessName   = $globalConfig['masterProcessName'];
        Smc::$logger->log('smc-server start, master pid: ' . $this->mpid . PHP_EOL);
    }

    /**
     * 初始化消费者.
     *
     * @param null|mixed $queueName
     */
    private function initConsumers($queueName = null)
    {
        $queues = Smc::getConfig()['queues'] ?? [];
        if (!empty($queues)) {
            foreach ($queues as $queue) {
                if (null === $queueName || (null !== $queueName && $queueName == $queue['queueName'])) {
                    //清空队列的worker记录
                    Smc::cleanWorkers($queue['queueName']);
                    $minConsumerNum = (int) $queue['minConsumerNum'];
                    $minConsumerNum = $minConsumerNum <= 0 ? $this->minConsumerNum : $minConsumerNum;
                    for ($i = 1; $i <= $minConsumerNum; $i++) {
                        $this->createProcess($queue);
                    }
                }
            }
        }
    }

    /**
     * 兼容重命名进程名称.
     *
     * @param mixed $name
     */
    private function renameProcessName($name)
    {
        if (PHP_OS != 'Darwin' && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        }
    }

    /**
     * 创建进程.
     *
     * @param array $queueConf 队列配置
     *
     * @return int
     */
    private function createProcess($queueConf = null)
    {
        $process = new \Swoole\Process(function (\Swoole\Process $worker) use ($queueConf) {
            $this->renameProcessName(sprintf('smc-worker-%s', $queueConf['queueName']));
            $this->checkMpid($worker);
            try {
                $config = Smc::getConfig()['connection'];
                $ampq = AmqpLib::getInstance($config['host'], $config['port'], $config['user'], $config['pass'], $config['vhost'], $config['exchange'], $config['timeout'] ?? null);
                $channel = new \AMQPChannel($ampq::$ampqConnection);
                $channel->setPrefetchCount($queueConf['prefetchCount'] ?? 10);
                $queue = new \AMQPQueue($channel);
                $queue->setName($queueConf['queueName'] ?? null);
                $queue->bind(Smc::getConfig()['connection']['exchange'], $queueConf['routeKey'] ?? null);
                $baseApplication = '\Pupilcp\Base\BaseApplication';
                if (isset(Smc::getGlobalConfig()['global']['baseApplication']) && class_exists(Smc::getGlobalConfig()['global']['baseApplication'])) {
                    $baseApplication = Smc::getGlobalConfig()['global']['baseApplication'];
                }
                $baseApp   = new $baseApplication();
                $startTime = time();
                $queue->consume(function (\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($baseApp, $queueConf, &$startTime, $worker) {
                    $baseApp->run(['command' => $queueConf['callback'][0], 'action' => $queueConf['callback'][1], 'msg' => $envelope->getBody()]);
                    $queue->ack($envelope->getDeliveryTag());
                    //子进程执行时间超过设定时间，退出并重启
                    if (isset(Smc::getGlobalConfig()['global']['childProcessMaxExecTime']) && Smc::getGlobalConfig()['global']['childProcessMaxExecTime'] && (time() - $startTime > (int) Smc::getGlobalConfig()['global']['childProcessMaxExecTime'])) {
						$startTime = time();
                        $msg = '队列[' . $queueConf['queueName'] . ']-子进程[' . $worker->pid . ']执行时间超过设定时间，退出并重启';
                        Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $msg]);
                        throw new \Exception($msg);
                    }
                });
            } catch (\Throwable $e) {
                $this->swooleTable->set($this->rebootChildProcessFlag, ['reboot' => 1]);
                Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            }
        }, false, false);
        $pid                                    = $process->start();
        $this->works[$queueConf['queueName']][] = $pid;
        Smc::addWorker($queueConf['queueName'], $pid);

        return $pid;
    }

    /**
     * 清除储存的子进程pid.
     *
     * @param int $pid 子进程ID
     *
     * @return mixed
     */
    private function cleanWorkerPid($pid)
    {
        if (!empty($this->works)) {
            foreach ($this->works as $queueName => $pidArr) {
                if (in_array($pid, $pidArr)) {
                    $index = array_search($pid, $pidArr);
                    unset($this->works[$queueName][$index]);
                    break;
                }
            }
        }
        if (!empty(Smc::getConfig()['queues'])) {
            foreach (Smc::getConfig()['queues'] as $name => $queue) {
                if (false !== Smc::deleteWorker($name, $pid)) {
                    return $name;
                }
            }
        }

        return false;
    }

    /**
     * 退出smc-server.
     *
     * @param int   $signo 信号码
     * @param mixed $exit
     */
    private function exitSmcServer($signo = SIGTERM, $exit = true)
    {
        if (!empty(Smc::getConfig()['queues'])) {
            foreach (Smc::getConfig()['queues'] as $name => $queue) {
                $pidArr = Smc::getWorkers($name);
                if (empty($pidArr)) {
                    $pidArr = $this->works[$name] ?? [];
                }
                if (count($pidArr)) {
                    foreach ($pidArr as $pid) {
                        if ($pid && \Swoole\Process::kill($pid, 0)) {
                            \Swoole\Process::kill($pid, $signo);
                            Smc::$logger->log(sprintf('smc-server接收到信号：%s，子进程：%d退出' . PHP_EOL, $signo, $pid));
                        }
                    }
                    Smc::cleanWorkers($name);
                }
            }
            $this->works = null;
            if ($exit) {
                //主进程退出，清空配置缓存
                Smc::delConfigHash();
                if ($this->timerPid && \Swoole\Process::kill($this->timerPid, 0)) {
                    \Swoole\Process::kill($this->timerPid, $signo);
                }
                if (is_file($this->masterPidFile) && file_exists($this->masterPidFile)) {
                    unlink($this->masterPidFile);
                }
                $msg = sprintf('smc-server接收到信号：%s，master主进程：%d退出' . PHP_EOL, $signo, $this->mpid);
                Smc::$logger->log($msg);
                die($msg);
            }
        }
    }

    /**
     * 注册用户信号.
     */
    private function registerSignal()
    {
        //强行退出主进程
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->exitSmcServer($signo);
        });
        //强行退出主进程
        \Swoole\Process::signal(SIGKILL, function ($signo) {
            $this->exitSmcServer($signo);
        });
        //重启消费者子进程
        \Swoole\Process::signal(SIGUSR1, function ($signo) {
            Smc::$logger->log('【系统提示】接收到系统命令，重启消费者子进程');
            $this->swooleTable->set($this->rebootChildProcessFlag, ['reboot' => 0]);
            $this->exitSmcServer($signo, false);
            $this->initConsumers();
        });
        //重新注册定时器
        \Swoole\Process::signal(SIGUSR2, function ($signo) {
            if ($this->timerPid && \Swoole\Process::kill($this->timerPid, 0)) {
                \Swoole\Process::kill($this->timerPid);
            }
            Smc::$logger->log('【系统提示】接收到系统命令，重新注册定时器');
            $this->registerTimer();
        });
        //回收子进程
        \Swoole\Process::signal(SIGCHLD, function ($signo) {
            $this->processWait();
        });
    }

    /**
     * 重启.
     *
     * @param mixed $ret
     *
     * @throws
     *
     * @return bool
     */
    private function rebootProcess($ret)
    {
        $pid       = $ret['pid'];
        $queueName = $this->cleanWorkerPid($pid);
        $rebootRow = $this->swooleTable->get($this->rebootChildProcessFlag);
        if (false !== $queueName && isset($rebootRow['reboot']) && 1 == $rebootRow['reboot']) {
            $newPid = $this->createProcess(Smc::getConfig()['queues'][$queueName]);
            Smc::$logger->log("rebootProcess: {$newPid} Done" . PHP_EOL);
            sleep(3);

            return true;
        }
        Smc::$logger->log('rebootProcess Error: no pid');
    }

    /**
     * 回收子进程.
     */
    private function processWait()
    {
        while (true) {
            $ret = \Swoole\Process::wait(false);
            if ($ret) {
                try {
                    $this->rebootProcess($ret);
                } catch (\Throwable $e) {
                    Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
                }
            } else {
                break;
            }
        }
    }

    /**
     * 注册定时器事件.
     */
    private function registerTimer()
    {
        $process = new \Swoole\Process(function (\Swoole\Process $worker) {
            $this->renameProcessName('smc-check-worker');
            isset(Smc::getGlobalConfig()['global']['queueStatusTime']) && null !== Smc::getGlobalConfig()['global']['queueStatusTime'] && $this->checkQueuesStatus($worker);
            isset(Smc::getGlobalConfig()['global']['smcServerStatusTime']) && null !== Smc::getGlobalConfig()['global']['smcServerStatusTime'] && $this->getSmcServerInfo($worker);
            isset(Smc::getGlobalConfig()['global']['checkConfigTime']) && null !== Smc::getGlobalConfig()['global']['checkConfigTime'] && $this->checkConfigStatus();
        });
        $this->timerPid = $process->start();
        if (strcmp(SWOOLE_VERSION, '4.4.0') > 0) {
            //兼容4.4以上版本，由于信号注册不再作为eventloop block的条件
            \Swoole\Timer::tick(60 * 1000, function ($timerId) {
            });
        }
    }

    /**
     * 检测主进程状态.
     */
    private function checkMasterProcess()
    {
        return \Swoole\Process::kill($this->mpid, 0);
    }

    /**
     * 检测队列状态.
     *
     * @param mixed $worker
     */
    private function checkQueuesStatus($worker)
    {
        \Swoole\Timer::tick($this->queueStatusTime * 1000, function ($timerId, $worker) {
            if (!empty(Smc::getConfig()['queues'])) {
                foreach (Smc::getConfig()['queues'] as $queueConf) {
                    try {
                        $result = Monitor::getInstance()->checkOverStock($queueConf);
                        //计算需要的消费者数量
                        $needConsumerNum = $queueConf['minConsumerNum'] + ceil($result['msgCount'] / $queueConf['warningNum']);
                        if ($result['msgCount'] > 0) {
                            $needConsumerNum -= 1;
                        }
                        //不超过设置的最大消费者数量
                        $maxConsumerNum = isset($queueConf['maxConsumerNum']) && $queueConf['maxConsumerNum'] < $this->maxConsumerNum ? $queueConf['maxConsumerNum'] : $this->maxConsumerNum;
                        $needConsumerNum = $needConsumerNum > $maxConsumerNum ? $maxConsumerNum : $needConsumerNum;
                        $incrConsumerNum = $needConsumerNum - Smc::getWorkerCount($queueConf['queueName']);
                        if (true === $result['status']) {
                            $logMsg = sprintf('队列名： %s，消息积压数量：%d' . PHP_EOL, $queueConf['queueName'], $result['msgCount']);
                            //连续监控超出预警值
                            if ($incrConsumerNum > 0) {
                                //当前消费者数量未到达需要的数量，增加对应数量的消费者
                                for ($i = 1; $i <= $incrConsumerNum; $i++) {
                                    $this->createProcess($queueConf);
                                }
                                $logMsg .= '消息积压过多，系统自动增加消费者：' . $incrConsumerNum . PHP_EOL;
                                $logMsg .= $this->getSmcServcerStatus($worker);
                            }
                            Smc::$logger->log($logMsg);
                            Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $logMsg]);
                        } elseif (null !== $result['msgCount']) {
                            //根据队列的消息数量进行消费者数量的自动调整，减少消费者数量
                            if ($incrConsumerNum < 0) {
                                $incrConsumerNum = (int) abs($incrConsumerNum);
                                if (round(($result['msgCount'] % $queueConf['warningNum']) / $queueConf['warningNum'], 2) >= 0.8) {
                                    //如果取模值与预警值比例少于0.8，减少一个消费者，避免在预警值临界点可能会频繁出现拉起或销毁子进程的情况
                                    $incrConsumerNum -= 1;
                                }
                                for ($i = 1; $i <= $incrConsumerNum; $i++) {
                                    $pid = Smc::deleteWorker($queueConf['queueName']);
                                    \Swoole\Process::kill((int) $pid);
                                }
                                if ($incrConsumerNum > 0) {
                                    $logMsg = sprintf('队列名： %s，消息数量：%d，已下降，系统自动减少消费者：%d' . PHP_EOL, $queueConf['queueName'], $result['msgCount'], $incrConsumerNum);
                                    $logMsg .= $this->getSmcServcerStatus($worker);
                                    Smc::$logger->log($logMsg);
                                    Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $logMsg]);
                                }
                            }
                        } else {
                        }
                    } catch (\Throwable $e) {
                        Smc::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
                    }
                }
            }
        }, $worker);
    }

    /**
     * 检测配置文件是否有修改，如果有进行热加载.
     */
    private function checkConfigStatus()
    {
        \Swoole\Timer::tick($this->checkConfigTime * 1000, function ($timerId) {
            if (isset(Smc::getGlobalConfig()['global']['queueCfgCallback']) && Smc::getGlobalConfig()['global']['queueCfgCallback']) {
                $config = call_user_func_array(Smc::getGlobalConfig()['global']['queueCfgCallback'], []);
                $configJson = json_encode($config);
                if ($configJson && !Smc::cmpConfigHash($configJson)) {
                    $msg = 'smc-server检测到队列配置发生变化，系统将重启子进程';
                    Smc::$logger->log($msg);
                    Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $msg]);
                    //配置发生变化，重新加载配置，并重启子进程
                    Smc::setConfigHash($configJson);
                    \Swoole\Process::kill($this->mpid, SIGUSR1);
                }
            }
        });
    }

    /**
     * 获取smc-server服务的状态信息.
     *
     * @param mixed $worker
     *
     * @return string
     */
    private function getSmcServerInfo($worker)
    {
        \Swoole\Timer::tick($this->smcServerStatusTime * 1000, function ($timerId, $worker) {
            if (!$this->checkMasterProcess()) {
                $msg = sprintf('【Error】检测到主进程：%d不存在，强制退出子进程', $this->mpid);
                Smc::$logger->log($msg, Logger::LEVEL_ERROR);
                Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $msg]);
                $this->exitSmcServer(SIGKILL);
            }
            $statusInfo = $this->getSmcServcerStatus($worker);
            file_put_contents($this->masterPath . DIRECTORY_SEPARATOR . $this->serverStatusFile, $statusInfo);
            Smc::$logger->log($statusInfo);
        }, $worker);
    }

    /**
     * 获取smc-server服务的状态信息.
     *
     * @param mixed $worker
     *
     * @return string
     */
    private function getSmcServcerStatus($worker = null)
    {
        $statusInfo  = PHP_EOL;
        $statusInfo .= 'AppName: Swoole-Multi-Consumer Version: ' . App::SMC_SERVER_VERSION . PHP_EOL;
        $statusInfo .= 'PHP Version:' . PHP_VERSION . '    Swoole Version: ' . SWOOLE_VERSION . PHP_EOL;
        $statusInfo .= Utils::getSysLoadAvg() . '   Memory Used:' . Utils::getServerMemoryUsage() . PHP_EOL;
        $statusInfo .= 'StartTime : ' . date('Y-m-d H:i:s', $this->startTime) . '   Run ' . floor((time() - $this->startTime) / (24 * 60 * 60)) . ' Days ' . floor(((time() - $this->startTime) % (24 * 60 * 60)) / (60 * 60)) . ' Hours ' . floor(((time() - $this->startTime) % 3600) / 60) . ' Minutes ' . PHP_EOL;
        $statusInfo .= '|-- Master: ' . $this->masterProcessName . ' PID: ' . $this->mpid . PHP_EOL;
        if ($worker) {
            $statusInfo .= '    |-- Smc Check Worker PID: ' . $worker->pid . PHP_EOL;
        }
        if (Smc::getConfig()['queues']) {
            foreach (Smc::getConfig()['queues'] as $k => $queue) {
                $workers = Smc::getWorkers($k);
                $statusInfo .= '    |-- Queue Name: ' . $k . '(' . (is_array($workers) ? count($workers) : 0) . ')' . PHP_EOL;
                if (!empty($workers)) {
                    foreach ($workers as $pid) {
                        $statusInfo .= '        |-- Smc Worker PID:  ' . $pid . PHP_EOL;
                    }
                } else {
                    //消费者为空，需要重启
                    $msg = '队列[' . $k . ']无消费者，重新初始化子进程';
                    Smc::$logger->log($msg, Logger::LEVEL_ERROR);
                    Notice::getInstance()->notice(['title' => 'smc-server预警提示', 'content' => $msg]);
                    $this->initConsumers($k);
                    $statusInfo .= '        |-- None' . PHP_EOL;
                }
            }
        }

        return $statusInfo;
    }
}
