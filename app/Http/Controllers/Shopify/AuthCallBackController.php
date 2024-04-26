<?php

namespace App\Http\Controllers\Shopify;

use Shopify\Utils;
use Shopify\Auth\OAuth;
use App\Lib\EnsureBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use App\Webhook\Register\ProductCreate;
use App\Services\Shopify\WebhookService;
use App\Webhook\Register\AppUninstalled;

class AuthCallBackController extends Controller
{
    public function __construct()
    {

    }

    public function index(Request $request)
    {
        Log::debug("In AuthCallback controller");

        $session = OAuth::callback(
            $request->cookie(),
            $request->query(),
            ['App\Lib\CookieHandler', 'saveShopifyCookie'],
        );

        $host = $request->query('host');
        $shop = Utils::sanitizeShopDomain($request->query('shop'));

        // Register all Webhooks in WebhookService
        // new WebhookService($shop, $session);

        $redirectUrl = Utils::getEmbeddedAppUrl($host);
        if (Config::get('shopify.billing.required')) {
            list($hasPayment, $confirmationUrl) = EnsureBilling::check($session, Config::get('shopify.billing'));

            if (!$hasPayment) {
                $redirectUrl = $confirmationUrl;
            }
        }

        return redirect($redirectUrl);
    }

}