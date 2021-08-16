<?php


namespace App\Delivery;
use App\Delivery\ServicesDelivery\CDEK;
use App\Delivery\ServicesDelivery\KSE;
use App\Delivery\ServicesDelivery\DELLINEDEV;
use App\Delivery\ServicesDelivery\UTSR;

class SwitchInstance
{

    private static $instances = [];
    private static $type;

    private static $arServiceDeliveryInstance = [
        'sdek' => CDEK::class,
        'dellindev.shipping' => DELLINEDEV::class,
        'kse' => KSE::class,
        'utsr' => UTSR::class
    ];



    public static function getInstance() {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static();
        }
        return self::$instances[$subclass];
    }


    public function getDelivery(string $typeDelivery, $order) {
        $typeDelivery = strtolower($typeDelivery);
        if(isset(self::$arServiceDeliveryInstance[$typeDelivery])) return new self::$arServiceDeliveryInstance[$typeDelivery]($order, $typeDelivery);

        return new \Exception("not find delivery");
    }

    public static function getDeliveryArray() {
        return array_keys(self::$arServiceDeliveryInstance);
    }

}