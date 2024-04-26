<?php

namespace App\Services\Veeva;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class VaultProductService extends VaultBase
{
    // public function __construct(Client $client)
    // {
    //     parent::__construct($client);
    // }

    public function getProducts()
    {

        $productcArr = [
            'id',
            'name__v',
            'application__c',
            'bundle__c',
            'product_type__c',
            'product_record_type__c',
            'product_description__c',
            'product_family__c',
            'product_code__c',
            'status__v',
            'created_by__v',
            'created_date__v'
        ];

        $productBundleComponents1crArr = [
            'id',
            'name__v',
            'product_bundle__c',
            'product_component__c',
            'status__v',
            'created_date__v',
            'created_by__v'
        ];

        $priceBookEntriescrArr = [
            'id',
            'list_price__c',
            'name__v',
            'price_book__c',
            'product__c',
            'product_code__c'
        ];

        // get sesssion ID
        // $data = $this->makeSessionId();
        $data = $this->makeSessionIdForGuzzle();
        $base_url = $this->apiUrl;
        $url = $base_url . $this->apiVersion . $this->query;
        // $headers = $this->makeHeadersForApiCall($data);
        $headers = $this->makeHeadersForApiCallAuth($data);


        $query = "q=SELECT id, name__v, bundle__c, product_type__c, product_record_type__c, product_description__c, product_family__c, product_code__c, status__v,created_by__v,created_date__v, (SELECT id,name__v,product_bundle__c,product_component__c,status__v,created_date__v,created_by__v FROM product_bundle_components1__cr) , (SELECT id, local_currency__sys,list_price__c,name__v,price_book__c,product__c,product_code__c from price_book_entries__cr),(SELECT id,name__v, product__c,prerequisite__c,sort__c from training_prerequisites__cr),(select id, course_name__c, course_start_date__c, course_end_date__c, course_start_time__c, course_end_time__c, course_status__c, timezone__c, product__c from trainings__cr) FROM product__c where product_record_type__c='training__c'";

        // $result = $this->call_curl($url, $query, $headers, $base_url, 0);
        $result = $this->getGuzzleRequest($url, $query, $headers);
        return $result['data'];
    }
}