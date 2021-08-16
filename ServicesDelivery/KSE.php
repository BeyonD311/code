<?php
namespace App\Delivery\ServicesDelivery;
use App\Delivery\ServicesDelivery\Delivery as D;
use App\Delivery\InterfaceDelivery\DeliveryService;

class KSE extends D implements DeliveryService {
    public function __construct($order, $type)
    {
        parent::__construct($order, $type);
    }

    public function setCalculateSum($location, $otherInfo = null)
    {
        $this->initCalculate($otherInfo);
    }

}