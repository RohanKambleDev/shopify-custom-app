<?php

namespace App\Services\Veeva;

use Illuminate\Support\Facades\Log;

class VaultConfig
{
    protected $username = 'rohan.kamble@veevasbx.com';
    protected $password = 'Veevan@1234';
    protected $apiUrl = 'https://veevasbx-lynx-qa1.veevavault.com';
    protected $apiVersion = '/api/v23.3';
    protected $auth = '/auth';
    protected $vaultObject = '/vobjects';
    protected $training = '/training__c';
    protected $engagement = '/engagement__c';
    protected $product = '/product__c';
    protected $budgetLine = '/budget_line__c';
    protected $localCurrencyField = 'local_currency__sys';
    protected $localCurrencyUSDId = 'V0V000000000101';
    protected $serviceProduct = '/services_product__c';
    protected $query = '/query';

    // https://veeva-training-services.myshopify.com/admin/oauth/authorize?client_id=ab7a9dfd9e16b95257c593a031d7ce93&scope=read_product_feeds,write_product_feeds,write_products,read_products,write_product_listings,read_product_listings&redirect_uri=https://wetheengineers.com/shopify&state=1db49e4a71dd4f345fd3684929a99872&grant_options[]=per-user
}