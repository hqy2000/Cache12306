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
     * Left tickets.
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
                $response = $client->request("GET", "https://www.12306.cn/kfzmpt/lcxxcx/query?purpose_codes=$purpose&queryDate=$date&from_station=$from&to_station=$to");
                $rawContents = $response->getBody()->getContents();
                // return $this->response($rawContents);
                $contents = json_decode($rawContents, true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    if(array_key_exists("datas", $contents["data"]) && count($contents["data"]["datas"]) > 0) {
                        $trains = $contents["data"]["datas"];
                        $cache->set($cacheKey, json_encode($trains));
                        $cache->expire($cacheKey, 1800);
                        if ($debug)
                            return $this->response($trains, "12306");
                        else
                            return $this->response($trains, "12306");
                    } else {
                        return $this->response("没有符合条件的数据", null, 400);
                    }
                } else {
                    return $this->response("服务器错误".$response->getBody()->getContents(), null, 400);
                }
            } catch (\Exception $exception) {
                return $this->response($exception->getMessage(), null, 400);
            }
        }
    }

    /**
     * @Route("/12306/queryByTrainNumber", methods="GET")
     */
    public function queryByTrainNumber(Request $request) {
        $train = $request->query->get("train");
        $date = $request->query->get("date");
        $debug = $request->query->getBoolean("debug", false);
        if(!preg_match('/^([TKDGCLZAY]|[1-7]){1}\d{1,4}$/m', $train))
            return $this->response("车次不正确", null, 400);
        if(!preg_match('/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/', $date))
            return $this->response("日期格式不正确", null, 400);
        $cache = $this->getCache();
        $cacheKey = "queryByTrainNumber.$train.$date";

        if ($cache->exists($cacheKey) && !$debug) {
            $trainNo = $cache->get($cacheKey);
            return $this->response($trainNo, "cache");
        } else {
            try {
                $client = $this->getClient();
                $response = $client->request("GET", "https://www.12306.cn/kfzmpt/queryTrainInfo/query?leftTicketDTO.train_no=$train&leftTicketDTO.train_date=$date&rand_code=");
                $contents = json_decode($response->getBody()->getContents(), true);
                if ($contents["status"] === true && $contents["httpstatus"] == 200) {
                    $trainDetails = $contents["data"]["data"];
                    if(is_array($trainDetails) && count($trainDetails) > 0) {
                        $cache->set($cacheKey, json_encode($trainDetails));
                        $cache->expire($cacheKey, 72000);
                        return $this->response($trainDetails, "12306");
                    } else {
                        return $this->response("请稍后再试");
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
     * @Route("/12306/queryByTrainNo", methods="GET")
     * Train Timetable.
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
                $response = $client->request("GET", "https://www.12306.cn/kfzmpt/czxx/queryByTrainNo?train_no=$train&from_station_telecode=$from&to_station_telecode=$to&depart_date=$date");
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
     * Station information.
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
}
