<?php

namespace App\Services\Veeva;

use App\Services\Veeva\VaultBase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Routing\Matcher\Dumper\MatcherDumperInterface;

class VaultOrderService extends VaultBase
{
    public function __construct()
    {
        //
    }
    public function submitOrdersToVault($shopifyData = [])
    {
        // get sesssion ID
        $base_url = $this->apiUrl;
        $mytime = Carbon::now();
        $now = $mytime->toDateTimeString();
        $response = [];

        $note = 'test Rohan Note....';
        // if (isset ($shopifyData['note'])) {
        //     $note = $shopifyData['note'];
        // }
        if (isset($shopifyData['customer'])) {
            $note = json_encode($shopifyData['customer']);
        }
        // $shopifyData['line_items'][0]; //first item
        // $shopifyData['customer'];
        // $shopifyData['customer']['email'];
        // $shopifyData['customer']['default_address'];

        // New Call Engagement ======================================================================================================
        $data = $this->makeSessionId();
        $headers = $this->makeHeadersForApiCall($data);
        // $url = $base_url . "/api/v23.3/vobjects/engagement__c";
        $url = $base_url . $this->apiVersion . $this->vaultObject . $this->engagement;
        // $payload = [
        //     'account__c' => 'V5300000000H001', // https://veevasbx-lynx-qa.veevavault.com/ui/#t/0TB00000000N003/V53/V5300000000H001?expanded=details__c&s=0
        //     'engagement_name__c' => 'Engagement created from Shopify Rohan - ' . $now,
        //     'object_type__v' => 'OOT000000018001', // training object ID #https://veevasbx-lynx-qa.veevavault.com/ui/#admin/content_setup/object_schema/types=&t=engagement__c&d=training__c
        //     'project_status__c' => 'na__c', // N/A
        //     'contract_services_product_budget__c' => 23000
        // ];
        $payload = [
            'account__c' => 'V5D00000000D001',
            'engagement_name__c' => 'Engagement created from Shopify Rohan - ' . $now,
            'object_type__v' => 'OOT00000001F001',
            'project_status__c' => 'active__c',
            'contract_services_product_budget__c' => 23000,
            'business_golive_date__c' => '2024-05-01',
            'close_date__c' => '2024-03-01',
            'end_date__c' => '2024-06-01',
            'region__c' => 'north_america1__c',
            'revenue_department__c' => 'services_training_services__c',
            'services_business_line__c' => 'rd__c',
            'start_date__c' => '2024-03-22',
            'sub_revenue_department__c' => 'na__c',
            'engagement_weekly_status__c' => 'green__c',
            'owner__c' => '17522999',
            'probability__c' => 100,
            'suite__c' => 'vault_platform_development__c',
        ];

        $query = json_encode([$payload]);
        $result = $this->call_curl($url, $query, $headers, $base_url, 1);
        Log::debug(print_r($url, true));
        Log::debug(print_r($headers, true));
        Log::debug(print_r($payload, true));
        Log::debug(print_r($result, true));
        $response['engagement'] = $result;

        // New Call Budget Line ======================================================================================================
        $data = $this->makeSessionId();
        $headers = $this->makeHeadersForApiCall($data);
        $engagement_id = $result['data'][0]['data']['id'];
        // $url = $base_url . "/api/v23.3/vobjects/budget_line__c";
        $url = $base_url . $this->apiVersion . $this->vaultObject . $this->budgetLine;
        $payload = [
            'engagement__c' => $engagement_id,
            'budget_line_name__c' => 'New Budget Line from Shopify Rohan - ' . $now,
        ];
        $query = json_encode([$payload]);
        $result = $this->call_curl($url, $query, $headers, $base_url, 1);
        Log::debug(print_r($result, true));
        $response['budget_line'] = $result;


        // NEW call Services Product ======================================================================================================
        $data = $this->makeSessionId();
        $headers = $this->makeHeadersForApiCall($data);
        $budget_line_id = $result['data'][0]['data']['id'];
        // $url = $base_url . "/api/v23.3/vobjects/services_product__c";
        $url = $base_url . $this->apiVersion . $this->vaultObject . $this->serviceProduct;
        $payload = [
            'engagement__c' => $engagement_id,
            'quantity__c' => count($shopifyData['line_items']),
            'budget_line__c' => $budget_line_id,
            'comments__c' => 'New Service Product from Shopify Rohan - ' . $now . ' with Shopify note = ' . $note,
            'planned_delivery_date__c' => '2024-09-05',
            'object_type__v' => 'OOT000000016017', // training #https://veevasbx-lynx-qa.veevavault.com/ui/#admin/content_setup/object_schema/types=&t=services_product__c&d=training__c
            'local_currency__sys' => 'V0V000000000101' // usd data value
        ];
        $query = json_encode([$payload]);
        $result = $this->call_curl($url, $query, $headers, $base_url, 1);
        $response['service_product'] = $result;

        return $response;
    }

}