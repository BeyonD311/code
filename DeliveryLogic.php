<?php
namespace App\Delivery;

use Bitrix\Sale\Delivery\Services\Table;
use Bitrix\Sale\Order as Order;
use App\Partner\PartneryTable;

class DeliveryLogic
{
    protected $order;
    protected $partner;
    protected $arAddress;
    protected $arResult;
    protected $arVisibleDelivery;
    protected $arConditionNumbers;
    protected $arAdressVisible;
    protected $arAdressVisiblePartially;

    public function __construct(Order $order = null, string $partnerXmlId, array $arAddress)
    {
        $this->order = $order;
        $this->partner = $partnerXmlId;
        $this->arAddress = $arAddress;
        $this->arVisibleDelivery = array(
            "ratesExpenseClient",
            "partialCompensation",
            "free_driver",
            "pickup",
            "deliveryOurCar",
        );
        $this->arConditionNumbers = array(
            "PRICE_3000" => 3000,
            "PRICE_50000" => 50000,
            "PRICE_15000" => 15000,
            "PRICE_40000" => 40000,
        );
        $this->arAdressVisible = array(
            "MSK" => false,
            "MSK_C" => false,
            "SPB" => false,
            "SPB_C" => false,
            "OTHER" => false,
        );
        $this->arAdressVisiblePartially = array(
            "MSK" => false,
            "MSK_C" => false,
            "SPB" => false,
            "SPB_C" => false,
        );

        $this->arResult = $this->run();
    }

    /**
     * Получаем поля партнера
     * @return array
     */
    private function getPartner(): array
    {
        $rsPartner = PartneryTable::getList([
            'filter' => [
                'UF_XML_ID' => $this->partner
            ],
            'select' => [
                'ID',
                'UF_XML_ID',
                'UF_ADRESDOSTAVKIPART',
                'UF_ADRESDOSTAVKISPB',
                'UF_BESPLATNAYADOSTAV',
                'UF_ESTDOVERENNOST',
            ]
        ]);

        return $rsPartner->fetch();
    }

    /**
     * Проверка на бесплатную доставку
     * XML - free_driver
     * @param $arPartner
     * @return bool
     */
    private function checkForFreeShipping($arPartner): bool
    {
        $bResult = false;
        $arOrderPrice = $this->order->getPrice();
        // Если партнер является топовым и заполнен адрес партнера АдресДоставкиПартнера но пустой адрес для АдресДоставкиСПБ
        if (trim($arPartner["UF_BESPLATNAYADOSTAV"]) === "true" && $arPartner["UF_ADRESDOSTAVKIPART"] && empty(trim($arPartner["UF_ADRESDOSTAVKISPB"]))) {
            // Разблокируем радиокнопку с адресом Москва
            $this->arAdressVisible["MSK"] = true;
            $bResult = true;
        }

        // Если цена заказа более 50000 и заполен адрес партнера АдресДоставкиПартнера
        if ($arOrderPrice > $this->arConditionNumbers["PRICE_50000"] && !empty(trim($arPartner["UF_ADRESDOSTAVKIPART"]))) {
            // Разблокируем радиокнопку с адресом Москва
            $this->arAdressVisible["MSK"] = true;
            $bResult = true;
        }
        //Если цена заказа более 50000 и нет бесплаьной доставки
        if($arOrderPrice > $this->arConditionNumbers["PRICE_50000"] &&
            (trim($arPartner["UF_BESPLATNAYADOSTAV"]) !== "true" || empty($arPartner["UF_BESPLATNAYADOSTAV"]))
        ) {
            $this->arAdressVisible['MSK_C'] = true;
            $bResult = true;
        }

        // Если цена заказа более 15000 и заполен адрес партнера АдресДоставкиСПБ
        if ($arOrderPrice > $this->arConditionNumbers["PRICE_15000"] && !empty(trim($arPartner["UF_ADRESDOSTAVKISPB"]))) {
            // Разблокируем радиокнопку с адресом СПБ
            $this->arAdressVisible["SPB"] = true;
            $bResult = true;
        }
        // Если цена заказа более 15000 и не заполен адрес партнера АдресДоставкиСПБ
        if ($arOrderPrice >= $this->arConditionNumbers["PRICE_15000"]) {
            // Разблокируем радиокнопку с адресом СПБ
            $this->arAdressVisible["SPB_C"] = true;
            $bResult = true;
        }
        if ($arOrderPrice >= $this->arConditionNumbers["PRICE_3000"] && $arOrderPrice <= $this->arConditionNumbers["PRICE_15000"]) {
            // Разблокируем радиокнопку с адресом СПБ
            $this->arAdressVisible["SPB_C"] = true;
            $bResult = true;
        }

        // Если цена заказа более 40000 активируем поле выбора для любого города
        if ($arOrderPrice > $this->arConditionNumbers["PRICE_40000"]) {
            // Разблокируем радиокнопку с адресом СПБ
            $this->arAdressVisible["OTHER"] = true;
            $bResult = true;
        }
        return $bResult;
    }

    /**
     * Проверка на частичную компенсацию
     * XML - partialCompensation
     */
    private function checkFroDeliveryWithCompensation($arPartner)
    {
        $bResult = false;
        $arOrderPrice = $this->order->getPrice();

        // Если цена заказа более 15000 и менее 50000 и заполен адрес партнера АдресДоставкиПартнера и не установлено свойство Бесплатная доставка
        if (
            $arOrderPrice > $this->arConditionNumbers["PRICE_15000"] &&
            $arOrderPrice < $this->arConditionNumbers["PRICE_50000"] &&
            !empty(trim($arPartner["UF_ADRESDOSTAVKIPART"])) &&
            (trim($arPartner["UF_BESPLATNAYADOSTAV"]) !== "true" || empty($arPartner["UF_BESPLATNAYADOSTAV"]))
        ) {
            // Разблокируем радиокнопку с адресом Москва
            $this->arAdressVisiblePartially["MSK"] = true;
            $this->arAdressVisiblePartially["MSK_C"] = true;
            $bResult = true;
        }

        // Если цена заказа более 3000 и менее 15000 и заполен адрес партнера АдресДоставкиПартнера и не установлено свойство Бесплатная доставка
        if (
            $arOrderPrice > $this->arConditionNumbers["PRICE_3000"] &&
            $arOrderPrice < $this->arConditionNumbers["PRICE_15000"] &&
            !empty(trim($arPartner["UF_ADRESDOSTAVKISPB"]))
        ) {
            // Разблокируем радиокнопку с адресом Москва
            $this->arAdressVisiblePartially["SPB"] = true;
            $bResult = true;
        }

        return $bResult;
    }

    /**
     * Проверка доставку нашей машиной до ТК клиента
     * XML - deliveryOurCar
     */
    private function checkFroDeliveryOurCar($arPartner)
    {
        $bResult = false;
        // Если есть сертификат
        if (trim($arPartner["UF_ESTDOVERENNOST"]) === "true") {
            $bResult = true;
        }
        return $bResult;
    }

    /**
     * Получаем доставки
     * @param $arNotVisibleDelivery
     * @return array
     */
    private function getDelivery($arNotVisibleDelivery): array
    {
        $arResult = array();
        $dbDelivery = Table::getList(array(
                'order'=>array('SORT'=>'ASC'),
                'filter'=> array(
                    'ACTIVE'=>'Y',
                    'XML_ID' => $this->arVisibleDelivery,
                    '!XML_ID' => $arNotVisibleDelivery
                )
            )
        );

        while ($arDelivery = $dbDelivery->Fetch()) {
            $arResult[$arDelivery['ID']] = $arDelivery;
        }
        return $arResult;
    }

    /**
     * Проверяем доступные партнеру доставки
     * @param $arPartner
     * @return array
     */
    private function checkDelivery($arPartner): array
    {
        $arResult = array();
        $arResult["free_driver"] = $this->checkForFreeShipping($arPartner);
        $arResult["partialCompensation"] = $this->checkFroDeliveryWithCompensation($arPartner);
        $arResult["deliveryOurCar"] = $this->checkFroDeliveryOurCar($arPartner);
        return $arResult;
    }

    /**
     * Получаем идентификаторы доставок которые не выводим
     * @param array $arCheckDelivery
     * @return array
     */
    private function getIdDelivery(array $arCheckDelivery): array
    {
        $arResult = array();

        if (count($arCheckDelivery) > 0) {

            foreach ($arCheckDelivery as $key => $value) {
                // Если false то добавляем XML доставки и не выводим ее
                if (!$value)
                    $arResult[] = $key;
            }
        }
        return $arResult;
    }

    /**
     * Адреса партнера
     * @param array $arPartner
     * @return array
     */
    private function getAddress(array $arPartner): array
    {
        $arResult = array();
        foreach ($this->arAdressVisible as $key => $value) {
            if ($value) {

                switch ($key) {
                    case "MSK":
                        $arResult[$key] = $arPartner["UF_ADRESDOSTAVKIPART"];
                        break;
                    case "MSK_C":
                        $arResult[$key] = 'Выберите адрес в пределах "МКАД"';
                        break;
                    case "SPB":
                        $arResult[$key] = $arPartner["UF_ADRESDOSTAVKISPB"];
                        break;
                    case "SPB_C":
                        $arResult[$key] = 'Выберите адрес в пределах "КАД"';
                        break;
                    case "OTHER":
                        $arResult[$key] = "";
                        break;
                }
            }
        }
        return $arResult;
    }

    /**
     * Адреса партнера
     * @param array $arPartner
     * @return array
     */
    private function getAddressPartially(array $arPartner): array
    {
        $arResult = array();

        foreach ($this->arAdressVisiblePartially as $key => $value) {

            if ($value) {

                switch ($key) {
                    case "MSK":
                        $arResult[$key] = $arPartner["UF_ADRESDOSTAVKIPART"];
                        break;
                    case "MSK_C":
                        $arResult[$key] = 'Выберите адрес в пределах "МКАД"';
                        break;
                    case "SPB":
                        $arResult[$key] = $arPartner["UF_ADRESDOSTAVKISPB"];
                        break;
                    case "SPB_C":
                        $arResult[$key] = 'Выберите адрес в пределах "КАД"';
                        break;
                }
            }
        }
        return $arResult;
    }

    /**
     * Основной метод
     * @return array
     */
    public function run(): array
    {
        $arResult = array();
        // Получаем поля партнера
        $arPartner = $this->getPartner();
        // Проверяем доступность доставки для пользователя
        $arCheckDelivery = $this->checkDelivery($arPartner);
        // Получаем доставки которые не выводим
        $arXMLDelivery = $this->getIdDelivery($arCheckDelivery);
        // Получаем доставки
        $arResult["DELIVERY_SERVICES"] = $this->getDelivery($arXMLDelivery);
        // Адреса партнера
        $arResult["DELIVERY_PARTNER_ADDRESS"] = $this->getAddress($arPartner);
        $arResult["DELIVERY_PARTNER_ADDRESS_PARTIALLY"] = $this->getAddressPartially($arPartner);

        return $arResult;
    }

    /**
     * Возвращаем результат
     * @return array
     */
    public function getResult(): array
    {
        return $this->arResult;
    }
}