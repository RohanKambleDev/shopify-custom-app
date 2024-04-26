<?php

namespace App\Services\Shopify;

use Shopify\Clients\Graphql;
use Shopify\Clients\HttpResponse;
use Illuminate\Support\Facades\Log;
use App\Services\Shopify\BaseService;
use Shopify\Rest\Admin2024_04\Collect;
use Shopify\Rest\Admin2024_04\Product;
use App\Services\Shopify\WebhookService;
use App\Services\Veeva\VaultProductService;
use Shopify\Rest\Admin2024_04\CustomCollection;
use App\Exceptions\ShopifyProductCreatorException;
use GuzzleHttp\Client;

class ProductService extends BaseService
{
    private $collectionTitle = "Veeva Training Tracks";
    private $collectionHandle = "veeva-training-tracks";

    public function createCustomCollection()
    {
        $customCollection = new CustomCollection($this->session);
        $customCollection->title = $this->collectionTitle;
        $customCollection->save(
            true, // Update Object
        );
        return $customCollection;
    }

    /**
     * checkIfCustomCollectionExists
     *
     */
    public function checkIfCustomCollectionExists()
    {
        $customCollectionArr = [];
        $customCollections = CustomCollection::all(
            $this->session, // Session
        );

        foreach ($customCollections as $customCollection) {
            if ($customCollection->handle == $this->collectionHandle) {
                $customCollectionArr = $customCollection;
                break;
            }
        }

        return $customCollectionArr;
    }

    /**
     * getCustomCollectionId
     *
     * @return array
     */
    public function getCustomCollectionId()
    {
        $customCollectionId = 0;
        $customCollectionArr = $this->checkIfCustomCollectionExists();
        if ($customCollectionArr instanceof CustomCollection && isset($customCollectionArr->id)) {
            $customCollectionId = $customCollectionArr->id;
        } else {
            $customCollectionArr = $this->createCustomCollection();
            if ($customCollectionArr instanceof CustomCollection && isset($customCollectionArr->id)) {
                $customCollectionId = $customCollectionArr->id;
            }
        }

        return [$customCollectionId, $customCollectionArr];
    }

    /**
     * addProductToCustomCollection
     *
     * @param  mixed $productId
     * @param  mixed $collectionId
     * @return collect
     */
    public function addProductToCustomCollection($productId, $collectionId)
    {
        $collect = new Collect($this->session);
        if (isset($productId) && isset($collectionId)) {
            $collect->product_id = $productId;
            $collect->collection_id = $collectionId;
            $collect->save(
                true, // Update Object
            );
        }

        return $collect;
    }

    /**
     * checkIfProductExists
     *
     * @param  mixed $sku
     * @return array - $productId, $variantIdArr[0]
     */
    public function checkIfProductExists($sku)
    {
        $products = [];
        $productGId = '';
        $variantGId = '';
        $productMetafields = [];

        if (!isset($sku)) {
            return [$productGId, $variantGId, $productMetafields];
        }

        // if ($this->request->session()->missing('products')) {
        $products = $this->getAllShopifyProducts();
        // } else {
        //     $products = $this->request->session()->get('products');
        // }

        // Log::debug(print_r($products, true));

        if (count($products)) {
            $skuArr = [];
            foreach ($products as $product) {
                // get Variant
                if (!empty($product->node->variants) && is_array($product->node->variants->edges)) {
                    if (!empty($product->node->variants->edges)) {
                        foreach ($product->node->variants->edges as $variants) {
                            $skuArr[] = $variants->node->sku;
                            $variantGId = $variants->node->id;
                        }
                    }
                }
                if (in_array($sku, $skuArr)) {
                    $productGId = $product->node->id;
                    if (!empty($product->node->metafield) && isset($product->node->metafield->key) && isset($product->node->metafield->id)) {
                        $productMetafields[$product->node->metafield->key] = $product->node->metafield->id;
                    }
                    break;
                }
            }
        }

        return [$productGId, $variantGId, $productMetafields];
    }

    public function getAllShopifyProducts()
    {
        // $products = Product::all(
        //     $this->session, // Session
        // );

        // product query
        $productQuery = <<<QUERY
        {
            products(first: 50, reverse: true) {
                edges {
                    node {
                        id
                        title
                        handle
                        description
                        productType
                        tracksInventory
                        variants(first: 50) {
                            edges {
                                node {
                                    id
                                    displayName
                                    sku
                                }
                            }
                        }
                        metafield(key: "product_bundle_components", namespace: "custom") {
                            id
                            key
                        }
                    }
                }
            }
        }
        QUERY;

        $client = new Graphql($this->shop, $this->accessToken);
        $response = $client->query(["query" => $productQuery]);

        if ($response->getStatusCode() !== 200) {
            throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
        } else {
            $res = json_decode($response->getBody()->__toString());
            if (isset($res->data->products->edges)) {
                $products = $res->data->products->edges;
                // Log::debug(print_r($products, true));
            }
        }

        if ($this->request->session()->missing('products')) {
            $this->request->session()->put('products', $products);
        }

        return $products;
    }

    /**
     * createProductByGraphQL
     *
     * @return array of $productIdArr & $productUpdatedIdArr
     */
    public function createProductByGraphQL()
    {
        $productIdArr = $productUpdatedIdArr = $productPreReqUpdatedIdArr = $productTrainingCrUpdatedIdArr = [];

        $createProductsArr = self::PullDataFromVault();
        if (!count($createProductsArr)) {
            return [$productIdArr, $productUpdatedIdArr];
        }

        Log::debug("Start createProductByGraphQL");
        Log::debug("got " . count($createProductsArr) . " products");

        $productIdArr = $this->addProduct($createProductsArr);
        $productUpdatedIdArr = $this->addProductBundles($createProductsArr);
        $productPreReqUpdatedIdArr = $this->setProductMetaFields($createProductsArr, 'prepareProductTrainingPreRequisites', "training_prerequisites");
        $productTrainingCrUpdatedIdArr = $this->setProductMetaFields($createProductsArr, 'prepareProductTrainingCourses', "training_course");

        return [$productIdArr, $productUpdatedIdArr, $productPreReqUpdatedIdArr, $productTrainingCrUpdatedIdArr];
    }

    public function addProduct($createProductsArr)
    {
        $productIdArr = [];
        $client = new Graphql($this->shop, $this->accessToken);
        foreach ($createProductsArr as $vaultProduct) {

            // product__c
            if (!isset($vaultProduct['id']) || !isset($vaultProduct['name__v'])) {
                continue;
            }

            $sku = $vaultProduct['id'];
            $title = $vaultProduct['name__v'];
            $description = $vaultProduct['product_description__c'];

            // Log::debug('========vaultProduct======');
            // Log::debug(print_r($vaultProduct, true));

            $price = 0;
            if (isset($vaultProduct['price_book_entries__cr']['data']) && count($vaultProduct['price_book_entries__cr']['data'])) {
                foreach ($vaultProduct['price_book_entries__cr']['data'] as $price_book_entriecr) {
                    if (isset($price_book_entriecr['local_currency__sys']) && $price_book_entriecr['local_currency__sys'] == 'V0V000000000101') {
                        $price = $price_book_entriecr['list_price__c'];
                        break;
                    }
                }
            }

            if (empty($price)) {
                Log::debug('========price not found on product======');
                continue;
            }

            // check if product already exists
            list($productGId, $variantGId, $productMetafields) = $this->checkIfProductExists($sku);
            if ($productGId) {
                // if product is already added make sure product fields are all synced and skip the loop
                Log::debug('$productId: ' . $productGId . ' already added, Lets try to sync it');
                $vaultProduct['track_price'] = $price;
                $productIdArr['updated'][] = $this->productSync($productGId, $variantGId, $vaultProduct);
                Log::debug('productSync done : ');
                Log::debug(print_r($productIdArr, true));
                continue;
            }

            Log::debug("\n");
            Log::debug("=========================================");
            Log::debug("creating ProductByGraphQL.....");

            $status = $this->getProductStatus($vaultProduct);

            // since it is a course(digital product) - requiresShipping will be false
            $inputProductCreate = '{
                title: "' . $title . '", 
                vendor: "veeva", 
                productType: "veeva-training", 
                status: ' . $status . ',
                descriptionHtml: "' . $description . '"                 
            }';

            // create product mutation
            $createProductQuery = <<<QUERY
            mutation {
                productCreate(input: $inputProductCreate) {
                    product {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            QUERY;

            $response = $client->query(["query" => $createProductQuery]);

            Log::debug("ProductByGraphQL query fired.....");
            Log::debug('=========createProductQuery=======');
            Log::debug(print_r($createProductQuery, true));
            
            $responseProductId = null;
            $userError = [];

            if ($response->getStatusCode() !== 200) {
                Log::debug("Product with SKU - $sku Not added");
                throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);     
            } else {
                $res = json_decode($response->getBody()->__toString());
                $responseProductId = $res->data->productCreate->product->id;
                $userError = $res->data->productCreate->userErrors;
            }

            if (!count($userError) && isset($responseProductId)) {
            
                $inputProductVarianCreate = '{
                    productId: "' . $responseProductId . '", 
                    price: "' . $price. '", 
                    sku: "' . $sku . '",
                    options: "' . $title . '"
                }';

                // create product mutation
                $createProductVariantQuery = <<<QUERY
                mutation {
                    productVariantCreate(input: $inputProductVarianCreate) {
                        product {
                            id
                        }
                        productVariant {
                            sku
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
                QUERY;

                $productVariantResponse = $client->query(["query" => $createProductVariantQuery]);

            }
            
            Log::debug("ProductByGraphQL query fired.....");

            if ($productVariantResponse->getStatusCode() !== 200) {
                Log::debug("Product with SKU - $sku Not added");
                throw new ShopifyProductCreatorException($productVariantResponse->getBody()->__toString(), $response);
            } else {
                //the parent product id is added to the productIdArr after the successful creation of product variant
                $variantRes = json_decode($productVariantResponse->getBody()->__toString());
                $variantResponseProductId = $variantRes->data->productVariantCreate->product->id;
                if (isset($variantResponseProductId)) {
                    $globalId = $variantResponseProductId;
                    $exploded = explode('/', $globalId); // gid://shopify/Product/7049493053517
                    if (count($exploded) && isset($exploded[4]) && is_numeric($exploded[4])) {
                        $productIdArr[] = $exploded[4];
                        Log::debug("Product with SKU - $sku added - $globalId");
                    }
                }
            }
        }

        return $productIdArr;
    }
    public function prepareProductTrainingPreRequisites($vaultProduct)
    {
        // Initializing the vars
        $mainProductId = $metaFieldId = '';
        $productPreReqArray = [];

        //Checking the case of null 
        if (isset($vaultProduct["training_prerequisites__cr"])) {

            //Checking the case of null 
            if (!isset($vaultProduct["training_prerequisites__cr"]['data']) || !count($vaultProduct["training_prerequisites__cr"]['data'])) {
                return [$mainProductId, $productPreReqArray, $metaFieldId];
            }

            foreach ($vaultProduct["training_prerequisites__cr"]['data'] as $productReq) {
                // Training PreRequisite Product Sku
                $productPreReq = $productReq['prerequisite__c'];
                // Training PreRequisite Product Sort
                $productPreReqSort = $productReq['sort__c'];
                // Actual Product Sku
                $parentSku = $productReq['product__c'];
                // if there is no prereq present then no need to run the below code
                if (isset($productPreReq)) {
                    // Query to get the product id and metafield id from the product variant
                     $productQuery = <<<QUERY
                        query {
                            productVariants(first: 1, query: "sku:$parentSku") {
                                edges {
                                    node {
                                        id
                                        product{
                                            id    
                                            metafield(key: "training_prerequisites", namespace:"custom") {
                                            id
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    QUERY;
                
                    $client = new Graphql($this->shop, $this->accessToken);
                    $response = $client->query(["query" => $productQuery]);
                
                    if ($response->getStatusCode() !== 200) {
                        Log::debug("Product..... with SKU $parentSku not found in Shopify");
                        throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
                    } else {
                        Log::debug("Product..... with SKU $parentSku is found in Shopify");
                        $res = json_decode($response->getBody()->__toString());
                        if (isset($res->data->productVariants->edges[0]->node->product->id)) {
                            $mainProductId = $res->data->productVariants->edges[0]->node->product->id;
                            if (isset($res->data->productVariants->edges[0]->node->product->metafield->id)) {
                                $metaFieldId = $res->data->productVariants->edges[0]->node->product->metafield->id;
                            }
                            $metaObjectId = $this->createMetaObjectForTrainingPreReq($productPreReq, $productPreReqSort);
                            if ($metaObjectId) {
                                $productPreReqArray[] = $metaObjectId;
                            }
                        }
                    }
                }
            }
        }
        return [$mainProductId, $productPreReqArray, $metaFieldId];
    }

    public function prepareProductTrainingCourses($vaultProduct)
    {
        // Initializing the vars
        $mainProductId = $metaFieldId = '';
        $productTrainingCrArray = [];

        //Checking the case of null 
        if (isset($vaultProduct["trainings__cr"])) {

            //Checking the case of null 
            if (!isset($vaultProduct["trainings__cr"]['data']) || !count($vaultProduct["trainings__cr"]['data'])) {
                return [$mainProductId, $productTrainingCrArray, $metaFieldId];
            }

            foreach ($vaultProduct["trainings__cr"]['data'] as $obj) {
                // Data from the Training Course
                $dataArray = [
                    "id" => $obj["id"],
                    "course_name__c" => $obj["course_name__c"],
                    "course_start_date__c" => $obj["course_start_date__c"],
                    "course_end_date__c" => $obj["course_end_date__c"],
                    "course_start_time__c" => isset($obj["course_start_time__c"]) ? $obj["course_start_time__c"][0] : null,
                    "course_end_time__c" =>isset($obj["course_end_time__c"]) ? $obj["course_end_time__c"][0] : null,
                    "course_status__c" => isset($obj["course_status__c"]) ? $obj["course_status__c"][0] : null,
                    "timezone__c" => isset($obj["timezone__c"]) ? $obj["timezone__c"][0] : null
                ];
             
                // Actual Product Sku
                $parentSku = $obj['product__c'];
                // if there is no prereq present then no need to run the below code
                if (isset($obj["id"])) {
                    // Query to get the product id and metafield id from the product variant
                     $productQuery = <<<QUERY
                        query {
                            productVariants(first: 1, query: "sku:$parentSku") {
                                edges {
                                    node {
                                        id
                                        product{
                                            id    
                                            metafield(key: "training_course", namespace:"custom") {
                                            id
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    QUERY;
                
                    $client = new Graphql($this->shop, $this->accessToken);
                    $response = $client->query(["query" => $productQuery]);
                
                    if ($response->getStatusCode() !== 200) {
                        Log::debug("Product..... with SKU $parentSku not found in Shopify");
                        throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
                    } else {
                        Log::debug("Product..... with SKU $parentSku is found in Shopify");
                        $res = json_decode($response->getBody()->__toString());
                        if (isset($res->data->productVariants->edges[0]->node->product->id)) {
                            $mainProductId = $res->data->productVariants->edges[0]->node->product->id;
                            if (isset($res->data->productVariants->edges[0]->node->product->metafield->id)) {
                                $metaFieldId = $res->data->productVariants->edges[0]->node->product->metafield->id;
                            }
                            // return the metaObject id 
                            $metaObjectId = $this->createMetaObjectForTrainingsCourse($dataArray);
                            if ($metaObjectId) {
                                $productTrainingCrArray[] = $metaObjectId;
                            }
                        }
                    }
                }
            }
        }
        return [$mainProductId, $productTrainingCrArray, $metaFieldId];
    }


    public function createMetaObjectForTrainingsCourse($dataArray) 
    {
        $handleInput = '{
            type: "trainings_course",
            handle: "' .$dataArray["id"]. '"
        }';  
        $metaObjectFieldsInput = '{
            fields:[
                {
                    key: "course_id",
                    value: "' .$dataArray["id"]. '"
                }
                {
                    key: "course_name",
                    value: "' .$dataArray["course_name__c"]. '"
                }
                {
                    key: "course_start_date",
                    value: "' .$dataArray["course_start_date__c"]. '"
                }
                {
                    key: "course_end_date",
                    value: "' .$dataArray["course_end_date__c"]. '"
                }
                {
                    key: "course_start_time",
                    value: "' .$dataArray["course_start_time__c"]. '"
                }
                {
                    key: "course_end_time",
                    value: "' .$dataArray["course_end_time__c"]. '"
                }
                {
                    key: "course_status",
                    value: "' .$dataArray["course_status__c"]. '"
                }
                {
                    key: "timezone",
                    value: "' .$dataArray["timezone__c"]. '"
                }
            ]
        }'; 
        //create new metaObject if handler is not found else will update the existing
        $metaObjectMutation  = <<<QUERY
            mutation {
                metaobjectUpsert(handle: $handleInput, metaobject: $metaObjectFieldsInput) {
                    metaobject {
                        handle
                        course_id: field(key: "course_id") {
                          value
                        }
                        id
                        fields{
                          key
                        }
                    }
                    userErrors {
                        field
                        message
                        code
                    }
                }
            }
            QUERY;
        $client = new Graphql($this->shop, $this->accessToken);
        $response = $client->query(["query" => $metaObjectMutation]);

        $metaObjectId = null;
        if ($response->getStatusCode() !== 200) {
            throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
        } else {
            $res = json_decode($response->getBody()->__toString());
            if (isset($res->data->metaobjectUpsert->metaobject->id)) {
                $metaObjectId = $res->data->metaobjectUpsert->metaobject->id;
            }
        }
        return $metaObjectId;

    }

    public function createMetaObjectForTrainingPreReq($productPreReq, $productPreReqSort) 
    {
        $handleInput = '{
            type: "training_prerequisites_object",
            handle: "' .$productPreReq. '"
        }';  
        $metaObjectFieldsInput = '{
            fields:[
                {
                    key: "sku",
                    value: "' .$productPreReq. '"

                }
                {
                    key: "sort",
                    value: "' .$productPreReqSort. '"
                }
            ]
        }';
        //create new metaObject if handler is not found else will update the existing 
        $metaObjectMutation  = <<<QUERY
            mutation {
                metaobjectUpsert(handle: $handleInput, metaobject: $metaObjectFieldsInput) {
                    metaobject {
                        handle
                        sku: field(key: "Sku") {
                          value
                        }
                        id
                        fields{
                          key
                        }
                    }
                    userErrors {
                        field
                        message
                        code
                    }
                }
            }
            QUERY;
        $client = new Graphql($this->shop, $this->accessToken);
        $response = $client->query(["query" => $metaObjectMutation]);

        $metaObjectId = null;
        if ($response->getStatusCode() !== 200) {
            throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
        } else {
            $res = json_decode($response->getBody()->__toString());
            if (isset($res->data->metaobjectUpsert->metaobject->id)) {
                $metaObjectId = $res->data->metaobjectUpsert->metaobject->id;
            }
        }
        return $metaObjectId;

    }


    public function setProductMetaFields($createProductsArr, $functionName, $key)
    {
        $productUpdatedIdArr = [];
        $client = new Graphql($this->shop, $this->accessToken);
        foreach ($createProductsArr as $vaultProduct) {

            list($mainProductGId, $value, $metaFieldId) = $this->$functionName($vaultProduct);
            //Managing the case of null 
            if ($mainProductGId) {
                Log::debug("Adding Product MetaFields....");
                // Id is only required when updating a metafield, but shouldn't be included when creating as it's created automatically
                if ($metaFieldId) {
                    $input = '{
                        id: "' . $mainProductGId . '",
                        metafields: [{
                        id: "' . $metaFieldId . '",
                        namespace: "custom",
                        key: "' . $key . '",
                        value: "' . addslashes(json_encode($value)) . '",
                        type: "list.metaobject_reference"
                        }]
                    }';
                } else {
                    $input = '{
                        id: "' . $mainProductGId . '",
                        metafields: [{
                        namespace: "custom",
                        key: "' . $key . '",
                        value: "' . addslashes(json_encode($value)) . '",
                        type: "list.metaobject_reference"
                        }]
                    }';     
                }

                // create product mutation for updation 
                $productUpdateQuery = <<<QUERY
                        mutation {
                            productUpdate(input: $input) {
                            product {
                                id
                                title
                                metafields(first: 10) {
                                edges {
                                    node {
                                    id
                                    key
                                    value
                                    }
                                }
                                }
                            }
                            userErrors {
                                field
                                message
                            }
                            }
                        }
                        QUERY;

                $response = $client->query(["query" => $productUpdateQuery]);

                if ($response->getStatusCode() !== 200) {
                    Log::debug("Main Product does not exist with this id  - $mainProductGId");
                    throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
                } else {
                    $res = json_decode($response->getBody()->__toString());
                    if (!isset($res->errors) && isset($res->data->productUpdate->product->id)) {
                        $globalId = $res->data->productUpdate->product->id;
                        $exploded = explode('/', $globalId);
                        if (count($exploded) && isset($exploded[4]) && is_numeric($exploded[4])) {
                            $productUpdatedIdArr[] = $exploded[4];
                        }
                    }
                }

                    Log::debug("==============================");
                    Log::debug("\n");

            }
        }
        return $productUpdatedIdArr;
    }

    public function addProductBundles($createProductsArr)
    {
        $productUpdatedIdArr = [];
        $client = new Graphql($this->shop, $this->accessToken);
        foreach ($createProductsArr as $vaultProduct) {

            list($mainProductGId, $productBundleComponents, $productComponentcStr, $productMetafields) = $this->prepareProductBundle($vaultProduct);

            if (!empty($mainProductGId) && !empty($productBundleComponents)) {
                Log::debug("Adding Product Bundle....");

                Log::debug('=====productMetafields====');
                Log::debug(print_r($productMetafields, true));

                // id: "gid://shopify/Metafield/21930115149", // product component
                // id: "gid://shopify/Metafield/23358144589", // Training Prerequisites

                $productMetafieldId = "gid://shopify/Metafield/21930115149"; // @todo: make Metafield id dynamic
                if (!empty($productMetafields) && isset($productMetafields['product_bundle_components'])) {
                    $productMetafieldId = $productMetafields['product_bundle_components'];
                }

                $input = '{
                        id: "' . $mainProductGId . '",
                        metafields: [{
                          id: "' . $productMetafieldId . '",
                          namespace: "custom",
                          key: "product_bundle_components",
                          value: "' . $productBundleComponents . '",
                          type: "list.product_reference"
                        }]
                      }';

                // create product mutation
                $bundleProductQuery = <<<QUERY
                    mutation {
                        productUpdate(input: $input) {
                          product {
                            id
                            title
                            metafields(first: 10) {
                              edges {
                                node {
                                  id
                                  key
                                  value
                                }
                              }
                            }
                          }
                          userErrors {
                            field
                            message
                          }
                        }
                      }
                    QUERY;

                $response = $client->query(["query" => $bundleProductQuery]);

                if ($response->getStatusCode() !== 200) {
                    Log::debug("No Product was added as a bundle to Product Id - $mainProductGId");
                    throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
                } else {
                    $res = json_decode($response->getBody()->__toString());
                    if (!isset($res->errors) && isset($res->data->productUpdate->product->id)) {
                        $globalId = $res->data->productUpdate->product->id;
                        $exploded = explode('/', $globalId); // gid://shopify/Product/7049493053517
                        if (count($exploded) && isset($exploded[4]) && is_numeric($exploded[4])) {
                            $productUpdatedIdArr[] = $exploded[4];
                            if (!empty($mainProductGId) && !empty($productComponentcStr)) {

                                Log::debug('========bundleProductQuery========');
                                Log::debug(print_r($bundleProductQuery, true));

                                Log::debug("$productComponentcStr added as a bundle to Product Id - $mainProductGId");
                            }
                        }
                    }
                }
                Log::debug("==============================");
                Log::debug("\n");
            }
        }

        return $productUpdatedIdArr;
    }

    public function prepareProductBundle($vaultProduct)
    {
        // create bundles if any
        // add existing product to the product metafields
        $mainProductGId = $productBundleComponents = $productComponentcStr = '';
        $productMetafields = [];

        if (isset($vaultProduct['product_bundle_components1__cr'])) {

            $productComponentc = [];

            if (!isset($vaultProduct['product_bundle_components1__cr']['data'])) {
                return $productComponentc;
            }

            foreach ($vaultProduct['product_bundle_components1__cr']['data'] as $productBundle) {
                if (isset($productBundle['product_bundle__c']) && $productBundle['product_bundle__c'] == $vaultProduct['id']) {
                    if (isset($productBundle['product_component__c'])) {

                        list($bundleProductGId, $variantGId, $productMetafields) = $this->checkIfProductExists($productBundle['product_component__c']);
                        if ($bundleProductGId) {

                            Log::debug("Checking if Product Component..... with SKU " . $productBundle['product_component__c'] . " & ID SID - $bundleProductGId exists");

                            // product query
                            // $bundleProductGId = 'gid://shopify/Product/' . $bundleProductId;
                            $productQuery = <<<QUERY
                                query {
                                    product(id: "$bundleProductGId") {
                                      title
                                      description
                                      onlineStoreUrl
                                    }
                                  }
                                QUERY;

                            $client = new Graphql($this->shop, $this->accessToken);
                            $response = $client->query(["query" => $productQuery]);

                            if ($response->getStatusCode() !== 200) {
                                Log::debug("Product Bundle..... with ID $bundleProductGId not found in Shopify");
                                throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
                            } else {
                                $res = json_decode($response->getBody()->__toString());
                                $productComponentc[] = $bundleProductGId;
                                Log::debug("Product Bundle..... with ID $bundleProductGId found in Shopify");
                            }
                        }
                    }
                }
            }

            if (!empty($productComponentc)) {

                $productComponentcStr = '["' . implode('","', $productComponentc) . '"]';
                $productBundleComponents = addslashes($productComponentcStr);

                // $mainProductGId = '';
                list($mainProductGId, $variantId, $productMetafields) = $this->checkIfProductExists($productBundle['product_bundle__c']);
                // if ($mainProductGId) {
                //     $mainProductGId = 'gid://shopify/Product/' . $mainProductId;
                // }
            }
        }

        return [$mainProductGId, $productBundleComponents, $productComponentcStr, $productMetafields];
    }

    public function createProduct()
    {
        $collect = $productIdArr = $productUpdatedIdArr =  $productPreReqUpdatedIdArr = [];

        // create Product
        list($productIdArr, $productUpdatedIdArr, $productPreReqUpdatedIdArr, $productTrainingCrUpdatedIdArr) = $this->createProductByGraphQL();

        // create collection and add the products to the collection
        if (!isset($productIdArr['updated'])) {
            if (count($productIdArr)) {
                list($collectionId, $collectionArr) = $this->getCustomCollectionId();
                if (isset($collectionId)) {
                    foreach ($productIdArr as $productId) {
                        $collect[] = $this->addProductToCustomCollection($productId, $collectionId);
                    }
                }
            }
        }

        // $webhookService = new WebhookService($this->request);
        // $webhookService->register(Topics::ORDERS_CREATE);
        // $webhookService->register(Topics::PRODUCTS_CREATE);
        // $webhookService->deleteWebhook(1132221628493);
        // $webhookService->deleteWebhook(1132197871693);


        // $change = [];
        // $change['address'] = "https://7d8e-2405-201-e-408d-f123-cd3d-ae38-2973.ngrok-free.app/api/webhooks";
        // $webhookService->updateWebhook(1132221726797, $change);
        // $webhookService->updateWebhook(1130559668301, $change);

        // $webhookService->getListOfWebhooks();
        // $webhookService->getWebhooksCount();

        return [$productIdArr, $collect, $productUpdatedIdArr, $productPreReqUpdatedIdArr, $productTrainingCrUpdatedIdArr];
    }

    public function productSync($mainProductGId, $variantGId, $vaultProduct)
    {
        $productUpdatedIdArr = '';
        // $mainProductGId = 'gid://shopify/Product/' . $mainProductId;

        $title = $vaultProduct['name__v'];
        $price = $vaultProduct['track_price'];
        $sku = $vaultProduct['id'];
        $description = $vaultProduct['product_description__c'];

        $status = $this->getProductStatus($vaultProduct);

        $input = '{
            id : "' . $mainProductGId . '",
            title: "' . $title . '",
            descriptionHtml: "' . $description . '",
            status: ' . $status . ',
            variants: [
                {
                  id: "' . $variantGId . '"
                  price: ' . $price . '
                }
            ]
        }';

        // update product mutation
        $bundleProductQuery = <<<QUERY
        mutation {
            productUpdate(input: $input) {
              product {
                id
                title
                metafields(first: 10) {
                  edges {
                    node {
                      id
                      key
                      value
                    }
                  }
                }
              }
              userErrors {
                field
                message
              }
            }
          }
        QUERY;

        // Log::debug(print_r(["query" => $bundleProductQuery], true));

        $client = new Graphql($this->shop, $this->accessToken);
        $response = $client->query(["query" => $bundleProductQuery]);

        if ($response->getStatusCode() !== 200) {
            Log::debug("No Product was updated as a bundle to Product Id - $mainProductGId");
            throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
        } else {
            $res = json_decode($response->getBody()->__toString());
            if (!isset($res->errors) && isset($res->data->productUpdate->product->id)) {
                $globalId = $res->data->productUpdate->product->id;
                $exploded = explode('/', $globalId); // gid://shopify/Product/7049493053517
                if (count($exploded) && isset($exploded[4]) && is_numeric($exploded[4])) {
                    $productUpdatedIdArr = $exploded[4];
                    // if (!empty($mainProductId) && !empty($productComponentcStr)) {
                    // Log::debug("$productComponentcStr Updated as a bundle to Product Id - $mainProductId");
                    // }
                    Log::debug("Product Id - $mainProductGId updated");
                }
            }
        }

        // $inventoryLevelId = $mainProductGId;
        // $productInventoryQuery = <<<QUERY
        //     mutation {
        //     inventoryDeactivate(inventoryLevelId: $inventoryLevelId) {
        //         userErrors {
        //         message
        //         }
        //     }
        // }
        // QUERY;

        // $response = $client->query(["query" => $productInventoryQuery]);
        // if ($response->getStatusCode() !== 200) {
        //     Log::debug("No Product was updated as a bundle to Product Id - $mainProductId");
        //     throw new ShopifyProductCreatorException($response->getBody()->__toString(), $response);
        // }

        return $productUpdatedIdArr;
    }

    public function getProductStatus($vaultProduct)
    {
        if (isset($vaultProduct['status__v']) && isset($vaultProduct['status__v'][0])) {
            switch ($vaultProduct['status__v'][0]) {
                case 'active__v':
                    $status = 'ACTIVE';
                    break;

                case 'inactive__v':
                    $status = 'ARCHIVED';
                    break;

                default:
                    $status = 'ACTIVE';
                    break;
            }
        }

        if (isset($vaultProduct['training_internal_use_only__c'])) {
            if ($vaultProduct['training_internal_use_only__c']) {
                // if a product is set to only use for internal training
                // then dont display it on storefront
                $status = 'ARCHIVED';
            } else {
                $status = 'ACTIVE';
            }
        }

        return $status;
    }

    private static function PullDataFromVault()
    {
        $vaultProductService = new VaultProductService(new Client);
        $vaultProductsArr = $vaultProductService->getProducts();
        return $vaultProductsArr;
    }
}