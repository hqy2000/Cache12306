<?php

namespace App\Controller;

use GuzzleHttp\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QueryController extends AbstractController
{
    /**
     * @Route("/12306/leftTicket", methods="GET")
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
                $response = $client->request("GET", "https://kyfw.12306.cn/otn/leftTicket/query?leftTicketDTO.train_date=$date&leftTicketDTO.from_station=$from&leftTicketDTO.to_station=$to&purpose_codes=$purpose");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trains = $contents["data"]["result"];
                    $trains_simplify = array_map(function ($train) {
                        $details = explode("|", $train);
                        return [
                            $details[2], // 车号
                            $details[3], // 车次
                            $details[4], // 起点站
                            $details[5], // 终点站
                            $details[6], // 出发站
                            $details[16], // 出发站编号
                            $details[7], // 到达站
                            $details[17], // 到达站编号
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

                    $trains_implode = array_map(function ($train) {
                        return implode("|", $train);
                    }, $trains_simplify);
                    if(count($trains_implode) > 0) {
                        $cache->set($cacheKey, json_encode($trains_implode));
                        $cache->expire($cacheKey, 1800);
                    }
                    if ($debug)
                        return $this->response($trains_simplify, "12306");
                    else
                        return $this->response($trains_implode, "12306");

                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }
    }

    /**
     * @Route("/12306/queryByTrainNo", methods="GET")
     */
    public function queryByTrainNo(Request $request) {
        $train = $request->query->get("train");
        $from = $request->query->get("from");
        $to = $request->query->get("to");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        if(!preg_match('/^[a-zA-Z0-9]*$/m', $train))
            return $this->response("车号不正确", null, 400);
        if(!preg_match('/^[A-Z]{3}$/m', $from))
            return $this->response("起点站代码不正确", null, 400);
        if(!preg_match('/^[A-Z]{3}$/m', $to))
            return $this->response("终点代码不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $cache = $this->getCache();
        $cacheKey = "queryByTrainNo.$train.$from.$to.$date";
        if ($cache->exists($cacheKey) && !$debug) {
            $trainDetails = json_decode($cache->get($cacheKey), true);
            return $this->response($trainDetails, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET", "https://kyfw.12306.cn/otn/czxx/queryByTrainNo?train_no=$train&from_station_telecode=$from&to_station_telecode=$to&depart_date=$date");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trainDetails = $contents["data"]["data"];
                    $cache->set($cacheKey, json_encode($trainDetails));
                    $cache->expire($cacheKey, 1800);
                    return $this->response($trainDetails, "12306");
                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }
    }

    /**
     * @Route("/12306/czxx", methods="GET")
     */
    public function czxx(Request $request) {
        $station = $request->query->get("station");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        if(!preg_match('/^[A-Z]{3}$/m', $station))
            return $this->response("车站代码不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $cache = $this->getCache();
        $cacheKey = "czxx.$station.$date";
        if ($cache->exists($cacheKey) && !$debug) {
            $trainDetails = json_decode($cache->get($cacheKey), true);
            return $this->response($trainDetails, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET", "https://kyfw.12306.cn/otn/czxx/query?train_start_date=$date&train_station_code=$station");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trainDetails = $contents["data"]["data"];
                    $cache->set($cacheKey, json_encode($trainDetails));
                    $cache->expire($cacheKey, 1800);
                    return $this->response($trainDetails, "12306");
                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }
    }

    /**
     * @Route("/12306/search", methods="GET")
     */
    public function search(Request $request) {
        $train = $request->query->get("train");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        if(!preg_match('/^([TKDGCLZAY]|[1-7]){1}\d{1,4}$/m', $train))
            return $this->response("车次不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $date = str_replace("-","", $date);
        $cache = $this->getCache();
        $cacheKey = "czxx.$train.$date";

        if ($cache->exists($cacheKey) && !$debug) {
            $trainNo = $cache->get($cacheKey);
            return $this->response($trainNo, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET", "https://search.12306.cn/search/v1/train/search?keyword=$train&date=$date");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true) {
                    $trainDetails = $contents["data"];
                    foreach ($trainDetails as $train) {
                        $trainCode = $train["station_train_code"];
                        $trainNo = $train["train_no"];
                        $cacheTempKey = "czxx.$trainCode.$date";
                        $cache->set($cacheTempKey, $trainNo);
                        $cache->expire($cacheTempKey, 72000);
                    }
                    if ($cache->exists($cacheKey) && !$debug) {
                        $trainNo = $cache->get($cacheKey);
                        return $this->response($trainNo, "12306");
                    } else {
                        $trainNo = $cache->get($cacheKey);
                        return $this->response("找不到相关信息", null, 400);
                    }
                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }




    }
    /**
     * @Route("/12306/queryTrainInfo", methods="GET")
     */
    public function queryTrainInfo(Request $request) {
        $train = $request->query->get("train");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        if(!preg_match('/^[a-zA-Z0-9]*$/m', $train))
            return $this->response("车号不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $cache = $this->getCache();
        $cacheKey = "queryTrainInfo.$train.$date";
        if ($cache->exists($cacheKey) && !$debug) {
            $trainDetails = json_decode($cache->get($cacheKey), true);
            return $this->response($trainDetails, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET", "https://kyfw.12306.cn/otn/queryTrainInfo/query?leftTicketDTO.train_no=$train&leftTicketDTO.train_date=$date&rand_code=");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trainDetails = $contents["data"]["data"];
                    $cache->set($cacheKey, json_encode($trainDetails));
                    $cache->expire($cacheKey, 72000);
                    return $this->response($trainDetails, "12306");
                } else {
                    return $this->response("服务器错误", null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }
    }
}
