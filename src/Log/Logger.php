<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Log;

use Pupilcp\Smc;

class Logger
{
    const LEVEL_TRACE          = 'trace';
    const LEVEL_WARNING        = 'warning';
    const LEVEL_ERROR          = 'error';
    const LEVEL_INFO           = 'info';
    const LEVEL_PROFILE        = 'profile';
    const MAX_LOGS             = 1000;

    public $rotateByCopy       = true;
    public $maxLogFiles        = 5;
    public $maxFileSize        = 200; // in MB

    private $logPath      = '';
    private $logFileName  = 'smc-server.log';
    //单个类型log
    private $logs                 = [];
    private $logCount             = 0;
    private $dirMode              = 0755;

    private static $instance = null;

    public function __construct($logPath = null, $logFileName = null, $dirMode = 0755)
    {
        if (empty($logPath)) {
            $logPath = Smc::getGlobalConfig()['global']['logPath'] ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
        }
        $this->dirMode = $dirMode ?? $this->dirMode;
        $this->logPath = rtrim($logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, $this->dirMode, true);
        }
        $this->logFileName = Smc::getGlobalConfig()['global']['logFileName'] ?? $logFileName;
    }

    /**
     * 获取日志实例.
     *
     * @$logPath
     *
     * @param mixed      $dirMode
     * @param mixed      $logPath
     * @param null|mixed $logFileName
     *
     * @throws
     *
     * @return object
     */
    public static function getLogger($logPath = null, $logFileName=null, $dirMode = null)
    {
        if (isset(self::$instance) && null !== self::$instance) {
            return self::$instance;
        }
        self::$instance = new self($logPath, $logFileName, $dirMode);

        return self::$instance;
    }

    /**
     * 格式化日志信息.
     *
     * @param mixed $message
     * @param mixed $level
     * @param mixed $category
     * @param mixed $time
     *
     * @return string
     */
    public function formatLogMessage($message, $level, $category, $time)
    {
        return @date('Y/m/d H:i:s', $time) . " [$level] [$category] $message\n";
    }

    /**
     * 日志分类处理.
     *
     * @param mixed $message
     * @param mixed $level
     * @param mixed $category
     * @param mixed $flush
     */
    public function log($message, $level = self::LEVEL_INFO, $category = null, $flush = true)
    {
        if (empty($category)) {
            $category = $this->logFileName;
        }
        $this->logs[$category][] = [$message, $level, $category, microtime(true)];
        $this->logCount++;
        if ($this->logCount >= self::MAX_LOGS || true == $flush) {
            $this->flush($category);
        }
    }

    /**
     * 日志分类处理.
     */
    public function processLogs()
    {
        $logsAll=[];
        foreach ((array) $this->logs as $key => $logs) {
            $logsAll[$key] = '';
            foreach ((array) $logs as $log) {
                $logsAll[$key] .= $this->formatLogMessage($log[0], $log[1], $log[2], $log[3]);
            }
        }

        return $logsAll;
    }

    /**
     * 写日志到文件.
     */
    public function flush()
    {
        if ($this->logCount <= 0) {
            return false;
        }
        $logsAll = $this->processLogs();
        $this->write($logsAll);
        $this->logs     = [];
        $this->logCount = 0;
    }

    /**
     * [write 根据日志类型写到不同的日志文件].
     *
     * @param $logsAll
     *
     * @throws \Exception
     */
    public function write($logsAll)
    {
        if (empty($logsAll)) {
            return;
        }
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, $this->dirMode, true);
        }
        foreach ($logsAll as $key => $value) {
            if (empty($key)) {
                continue;
            }
            $fileName = $this->logPath . '/' . $key;

            if (false === ($fp = @fopen($fileName, 'a'))) {
                throw new \Exception("Unable to append to log file: {$fileName}");
            }
            @flock($fp, LOCK_EX);

            if (@filesize($fileName) > $this->maxFileSize * 1024 * 1024) {
                $this->rotateFiles($fileName);
            }
            @fwrite($fp, $value);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /**
     * Rotates log files.
     *
     * @param mixed $file
     */
    protected function rotateFiles($file)
    {
        for ($i = $this->maxLogFiles; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotateFile = $file . (0 === $i ? '' : '.' . $i);
            if (is_file($rotateFile)) {
                // suppress errors because it's possible multiple processes enter into this section
                if ($i === $this->maxLogFiles) {
                    @unlink($rotateFile);
                } else {
                    if ($this->rotateByCopy) {
                        @copy($rotateFile, $file . '.' . ($i + 1));
                        if ($fp = @fopen($rotateFile, 'a')) {
                            @ftruncate($fp, 0);
                            @fclose($fp);
                        }
                    } else {
                        @rename($rotateFile, $file . '.' . ($i + 1));
                    }
                }
            }
        }
    }
}
