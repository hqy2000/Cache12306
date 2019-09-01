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
                "Origin" => "https://kyfw.12306.cn",
                "Host" => "kyfw.12306.cn",
                "Accept" => "*/*",
                "Accept-Encoding" => "",
                "Accept-Language" => "zh-CN,zh;q=0.8",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36",
                "Connection" => "keep-alive",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer" => "https://kyfw.12306.cn/otn/leftTicket/init"
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