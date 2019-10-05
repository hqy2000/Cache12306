<?php
/**
 * Created by PhpStorm.
 * User: huqin
 * Date: 2019/7/14
 * Time: 3:34
 */

namespace App\Controller;
use Symfony\Component\Routing\Annotation\Route;

class DeviceController extends AbstractController
{
    /**
     * @Route("/device/version", methods="GET")
     */

    public function version() {
        return $this->response([
            "station_database" => "2019.10.05",
            "train_database" => "2019.02.01",
            "latest_ios" => "1.0.0"
        ]);
    }
}