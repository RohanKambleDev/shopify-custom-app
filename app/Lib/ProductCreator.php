<?php

declare(strict_types=1);

namespace App\Lib;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Shopify\ProductService;

class ProductCreator
{
    private const CREATE_PRODUCTS_MUTATION = <<<'QUERY'
    mutation populateProduct($input: ProductInput!) {
        productCreate(input: $input) {
            product {
                id
            }
        }
    }
    QUERY;

    // public static function call(Session $session, int $count)
    // {
    //     $client = new Graphql($session->getShop(), $session->getAccessToken());

    //     for ($i = 0; $i < $count; $i++) {
    //         $response = $client->query(
    //             [
    //                 "query" => self::CREATE_PRODUCTS_MUTATION,
    //                 "variables" => [
    //                     "input" => [
    //                         "title" => self::randomTitle(),
    //                         "variants" => [["price" => self::randomPrice()]],
    //                     ]
    //                 ]
    //             ],
    //         );

    //         if ($response->getStatusCode() !== 200) {
    //             throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
    //         }
    //     }
    // }

    // public static function call(Session $session, int $count)
    public static function call(Request $request, int $count)
    {
        $productService = new ProductService($request);
        // $productService->getAllShopifyProducts();
        list($productsArr, $collectArr, $productUpdatedIdArr, $productPreReqUpdatedIdArr, $productTrainingCrUpdatedIdArr) = $productService->createProduct();

        if (isset($productsArr['updated'])) {
            Log::debug('0 Product created');
        } else {
            Log::debug(count($productsArr) . ' Products created');
        }

        if (count($productsArr)) {
            if (count($productUpdatedIdArr)) {
                Log::debug(count($productUpdatedIdArr) . ' Products with ID ' . implode(',', $productUpdatedIdArr) . ' Updated with Bundles');
            }
            if (count($collectArr)) {
                Log::debug(count($collectArr) . ' Products Added to the collection');
            }
            if (count($productPreReqUpdatedIdArr)) {
                Log::debug(count($productPreReqUpdatedIdArr) . ' Products with ID ' . implode(',', $productUpdatedIdArr) . ' Updated with Training PreRequisites');
            }
            if (count($productTrainingCrUpdatedIdArr)) {
                Log::debug(count($productTrainingCrUpdatedIdArr) . ' Products with ID ' . implode(',', $productUpdatedIdArr) . ' Updated with Training Course');
            }
        }

    }

    private static function randomTitle()
    {
        $adjective = self::ADJECTIVES[mt_rand(0, count(self::ADJECTIVES) - 1)];
        $noun = self::NOUNS[mt_rand(0, count(self::NOUNS) - 1)];

        return "$adjective $noun";
    }

    private static function randomPrice()
    {

        return (100.0 + mt_rand(0, 1000)) / 100;
    }

    private const ADJECTIVES = [
        "autumn",
        "hidden",
        "bitter",
        "misty",
        "silent",
        "empty",
        "dry",
        "dark",
        "summer",
        "icy",
        "delicate",
        "quiet",
        "white",
        "cool",
        "spring",
        "winter",
        "patient",
        "twilight",
        "dawn",
        "crimson",
        "wispy",
        "weathered",
        "blue",
        "billowing",
        "broken",
        "cold",
        "damp",
        "falling",
        "frosty",
        "green",
        "long",
    ];

    private const NOUNS = [
        "waterfall",
        "river",
        "breeze",
        "moon",
        "rain",
        "wind",
        "sea",
        "morning",
        "snow",
        "lake",
        "sunset",
        "pine",
        "shadow",
        "leaf",
        "dawn",
        "glitter",
        "forest",
        "hill",
        "cloud",
        "meadow",
        "sun",
        "glade",
        "bird",
        "brook",
        "butterfly",
        "bush",
        "dew",
        "dust",
        "field",
        "fire",
        "flower",
    ];
}
