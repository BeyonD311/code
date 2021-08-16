<?php

namespace App\Delivery;

use App\Delivery\SwitchInstance;
use \Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use \Bitrix\Sale\Order;
use \Bitrix\Sale\PropertyValueCollection;
use \Bitrix\Sale\Delivery\Services\Table;

Loader::includeModule("sale");

/**
 * Class Delivery
 * @package App\Delivery\
 * инициализируется в /local/php_interface/functions.php для уменьшения времени вызова
 */
class Delivery
{
    private static $arDeliveryInstance = [];
    private static $deliveryType;
    private static $instance;
    public $objectOrder;
    private $arResult = [];
    private static $basket;
    private static $arProperties = [];

    public function __construct(string $deliveryType, $order = null)
    {
        try {
            self::$instance = SwitchInstance::getInstance();
            self::$deliveryType = $deliveryType;
            $this->objectOrder = $order;
        } catch (\Exception $ex) {

        }
    }

    /**
     * @return array
     * Получение массива доставок для вывода в sale.location.selector.search
     */
    public static function getArDelivery()
    {
        $arDelivery = self::$instance ? self::$instance::getDeliveryArray() : SwitchInstance::getInstance()::getDeliveryArray();
        $arRes = [];
        $rsDelivery = Table::getList([
            'select' => ['*'],
            'filter' => [
                'ACTIVE' => 'Y',
                '=XML_ID' => $arDelivery
            ]
        ]);
        while ($res = $rsDelivery->Fetch()) {
            if ($res['XML_ID'] && !$arRes[$res['XML_ID']])
                $arRes[$res['XML_ID']] = $res;
        }

        return $arRes;
    }

    static function getPropertyDelivery():array
    {
        $arNeedProps = array(
            "СпособДоставки",
            "PICKUP_STORE_ID",
            "PICKUP_ADDRESS",
            "ТранспортнаяКомпания",
            "НазваниеТранспортнойКомпании",
            "CONTACT_NAME",
            "CONTACT_PHONE",
            "DELIVERY_ADDRESS",
//            "СтоимостьДоставки",
            "НазваниеТранспортнойКомпании",
            "АдресПунктаПриёмаГрузов",
        );
        return $arNeedProps;
    }

    static function getGroupPropertyDelivery():array
    {
        $arDelivery = array(
            "pickup" => array(
                "PICKUP_STORE_ID",
                "PICKUP_ADDRESS",
            ),
            "ratesExpenseClient" => array(
                "ТранспортнаяКомпания",
                "НазваниеТранспортнойКомпании",
                "CONTACT_NAME",
                "CONTACT_PHONE",
                "DELIVERY_ADDRESS",
//                "СтоимостьДоставки",
            ),
            "free_driver" => array(
                "DELIVERY_ADDRESS",
                "CONTACT_NAME",
                "CONTACT_PHONE",
            ),
            "partialCompensation" => array(
                "DELIVERY_ADDRESS",
                "CONTACT_NAME",
                "CONTACT_PHONE",
            ),
            "deliveryOurCar" => array(
                "НазваниеТранспортнойКомпании",
                "АдресПунктаПриёмаГрузов",
            )
        );

        return $arDelivery;
    }

    /**
     * Проверяем на заполненость доставки
     * @param $order
     * @return bool
     */
    static function checkChoiceDelivery($order): bool
    {
        // Все поля для доставки заполнены
        $bSuccessDelivery = false;
        // Список свойст для доставки
        $arPropertyDelivery = self::getPropertyDelivery();
        // Список свойств сгруппированных по доставке
        $arGroupPropertyDelivery = self::getGroupPropertyDelivery();
        // Значение нужных свойств для доставки
        $arPropertyOrder = array();
        // Перебираем свойства заказа
        $collection = $order->getPropertyCollection();

        foreach ($collection as $item)
        {
            // Получаем нужные нам свойства
            if (in_array($item->getProperty()["CODE"], $arPropertyDelivery)) {
                $arPropertyOrder[$item->getProperty()["CODE"]] = $item->getValue();
            }
        }
        // Если заполена служба доставки можем проверять нужные поля для данной доставки
        if (!empty($arPropertyOrder["СпособДоставки"])) {
            $sXmlDelivery = '';
            // Получаем XML доставки
            $dbDelivery = Table::getList(array(
                    'order'=>array('SORT'=>'ASC'),
                    'filter'=> array(
                        'ACTIVE'=>'Y',
                        'ID' => $arPropertyOrder["СпособДоставки"],
                    )
                )
            );

            if ($arDelivery = $dbDelivery->Fetch()) {
                $sXmlDelivery = $arDelivery["XML_ID"];
            }

            if (!empty($sXmlDelivery)) {
                // Получаем нужные свойства для проверки
                $arNeedPropertyDelivery = $arGroupPropertyDelivery[$sXmlDelivery];

                if (count($arNeedPropertyDelivery) > 0) {
                    $bEmpty = false;
                    // Проверяем что нужные поля для данной доставки не пустые
                    foreach ($arNeedPropertyDelivery as $value) {
                        // Если встречаем хоть одно пустое свойство выходим из цикла
                        if (empty($arPropertyOrder[$value]))
                            $bEmpty = true;
                    }
                    // Если мы не встретили пустые свойства то все хорошо и можно сохранять
                    if (!$bEmpty) {
                        $bSuccessDelivery = true;
                    }
                }
            }
        }

        return $bSuccessDelivery;
    }

    /**
     * @param $type string
     * @param $order
     *  Установка Доставок первоначальная
     */
    private function setDelivery($type, $order)
    {
        if (!self::$arDeliveryInstance[$type]) {
            self::$arDeliveryInstance[$type] = self::$instance->getDelivery($type, $order);
        }
    }

    public function run($location, array $arOrder, $all = null)
    {
        $this->setDelivery(self::$deliveryType, $arOrder);
        $object = self::$arDeliveryInstance[self::$deliveryType];
        $object->setCalculateSum($location, $all);
        $this->arResult += $object->getDeliveryInfo();
    }

    public function getResult()
    {
        return $this->arResult;
    }

    public static function concatDelivery($arOrders = []): Order
    {
        static::$basket = Basket::create(SITE_ID);
        static::concatOrders($arOrders);
        $order = Order::create(\Bitrix\Main\Context::getCurrent()->getSite(), \CUser::GetID());
        static::$basket->refresh();
        $order->appendBasket(static::$basket);
        $collectionProperty = $order->getPropertyCollection();
        foreach (self::$arProperties as $code => $value) {
            $item = $collectionProperty->createItem([
                'ID' => $value['ID'],
                'CODE' => $code
            ]);
            $item->setField('VALUE', $value['VALUE']);
            $collectionProperty->addItem($item);
        }

        return $order;
    }

    private static function concatOrders($arOrders = [])
    {
        foreach ($arOrders as $order) {
            $orderOld = Order::load($order);
            static::setItemsBasket($orderOld->getBasket()->getBasketItems());
            static::concatProperties($orderOld->getPropertyCollection());
        }

    }
    private static function setItemsBasket($arBasketItems = [])
    {
        foreach ($arBasketItems as $item) {
            $itemNew = static::$basket->createItem('catalog', $item->getProductId());
            $arFields = [
                'QUANTITY' => $item->getQuantity(),
                'PRICE' => $item->getPrice(),
                'BASE_PRICE' => $item->getPrice()
            ];
            $itemNew->setFields($arFields);
        }
    }
    private static function concatProperties($arProperties): void
    {
        foreach ($arProperties as $property) {
            $code = $property->getField('CODE');
            if(!static::$arProperties[$code]) {
                static::$arProperties[$code] = [
                    'VALUE' => $property->getField('VALUE'),
                    'ID' => $property->getField('ORDER_PROPS_ID')
                ];
            }

        }
    }
}