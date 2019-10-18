<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

use Pupilcp\Smc;

class Notice
{
    const DINGDING_NOTICE    = 1;
    const EMAIL_NOTICE       = 2;
    private static $instance = null;

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
     * 发送通知入口.
     *
     * @param array $data 通知数据，根据不同的方式
     * @param int   $type 通知方式
     *
     * @return
     */
    public function notice($data = [], $type = self::DINGDING_NOTICE)
    {
        $enableNotice = Smc::getGlobalConfig()['global']['enableNotice'] ?? false;
        if (!$enableNotice) {
            return false;
        }
        switch ($type) {
            case self::DINGDING_NOTICE:
                $token = Smc::getGlobalConfig()['global']['dingDingToken'] ?? null;
                if (empty($token)) {
                    return false;
                }
                $this->sendDingding($token, $data['title'], $data['content'], $data['isAtAll'] ?? false, $data['atMobiles'] ?? []);
                break;
            case self::EMAIL_NOTICE:
                $this->sendEmail($data['title'], $data['content'], $data['email']);
                break;
            default:
                break;
        }

        return true;
    }

    /**
     * 调用钉钉接口请求发送自定义机器人消息.
     *
     * @param string $token     请求token
     * @param mixed  $content
     * @param mixed  $isAtAll
     * @param mixed  $atMobiles
     * @param mixed  $title
     *
     * @return mixed
     */
    public function sendDingding($token, $title, $content, $isAtAll = false, $atMobiles = [])
    {
        if (empty($token) || empty($content)) {
            return false;
        }
        $markdown = [
            'title' => $title,
            'text'  => $content,
        ];
        $data = [
            'msgtype'  => 'markdown',
            'markdown' => json_encode($markdown),
            'at'       => [
                'isAtAll'   => $isAtAll,
                'atMobiles' => $atMobiles,
            ],
        ];
        $apiUrl = 'https://oapi.dingtalk.com/robot/send?access_token=' . $token;
        $result = Utils::httpPost($apiUrl, json_encode($data));
        Smc::$logger->log(json_encode('钉钉接口响应：' . json_encode($result)));

        return $result;
    }

    /**
     * 发送email.
     *
     * @param mixed $title
     * @param mixed $content
     * @param mixed $email
     */
    private function sendEmail($title, $content, $email)
    {
    }
}
