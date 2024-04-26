<?php
namespace App\Services\Shopify;

use Illuminate\Http\Request;

class BaseService
{
    public $session = '';
    public $shop = '';
    public $accessToken = '';

    public $request = '';

    // public function __construct(Session $session)
    public function __construct(Request $request)
    {
        $this->session = $request->get('shopifySession');

        $this->request = $request;
        $this->shop = $this->session->getShop();
        $this->accessToken = $this->session->getAccessToken();
    }
}