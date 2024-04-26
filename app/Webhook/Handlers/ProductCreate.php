<?php

namespace App\Webhook\Handlers;

use Carbon\Carbon;
use Shopify\Webhooks\Handler;
use Illuminate\Support\Facades\Log;
use App\Services\Veeva\VaultOrderService;

class ProductCreate implements Handler
{
    private $now = 0;
    public function __construct()
    {
        $mytime = Carbon::now();
        $this->now = $mytime->toDateTimeString();
    }
    public function handle(string $topic, string $shop, array $requestBody): void
    {
        // Handle your webhook here!
        Log::debug("I am inside " . $topic . " handler at " . $this->now . " for " . $shop);

        // $vaultOrderService = new VaultOrderService();
        // $response = $vaultOrderService->submitOrdersToVault();
        // Log::debug(print_r($response, true));
    }

}