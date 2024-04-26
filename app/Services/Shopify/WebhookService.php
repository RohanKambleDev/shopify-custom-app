<?php
namespace App\Services\Shopify;

use Shopify\Auth\OAuth;
use Shopify\Auth\Session;
use Illuminate\Http\Request;
use Shopify\Webhooks\Topics;
use Shopify\Webhooks\Registry;
use Illuminate\Support\Facades\Log;
use App\Services\Shopify\BaseService;
use Shopify\Rest\Admin2024_04\Webhook;
use App\Webhook\Register\ProductCreate;
use App\Webhook\Register\AppUninstalled;

class WebhookService extends BaseService
{
    public function register($topic)
    {
        // if (Topics::$topic) {
        $response = Registry::register('/api/webhooks', $topic, $this->shop, $this->accessToken);
        if ($response->isSuccess()) {
            Log::debug("Registered " . $topic . " webhook for shop $this->shop");
        } else {
            Log::error(
                "Failed to register " . $topic . " webhook for shop $this->shop with response body: " .
                print_r($response->getBody(), true)
            );
        }
        // }

        // $webhook = new Webhook($this->session);
        // Log::debug(print_r($webhook, true));
        // $webhook->topic = "orders/create";
        // $webhook->address = "/api/webhooks";
        // $webhook->format = "json";
        // $webhook->fields = [
        //     "id",
        //     "note"
        // ];
        // $webhook->save(
        //     true, // Update Object
        // );

        // switch ($topic) {
        //     case Topics::APP_UNINSTALLED:
        //         new AppUninstalled($this->shop, $this->session); // register Topics::APP_UNINSTALLED webhook
        //         $this->registerTopic($this->shop, $this->session, $topic);
        //         break;

        //     case Topics::PRODUCTS_CREATE:
        //         new ProductCreate($this->shop, $this->session); // register Topics::PRODUCTS_CREATE webhook
        //         break;

        //     case Topics::ORDERS_CREATE:
        //         new ProductCreate($this->shop, $this->session); // register Topics::PRODUCTS_CREATE webhook
        //         break;
        // }
    }

    public function registerTopic($shop, $session, $topic)
    {
        $response = Registry::register('/api/webhooks', $topic, $shop, $session->getAccessToken());
        if ($response->isSuccess()) {
            Log::debug("Registered " . $topic . " webhook for shop $shop");
        } else {
            Log::error(
                "Failed to register " . $topic . " webhook for shop $shop with response body: " .
                print_r($response->getBody(), true)
            );
        }
    }

    public function getListOfWebhooks()
    {
        $webhooksArr = Webhook::all($this->session);
        // Log::debug("========================");
        // Log::error(var_export($webhooksArr));
        // Log::debug("========================");
        foreach ($webhooksArr as $webhook) {
            Log::debug("========================");
            Log::debug("webhook Topic : " . $webhook->topic);
            // Log::error(print_r($webhook, true));
            Log::debug("webhooks Address : " . $webhook->address);
            Log::debug("webhooks Id : " . $webhook->id);
            Log::debug("========================");
        }
    }

    public function getWebhooksCount()
    {
        $webhooksCount = Webhook::count($this->session);
        Log::debug("webhooks-count : " . $webhooksCount['count']);
    }

    public function updateWebhook($webhookId, $change = [])
    {
        $webhook = new Webhook($this->session);
        $webhook->id = $webhookId;

        if (!empty ($change) && isset ($change['address'])) {
            $webhook->address = $change['address'];
        }

        $webhook->save(
            true, // Update Object
        );
    }

    public function deleteWebhook($webhookId)
    {
        $webhookDeleted = Webhook::delete(
            $this->session,
            $webhookId
        );
        Log::debug("webhook-Deleted : ");
        Log::debug(print_r($webhookDeleted, true));

    }
}