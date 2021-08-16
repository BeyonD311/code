<?php
namespace App\Delivery\ServicesDelivery;

use \Bitrix\Sale\Order;
use \Bitrix\Sale\Basket;
use \Bitrix\Sale\ShipmentCollection;
use \Bitrix\Sale\Delivery\Services\Manager;
use \Bitrix\Sale\Delivery\Services\Table;


class Delivery {

    protected static $orders;
    protected $order;
    protected $delivery = [];
    protected static $type;
    protected $deliveryProfile = [];
    protected $basket;
    protected $parentDelivery;

    public function __construct($order, $type)
    {
        self::$orders = $order;
        $this->order = Order::create(\Bitrix\Main\Context::getCurrent()->getSite(), \CUser::GetID());
        self::$type = $type;
        $this->basket = Basket::create(\Bitrix\Main\Context::getCurrent()->getSite());
    }

    protected function setShipmentCollection (int $deliveryId, $all)
    {
        $this->order->getPropertyCollection()->getDeliveryLocation()->setValue($all['location']['CODE']);
        $shipmentCollection = $this->order->getShipmentCollection();
        foreach ($shipmentCollection as $shipment) {
            if(!$shipment->isSystem()) $shipment->delete();
        }
        $deliveryOb = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId);
        $shipment = $shipmentCollection->createItem($deliveryOb);
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        $shipment->setField('CURRENCY', $this->order->getCurrency());
        foreach ($this->basket->getBasketItems() as $item) {
            $spItem = $shipmentItemCollection->createItem($item);
            $spItem->setQuantity($item->getQuantity());
        }
        $shipment->setWeight($all['weight']);
        $shipment->setFields([
            'DELIVERY_ID' => $deliveryId,
            'CURRENCY' => $this->order->getCurrency(),
        ]);
        $res = Manager::calculateDeliveryPrice($shipment, $deliveryId);
        /*Response*/
        $arProfile = $this->getProfile($deliveryId);
        $image = $this->parentDelivery[$arProfile["PARENT_ID"]]["LOGOTIP"] ?: $arProfile["LOGOTIP"];
        $name = $this->parentDelivery[$arProfile["PARENT_ID"]]["NAME"] ?: $arProfile["NAME"];
        $image = \CFile::GetPath($image);
        $price = $res->getDeliveryPrice();
        $period = $res->getPeriodDescription();
        if(strpos($price,"Уточняйте")) $price = "Уточняйте у вашего менеджера";
        if(strpos($period,"Уточняйте")) $period = "Уточняйте у вашего менеджера";

        $this->delivery[$deliveryId] = [
            'price' => $price,
            'period' => $period,
            'error' => $res->getErrorMessages(),
            'description' => $res->getDescription(),
            'descriptionProfile' => $arProfile['DESCRIPTION'],
            'type' => strtoupper(self::$type),
            'img' => $image,
            'name' => $name,
            'nameDelivery' => $shipment->getDeliveryName()
        ];
    }

    private function setBasketItems($order, $arData)
    {
        $orderOld = Order::load($order);
        foreach ($orderOld->getBasket()->getBasketItems() as $item) {
            $itemNew = $this->basket->createItem('catalog', $item->getProductId());
            $oldFields = $item->getFields()->getValues();
            $dimensions = unserialize($oldFields["DIMENSIONS"]);
            $dimensions['WIDTH'] = $arData['width'];
            $dimensions['HEIGHT'] = $arData['height'];
            $dimensions['LENGTH'] = $arData['length'];
            $dimensions['VOLUME'] = $arData['volume_s'];
            $arFields = [
                'QUANTITY' => $item->getQuantity(),
                'WEIGHT' => $item->getWeight(),
                'PRICE' => $item->getPrice(),
                "PRODUCT_XML_ID" => $oldFields["PRODUCT_XML_ID"],
                "DIMENSIONS" => serialize($dimensions),
                "PRODUCT_PROVIDER_CLASS" => $oldFields["PRODUCT_PROVIDER_CLASS"],
                "CATALOG_XML_ID" => $oldFields["CATALOG_XML_ID"],
                "PRODUCT_PRICE_ID" => $oldFields["PRODUCT_PRICE_ID"],
                'BASE_PRICE' => $item->getPrice(),
            ];
            var_dump($arFields);
            $itemNew->setFields($arFields);
        }
    }

    public function getShipmentCollection($order)
    {
        $shipmentCollection = $this->order->getShipmentCollection();
        return $shipmentCollection;
    }

    protected function getPropertyCollection()
    {
        return $this->order->getPropertyCollection();
    }

    public function setDeliveryLocation($location)
    {
        $prop = $this->getPropertyCollection();
        $loc = $prop->getDeliveryLocation();
        $loc->setValue($location);
    }

    protected function getDeliveryProfile()
    {
        $rsParentDelivery = Table::getList([
            'select' => ['*'],
            'filter' => [
                'ACTIVE' => 'Y',
                '=XML_ID' => self::$type
            ]
        ]);
        $arParentDelivery = $rsParentDelivery->fetchAll();
        $arParentId = [];
        foreach ($arParentDelivery as $delivery)
        {
            $arParentId[$delivery['ID']] = $delivery;
        }
        $this->parentDelivery = $arParentId;
        $rsDelivery = Table::getList([
            'filter' => [
                'ACTIVE' => 'Y',
                'PARENT_ID' => array_keys($arParentId)
            ]
        ]);
        $arDelivery = [];
        foreach ($rsDelivery->fetchAll() as $delivery)
        {
            $arDelivery[$delivery['ID']] = $delivery;
        }



        if(empty($arDelivery)) return $arParentId;

        if(empty($arParentId)) new \Exception("Доставка не найдена");

        return $arDelivery;

    }

    protected function initCalculate($all)
    {
        $weightCount = 0;
        $volume = 0;
        foreach (self::$orders as $order) {
            $this->setBasketItems($order, $all);
            $weightCount += $all["ordersInfo"][$order]['weight'];
            $volume += $all["ordersInfo"][$order]['volume'];
        }
        $all['weight'] = $weightCount;
        $all['volume'] = $volume;
        $this->deliveryProfile = $this->getDeliveryProfile();
        $this->order->appendBasket($this->basket);
        foreach ($this->deliveryProfile as $delivery) {
            $this->setShipmentCollection($delivery['ID'], $all);
        }
    }

    private function getProfile($id):array {
        return $this->deliveryProfile[$id];
    }

    public function getProfiles():array
    {
        return $this->deliveryProfile;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getDeliveryInfo()
    {
        return $this->delivery;
    }

}