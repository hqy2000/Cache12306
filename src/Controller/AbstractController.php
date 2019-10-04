<?php
/**
 * Created by PhpStorm.
 * User: huqin
 * Date: 2019/7/13
 * Time: 6:44
 */

namespace App\Controller;


use GuzzleHttp\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;

class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    protected function response($data, $origin = null, $code = 200) {
        $response =  new JsonResponse([
            "code" => $code,
            "data" => $data
        ], $code);
        if (!is_null($origin))
            $response->headers->add([
                "fetch-from" => $origin
            ]);
        return $response;
    }

    protected function getClient() {
        $client = new Client([
            'headers' => [
                "Origin" => "https://www.12306.cn",
                "Host" => "www.12306.cn",
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3",
                "Accept-Encoding" => "",
                "Accept-Language" => "zh-CN,zh;q=0.8",
                "Cookie" => "JSESSIONID=F6D275FCDB009686C472D1F4559B6768; BIGipServerkfzmpt=2614690058.64543.0000",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36",
                "Connection" => "keep-alive",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer" => "https://www.12306.cn/kfzmpt/lcxxcx/init",
                "Sec-Fetch-Mode" => "navigate",
                "Sec-Fetch-Site" => "none",
                "Sec-Fetch-User" => "?1"
            ]
        ]);
        return $client;
    }

    protected function getCache() {
        $client = RedisAdapter::createConnection(
            'redis://localhost'
        );
        return $client;
    }
}