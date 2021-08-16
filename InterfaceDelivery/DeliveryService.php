<?php

namespace App\Delivery\InterfaceDelivery;

interface DeliveryService{
    public function setCalculateSum($location, $otherInfo = null);
}