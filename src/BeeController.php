<?php

namespace Ghanem\Bee;

use Ghanem\Bee\Request;

class BeeController
{
    public function getCategoryList($lang = 'en')
    {
        return Request::getCategoryList($lang);
    }

    public function getCategoryServiceList($lang = 'en')
    {
        return Request::getCategoryServiceList($lang);
    }

    public function getProviderList($categoryId = 2, $lang = 'en')
    {
        return Request::getProviderList($categoryId, $lang);
    }

    public function getServiceList($lang = 'en')
    {
        return Request::getServiceList($lang);
    }

    public function getServiceInputParameterList($lang = 'en')
    {
        return Request::getServiceInputParameterList($lang);
    }

    public function getServiceOutputParameterList($lang = 'en')
    {
        return Request::getServiceOutputParameterList($lang);
    }

    public function getTransaction($id, $type = 'id', $lang = 'en')
    {
        return Request::getTransaction($id, $type, $lang);
    }

    public function getAccountInfo($lang = 'en')
    {
        return Request::getAccountInfo($lang);
    }


    public function transactionInquiry($data, $lang = 'en')
    {
        $service_version = Request::getProviderList(2, $lang)['data']['service_version'];
        $data['service_version'] = $service_version;
        return Request::transactionInquiry($data, $lang);
    }

    public function transactionPayment($data, $lang = 'en')
    {
        $service_version = Request::getProviderList(2, $lang)['data']['service_version'];
        $data['service_version'] = $service_version;
        return Request::transactionPayment($data, $lang);
    }

    public function caluclateServiceCharge($data)
    {
        return Request::caluclateServiceCharge($data);
    }

    public function caluclateServiceChargeRevers($data)
    {
        return Request::caluclateServiceChargeRevers($data);
    }

    public function getBillsAmount($data)
    {
        return Request::getBillsAmount($data);
    }
}
