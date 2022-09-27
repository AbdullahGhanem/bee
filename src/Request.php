<?php

namespace Ghanem\Bee;

use Illuminate\Support\Facades\Http;

class Request
{
    static public function request($endpoint, array $params = [])
    {
        $params['login'] = config('bee.username');
        $params['password'] = config('bee.password');
        $link = config('bee.url') . $endpoint;
        $headers = [
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $response = Http::withHeaders($headers)->post($link, $params);

        if ($response->ok()) {
            $data = $response->json();
            // if ($data['success'] == false)
            // return array_merge(['headers' => $headers], ['params' => $params, 'link' => $link], ['status_code' => $response->status()], $response->json());
            return collect($data);
        } else {
            return array_merge(['headers' => $headers], ['params' => $params, 'link' => $link], ['status_code' => $response->status()], $response->json());
        }
    }

    static public function getProviderList($categoryId = 2, $lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetProviderList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['service_version'] = 0;
        return Request::request('service', $params);
    }

    static public function getServiceList($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetServiceList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 'd';
        return Request::request('service', $params);
    }

    static public function getServiceInputParameterList($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetServiceInputParameterList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 'd';
        return Request::request('service', $params);
    }

    static public function getServiceOutputParameterList($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetServiceOutputParameterList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 'd';
        return Request::request('service', $params);
    }

    static public function getCategoryList($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetCategoryList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 1;
        return Request::request('service', $params);
    }

    static public function getCategoryServiceList($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetCategoryServiceList';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 1;
        return Request::request('service', $params);
    }


    static public function getTransaction($id, $type = 'id', $lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = $type == 'external_id' ? 'GetTransactionByExternalId' : 'GetTransactionDetails';
        $params['version'] = 2;
        $params['language'] = $lang;
        if ($type == 'external_id')
            $params['data']['external_id'] = $id;
        else
            $params['data']['transaction_id'] = $id;
        return Request::request('report', $params);
    }

    static public function getAccountInfo($lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'GetAccountInfo';
        $params['version'] = 2;
        $params['language'] = $lang;
        $params['data']['s'] = 'd';
        return Request::request('report', $params);
    }

    static public function transactionInquiry($data, $lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'TransactionInquiry';
        $params['version'] = 2;
        $params['language'] = $lang;

        $params['data']['service_version'] = $data['service_version'] ?? 2;
        $params['data']['account_number'] = $data['account_number'] ?? 2;
        $params['data']['service_id'] = $data['service_id'] ?? 14;
        $params['data']['input_parameter_list'] = $data['input_parameter_list'] ?? [];
        return Request::request('transaction', $params);
    }


    static public function transactionPayment($data, $lang = 'en')
    {
        $params = [];
        $params['terminal_id'] = '1';
        $params['action'] = 'TransactionPayment';
        $params['version'] = 2;
        $params['language'] = $lang;

        $params['data']['service_version'] = $data['service_version'] ?? 2;
        $params['data']['account_number'] = $data['account_number'] ?? 2;
        $params['data']['service_id'] = $data['service_id'] ?? 14;
        $params['data']['external_id'] = $data['external_id'] ?? "14";
        $params['data']['amount'] = $data['amount'] ?? 1.5;
        $params['data']['service_charge'] = $data['service_charge'] ?? 0;
        $params['data']['total_amount'] = $data['total_amount'] ?? 1.5;
        $params['data']['quantity'] = $data['quantity'] ?? 1;
        $params['data']['inquiry_transaction_id'] = $data['inquiry_transaction_id'] ?? 2;
        $params['data']['input_parameter_list'] = $data['input_parameter_list'] ?? [];
        return Request::request('transaction', $params);
    }
    static public function caluclateServiceChargeRevers($data)
    {
        $service_list = self::getServiceList()['data'] ?? [];

        $service = collect($service_list['service_list'])->where('id', $data['service_id'])->first();
        $service_charge_object = self::getServiceChargeObject($service['service_charge_list'], $data['amount']);

        $data['total_amount'] = $data['amount'];
        $data['amount'] = round($data['total_amount'] / (1 + $service_charge_object['charge'] / 100), 2);
        $data['service_charge'] = bcdiv($service_charge_object['percentage'] ? $data['amount'] * $service_charge_object['charge'] / 100 : $service_charge_object['charge'], 1, 2);
        $data['service_charge'] = $data['service_charge'] > $service_charge_object['slap'] ? $data['service_charge'] : $service_charge_object['slap'];
        $data['total_amount'] = $data['amount'] + $data['service_charge'];

        return $data;
    }

    static public function caluclateServiceCharge($data)
    {
        $service_list = self::getServiceList()['data'] ?? [];

        $service = collect($service_list['service_list'])->where('id', $data['service_id'])->first();
        $service_charge_object = self::getServiceChargeObject($service['service_charge_list'], $data['amount']);


        $data['service_charge'] = $service_charge_object['percentage'] ? $data['amount'] * $service_charge_object['charge'] / 100 : $service_charge_object['charge'];
        $data['service_charge'] = $data['service_charge'] > $service_charge_object['slap'] ? $data['service_charge'] : $service_charge_object['slap'];
        $data['total_amount'] = $data['amount'] + $data['service_charge'];
        return $data;
    }

    static public function getBillsAmount($data)
    {
        $transaction_inquiry = self::transactionInquiry($data);
        $data['inquiry_transaction_id'] = $transaction_inquiry['data']['transaction_id'];
        $data['amount'] = $transaction_inquiry['data']['amount'];
        return $data;
    }

    static public function getServiceChargeObject($array, $amount)
    {
        $collection = collect($array);
        return $collection->where('from', '<=', $amount)->where('to', '>=', $amount)->first();
    }
}
