<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Driver;

use Pupilcp\Interfaces\MessageDriver;

class RedisDriver implements MessageDriver
{
	public function subscribe(array $callbackConf, array $queueConf = [])
	{
		// TODO: Implement subscribe() method.
	}

	public function unsubscribe()
	{
		// TODO: Implement unsubscribe() method.
	}

}
