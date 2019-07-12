<?php

namespace App\Controller;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QueryController extends AbstractController
{
    /**
     * @Route("/query", name="query")
     */
    public function index()
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/QueryController.php',
        ]);
    }

    /**
     * @Route("/query/leftTicket")
     */
    public function leftTicket(Request $request) {
        $from = $request->query->get("from");
        $to = $request->query->get("to");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        $purpose = "ADULT";
        if(!preg_match('/^[A-Z]{3}$/m', $from))
            return $this->response("起点站代码不正确", null, 400);
        if(!preg_match('/^[A-Z]{3}$/m', $to))
            return $this->response("终点代码不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $cache = $this->getCache();
        $cacheKey = "leftTicket.$from.$to.$date.$purpose";
        if ($cache->exists($cacheKey) && !$debug) {
            $trains_simplify = json_decode($cache->get($cacheKey), true);
            return $this->response($trains_simplify, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET","https://kyfw.12306.cn/otn/leftTicket/query?leftTicketDTO.train_date=$date&leftTicketDTO.from_station=$from&leftTicketDTO.to_station=$to&purpose_codes=$purpose");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trains = $contents["data"]["result"];
                    $trains_simplify = array_map(function($train){
                        $details = explode("|", $train);
                        return [
                            $details[3], // 车次
                            $details[4], // 起点站
                            $details[5], // 终点站
                            $details[6], // 出发站
                            $details[7], // 到达站
                            $details[8], // 出发时间
                            $details[9], // 到达时间
                            $details[10], // 历时
                            $details[11], // 是否可购买
                            $details[25], // 特等座
                            $details[32], // 商务座
                            $details[31], // 一等座
                            $details[30], // 二等座
                            $details[21], // 高级软卧
                            $details[23], // 软卧 一等卧
                            $details[33], // 动卧
                            $details[28], // 硬卧 二等卧
                            $details[24], // 软座
                            $details[29], // 硬座
                            $details[26], // 无座
                        ];
                    }, $trains);

                    $trains_implode = array_map(function($train){
                        return implode("|", $train);
                    }, $trains_simplify);
                    $cache->set($cacheKey, json_encode($trains_implode));
                    if($debug)
                        return $this->response($trains_simplify, "12306");
                    else
                        return $this->response($trains_implode, "12306");

                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response("服务器错误", null, 400);
            }
        }


    }

    private function response($data, $origin = null, $code = 200) {
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

    private function getClient() {
        $client = new Client([
            'headers' => [
                "Origin" => "https://kyfw.12306.cn",
                "Host" => "kyfw.12306.cn",
                "Accept" => "*/*",
                "Accept-Encoding" => "",
                "Accept-Language" => "zh-CN,zh;q=0.8",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36",
                "Connection" => "keep-alive",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer" => "https://kyfw.12306.cn/otn/leftTicket/init"
            ]
        ]);
        return $client;
    }

    private function getCache() {
        $client = RedisAdapter::createConnection(
            'redis://localhost'
        );
        return $client;
    }
}
