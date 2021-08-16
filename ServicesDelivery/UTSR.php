<?php


namespace App\Delivery\ServicesDelivery;
use App\Delivery\ServicesDelivery\Delivery as D;
use App\Delivery\InterfaceDelivery\DeliveryService;
use \Bitrix\Sale\Location\LocationTable;
use Bitrix\Main\Web\HttpClient;

class UTSR extends D implements DeliveryService
{
    private $arCity = [];
    private $location;
    private static $otherInfo;

    public function __construct($order, $type)
    {
        parent::__construct($order, $type);
    }
    public function setCalculateSum($location, $otherInfo = null)
    {
        // TODO: Implement getCalculateSum() method.
        static::$otherInfo = $otherInfo;
        $this->location = $this->getLocation($location);
        $this->getCityLocation();
        $this->sendPostRequest();

    }
    private function getLocation($location): array
    {
        $loc = LocationTable::getList([
            'filter' => [
                '=CODE' => $location['CODE'],
                '=PARENTS.NAME.LANGUAGE_ID' => LANGUAGE_ID,
                '=PARENTS.TYPE.NAME.LANGUAGE_ID' => LANGUAGE_ID
            ],
            'select' => array(
                'I_ID' => 'PARENTS.ID',
                'I_NAME_RU' => 'PARENTS.NAME.NAME',
                'I_TYPE_CODE' => 'PARENTS.TYPE.CODE',
                'I_TYPE_NAME_RU' => 'PARENTS.TYPE.NAME.NAME'
            ),
            'order' => array(
                'PARENTS.DEPTH_LEVEL' => 'asc'
            )
        ]);
        $arLocation = [];
        foreach ($loc->fetchAll() as $arInfoLoc)
        {
            $arLocation[] = $arInfoLoc["I_NAME_RU"];
        }
        $arLocation = array_reverse($arLocation);
        return $arLocation;
    }

    private function getCityLocation()
    {
        $httpClient = new HttpClient();
        $httpClient->setHeader('X-PARTNER-ID','c343652e-37ef-11eb-a671-00163e47de8a');
        $httpClient->setHeader('X-PARTNER-SIGN', 'be8d082f097f0bb43dc666a03f67bd28');
        $httpClient->get('https://api.utsr.ru/v1/city/');
        $this->arCity = json_decode($httpClient->getResult(), true);
    }

    private function checkCity($arCheck)
    {

        foreach ($this->arCity as $arCity)
        {
            $key = array_search($arCity['name'], $arCheck);
            if(is_array($arCheck) && $key !== false) {
                $arCity['keyLoc'] = $key;
                return $arCity;
            }
        }
        return false;
    }

    private function getCalculate($arPost)
    {
        $httpClient = new HttpClient();
        $httpClient->setHeader('X-PARTNER-ID','c34be707-37ef-11eb-a671-00163e47de8a');
        $httpClient->setHeader('X-PARTNER-SIGN', 'b535535c638b2162');
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->post('https://api.utsr.ru/v1/calculate/', $arPost);
        $arRes = json_decode($httpClient->getResult(), true);
        $arProfile = array_map(function ($ar) {return $ar;}, $this->getDeliveryProfile());
        $arProfile = array_values($arProfile);
        $deliveryPercent = current($this->getDeliveryProfile())['CONFIG']['MAIN']['PRICE'];
        if($arRes['price']) {
            $arRes['price'] = ($arRes['price'] / 100) * $deliveryPercent;
        }
        $arRes['name'] = $arProfile[0]['NAME'];
        $arRes['img'] = \CFile::GetPath($arProfile[0]['LOGOTIP']);
        $arRes['period'] = "Уточняйте у вашего менеджера";
        $this->delivery[$arProfile[0]['ID']] = $arRes;
    }

    private function sendPostRequest()
    {
        $option = \Bitrix\Main\Config\Option::get('sale', 'location');
        $arError = [];
        $arLocTo = $this->getLocation(['CODE' => $option]);
        $locationTo = $this->checkCity($arLocTo);
        $locationFrom = $this->checkCity($this->location);

        if(!$locationTo) {
            $arError['to'] = [
                'errorDescription' => 'Нет возможности отправить груз из этого местоположения',
                'errorLoc' => implode(" ", array_splice($arLocTo, 0 , -2)),
            ];
        }
        if(!$locationFrom) {
            $arError['from'] = [
                'errorDescription' => 'Нет возможности отправить груз от этого местоположения',
                'errorLoc' => implode(" ", array_splice($this->location, 0, -2)),
            ];
        }


        if(empty($arError)) {
            $arPost = [
                'weight' => static::$otherInfo['weight'],
                'volume' => static::$otherInfo["volume"],
                "sending" => [
                    'type' => 'address',
                    'city' => $locationTo['id'],
                ],
                "delivery" => [
                    "type" => 'address',
                    'city' => $locationFrom['id'],
                    'address' => [
                        'street' => $locationFrom['name']
                    ]
                ]
            ];
            $this->getCalculate($arPost);
        } else {
            $arProfile = array_map(function ($ar) {return $ar;}, $this->getDeliveryProfile());
            $arProfile = array_values($arProfile);
            $arRes['name'] = $arProfile[0]['NAME'];
            $arRes['img'] = \CFile::GetPath($arProfile[0]['LOGOTIP']);
            $arRes['period'] = "Уточняйте у вашего менеджера";
            $this->delivery[$arProfile[0]['ID']] = $arRes;
            if(!empty($arError['from']) || !empty($arError['to'])) {
                $this->delivery[$arProfile[0]['ID']]['error_loc'] = $arError;
            }
        }
    }

}