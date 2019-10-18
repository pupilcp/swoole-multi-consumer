<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Library;

/**
 * redis操作类
 * 说明，任何为false的串，存在redis中都是空串。
 * 只有在key不存在时，才会返回false。
 * 这点可用于防止缓存穿透.
 */
class RedisLib
{
    private $redis           = null;
    private $port            = 6379;
    private $host            = null;
    private $timeout         = 5;
    private $database        = 0;
    private static $instance = null;

    private function __construct($config)
    {
        try {
            $this->redis = new \Redis();
            $this->host  = $config['host'] ?? null;
            if (empty($this->host)) {
                throw new \Exception('Redis host can not empty');
            }
            $this->port  = $config['port'] ?? $this->port;
            $this->redis->connect($this->host, $this->port, $config['timeout'] ?? $this->timeout);
            if (isset($config['password']) && $config['password']) {
                $this->redis->auth($config['password']);
            }
            $this->database = $config['database'] ?? $this->database;
            $this->redis->select($this->database);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function __clone()
    {
    }

    public static function getInstance($config, $single = true)
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

    /**
     * 为hash表设定一个字段的值
     *
     * @param string $key   缓存key
     * @param string $field 字段
     * @param string $value 值
     *
     * @return bool
     */
    public function set($key, $field, $value = null)
    {
        return $this->redis->set($key, $field, $value);
    }

    public function sismember($key, $field)
    {
        return $this->redis->sisMember($key, $field);
    }

    /**
     * 得到一个key.
     *
     * @param mixed $key
     *
     * @return string
     */
    public function get($key)
    {
        return $this->redis->get($key);
    }

    /**
     * 设置一个有过期时间的key.
     *
     * @param mixed $key
     * @param mixed $expire
     * @param mixed $value
     *
     * @return
     */
    public function setex($key, $expire, $value)
    {
        return $this->redis->setex($key, $expire, $value);
    }

    /**
     * 返回集合中所有元素.
     *
     * @param mixed $key
     *
     * @return
     */
    public function smembers($key)
    {
        return $this->redis->sMembers($key);
    }

    /**
     * 添加集合。由于版本问题，扩展不支持批量添加。这里做了封装.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return
     */
    public function sadd($key, $value)
    {
        return $this->redis->sAdd($key, $value);
    }

    /**
     * 返回无序集合的元素个数.
     *
     * @param mixed $key
     *
     * @return
     */
    public function scard($key)
    {
        return $this->redis->sCard($key);
    }

    /**
     * 从集合中删除一个元素.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return
     */
    public function srem($key, $value)
    {
        return $this->redis->sRem($key, $value);
    }

    /**
     * 从集合中随机删除一个元素.
     *
     * @param mixed $key
     *
     * @return
     */
    public function spop($key)
    {
        return $this->redis->sPop($key);
    }

    /**
     * 删除指定key.
     *
     * @param mixed $key
     *
     * @return
     */
    public function del($key)
    {
        return $this->redis->del($key);
    }

    /**
     * 增加key的值.
     *
     * @param mixed $key
     *
     * @return int
     */
    public function incr($key)
    {
        return $this->redis->incr($key);
    }

    /**
     * 判断一个key值是不是存在.
     *
     * @param mixed $key
     *
     * @return
     */
    public function exists($key)
    {
        return $this->redis->exists($key);
    }

    /**
     * 为一个key设定过期时间 单位为秒.
     *
     * @param mixed $key
     * @param mixed $expire
     *
     * @return
     */
    public function expire($key, $expire)
    {
        return $this->redis->expire($key, $expire);
    }

    /**
     * 返回一个key还有多久过期，单位秒.
     *
     * @param mixed $key
     *
     * @return
     */
    public function ttl($key)
    {
        return $this->redis->ttl($key);
    }

    /**
     * 关闭服务器链接.
     */
    public function close()
    {
        $this->redis->close();
    }

    public function __destruct()
	{
		$this->close();
	}
}
