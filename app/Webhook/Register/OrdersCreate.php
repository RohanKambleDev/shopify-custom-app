<?php

namespace App\Webhook\Register;

use Shopify\Webhooks\Topics;
use Shopify\Webhooks\Registry;
use Illuminate\Support\Facades\Log;

class OrdersCreate
{
    public function __construct($shop, $session)
    {
        $response = Registry::register('/api/webhooks', Topics::ORDERS_CREATE, $shop, $session->getAccessToken());
        if ($response->isSuccess()) {
            Log::debug("Registered " . Topics::ORDERS_CREATE . " webhook for shop $shop");
        } else {
            Log::error(
                "Failed to register " . Topics::ORDERS_CREATE . " webhook for shop $shop with response body: " .
                print_r($response->getBody(), true)
            );
        }
    }
}