<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Library;

class AmqpLib
{
    const TYPE_TOPIC   = 'topic';
    const TYPE_DIRECT  = 'direct';
    const TYPE_HEADERS = 'headers';
    const TYPE_FANOUT  = 'fanout';

    /**
     * @var AMQPConnection
     */
    public static $ampqConnection;

    protected $prefetchCount = 10;

    /**
     * @var AMQPChannel[]
     */
    protected $channels = [];

    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 5672;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $pass;

    /**
     * @var string
     */
    private $vhost = '/';
    /**
     * @var string
     */
    private $exchange = ''; //交换机
    /**
     * @var string
     */
    private $routekey = ''; //路由key

    private static $instance = null;

    /**
     * constructor.
     *
     * @param mixed      $host
     * @param mixed      $port
     * @param mixed      $user
     * @param mixed      $pass
     * @param mixed      $vhost
     * @param null|mixed $exchange
     * @param mixed      $timeout
     *
     * @throws
     */
    private function __construct($host, $port, $user, $pass, $vhost, $exchange, $timeout = 5)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->vhost    = $vhost;
        $this->exchange = $exchange;
        if (empty($this->user)) {
            throw new \Exception("Parameter 'user' was not set for AMQP connection.");
        }
        if (empty(self::$ampqConnection)) {
            $connectionArr = [
                'host'            => $this->host,
                'port'            => $this->port,
                'login'           => $this->user,
                'password'        => $this->pass,
                'vhost'           => $this->vhost,
                'connect_timeout' => $timeout,
            ];
            $class = class_exists('AMQPConnection', false);
            if ($class) {
                self::$ampqConnection = new \AMQPConnection($connectionArr);
            } else {
                throw new \Exception('please install pecl amqp extension');
            }
        }
    }

    public static function getInstance($host, $port, $user, $password, $vhost, $exchange, $timeout)
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        $instance = new self($host, $port, $user, $password, $vhost, $exchange, $timeout);
        if (!self::$ampqConnection->connect()) {
            throw new \Exception("Cannot connect to the broker!\n");
        }
        self::$instance = $instance;

        return self::$instance;
    }

    /**
     * Returns AMQP connection.
     *
     * @throws
     *
     * @return AMQPConnection
     */
    public function getConnection()
    {
        if (!self::$ampqConnection->connect()) {
            throw new \Exception("Cannot connect to the broker!\n");
        }

        return self::$ampqConnection;
    }

    /**
     * 发布消息.
     *
     * @param string $exchange   交换机
     * @param string $routingKey 路由key
     * @param string $message    消息
     *
     * @throws
     */
    public function publish($exchange = null, $routingKey = null, $message = null)
    {
        $channel = new \AMQPChannel(self::$ampqConnection);
        $ex      = new \AMQPExchange($channel);
        $ex->setName($exchange);
        $channel->startTransaction(); //开始事务
        $ex->publish($message, $routingKey);
        $channel->commitTransaction(); //提交事务
        self::$ampqConnection->disconnect();
    }

    /**
     * 订阅.
     *
     * @param mixed      $callback
     * @param null|mixed $queueConf
     *
     * @throws
     */
    public function consume($callback, $queueConf = null)
    {
        if (null === self::$ampqConnection) {
            $this->getConnection();
        }
        //channel
        $channel = new \AMQPChannel(self::$ampqConnection);
        $channel->setPrefetchCount($queueConf['prefetchCount'] ?? $this->prefetchCount);
        $queue = new \AMQPQueue($channel);
        $queue->setName($queueConf['queueName'] ?? null);
        $queue->bind($this->exchange, $queueConf['routeKey'] ?? null);
        $queue->setFlags(AMQP_DURABLE);
        try {
            $queue->consume($callback);
        } catch (\AMQPQueueException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * queue message length.
     *
     *
     * @param mixed $queueName
     *
     * @throws
     *
     * @return int
     */
    public function getMessageCount($queueName)
    {
        if (null === self::$ampqConnection) {
            $this->getConnection();
        }
        //在连接内创建一个通道
        $ch = new \AMQPChannel(self::$ampqConnection);
        $q  = new \AMQPQueue($ch);
        $q->setName($queueName);
        $q->setFlags(AMQP_PASSIVE);
        $len = $q->declareQueue();
        self::$ampqConnection->disconnect();

        return $len ?? 0;
    }
}
