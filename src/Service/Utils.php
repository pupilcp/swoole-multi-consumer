<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp\Service;

class Utils
{
    public static function getMillisecond()
    {
        return microtime(true);
    }

    /**
     * Get Server Memory Usage.
     *
     * @return string
     */
    public static function getServerMemoryUsage()
    {
        return round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB';
    }

    /**
     * Get Server load avg.
     *
     * @return string
     */
    public static function getSysLoadAvg()
    {
        $loadavg = function_exists('sys_getloadavg') ? array_map('round', sys_getloadavg(), [2]) : ['-', '-', '-'];

        return 'Load Average: ' . implode(', ', $loadavg);
    }
	/**
	 * http post request.
	 *
	 * @param mixed $url_mixed
	 * @param mixed $dataString
	 * @param mixed $timeoutTime
	 * @param mixed $https
	 *
	 * @return array
	 */
	public static function httpPost($url_mixed, $dataString, $timeoutTime = 5, $https = false)
	{
		$headerArr = [
			'Content-Type: application/json; charset=utf-8',
			'Content-Length: ' . strlen($dataString),
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_URL, $url_mixed);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $https);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
		if (null !== $timeoutTime) {
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutTime);
		}
		ob_start();
		curl_exec($ch);
		$response = ob_get_contents();
		ob_end_clean();

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return [
			'httpCode' => $httpCode,
			'response' => $response,
		];
	}

}
