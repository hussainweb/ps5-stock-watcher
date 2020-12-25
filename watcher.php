<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Monolog\Logger;

require_once "vendor/autoload.php";

Loop::run(function () {
    $handler = new StreamHandler(Amp\ByteStream\getStdout());
    $handler->setFormatter(new ConsoleFormatter);

    $logger = new Logger('ps5-stock-checker');
    $logger->pushHandler($handler);

    $httpClientBuilder = new HttpClientBuilder();
    $httpClientBuilder->followRedirects(0);
    $httpClient = $httpClientBuilder->build();

    $walmartData = ['client' => $httpClient, 'logger' => $logger->withName('walmart-ps5-stock-checker')];
    $bestbuyData = ['client' => $httpClient, 'logger' => $logger->withName('bestbuy-ps5-stock-checker')];

    Loop::repeat(300000, "getStockFromWalmartCa", $walmartData);
    Loop::defer("getStockFromWalmartCa", $walmartData);
    Loop::repeat(60000, "getStockFromBestBuyCa", $bestbuyData);
    Loop::defer("getStockFromBestBuyCa", $bestbuyData);
});

function getStockFromWalmartCa($_, $cbData): \Generator {
    $client = $cbData['client'];
    $logger = $cbData['logger'];

    $url = "https://www.walmart.ca/api/product-page/v2/price-offer";
    $request = new Request($url, "POST", '{"fsa":"L5R","products":[{"productId":"6000202198562","skuIds":["6000202198563"]}],"lang":"en","pricingStoreId":"3055","fulfillmentStoreId":"1061","experience":"whiteGM"}');
    $request->setHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0");
    $request->setHeader("Accept", "application/json");
    $request->setHeader("Accept-Language", "en-US,en;q=0.5");
    $request->setHeader("Referer", "https://www.walmart.ca/en/ip/playstation5-console/6000202198562");
    $request->setHeader("content-type", "application/json");

    /** @var \Amp\Http\Client\Response $response */
    $response = yield $client->request($request);
    $status = $response->getStatus();
    $reason = $response->getReason();
    if ($status != 200) {
        $logger->error(sprintf("%s %s", $status, $reason));
        return;
    }

    $resp_json = yield $response->getBody()->buffer();
    $data = \json_decode($resp_json, TRUE);
    if ($data['offers']['6000202198563']['gmAvailability'] != "OutOfStock") {
        Loop::defer("alertPS5Available", [
            'source' => 'walmart',
            'url' => 'https://www.walmart.ca/en/ip/playstation5-console/6000202198562',
            'availability' => $data['offers']['6000202198563']['gmAvailability'],
            'response_data' => $data,
        ]);
    }
    $logger->info(sprintf("%s %s %s", $status, $reason, $data['offers']['6000202198563']['gmAvailability']));
}

function getStockFromBestBuyCa($_, $cbData): \Generator {
    $client = $cbData['client'];
    $logger = $cbData['logger'];

    $url = "https://www.bestbuy.ca/ecomm-api/availability/products?accept=application%2Fvnd.bestbuy.standardproduct.v1%2Bjson&accept-language=en-CA&locations=202%7C926%7C233%7C938%7C622%7C930%7C207%7C954%7C57%7C245%7C617%7C795%7C916%7C910%7C544%7C203%7C990%7C927%7C977%7C932%7C62%7C931%7C200%7C237%7C942%7C965%7C956%7C943%7C937%7C213%7C984%7C982%7C631%7C985&postalCode=L5R1V4&skus=14962185";
    $request = new Request($url);
    $request->setHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0");
    $request->setHeader("Accept", "*/*");
    $request->setHeader("Accept-Language", "en-US,en;q=0.5");
    $request->setHeader("Referer", "https://www.bestbuy.ca/en-ca/product/playstation-5-console-online-only/14962185");
    $request->setHeader("x-dtpc", "1$232704806_254h7vPCCHWJFTHUVPCJEUIQPMTKPGFCPHSGOJ-0e2");
    $request->setHeader("Cache-Control", "max-age=0");

    /** @var \Amp\Http\Client\Response $response */
    $response = yield $client->request($request);
    $status = $response->getStatus();
    $reason = $response->getReason();
    if ($status != 200) {
        $logger->error(sprintf("%s %s", $status, $reason));
        return;
    }

    $resp_json = yield $response->getBody()->buffer();
    $resp_json = $string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', trim($resp_json));
    $data = \json_decode($resp_json, TRUE);
    if ($data['availabilities'][0]['shipping']['status'] != "ComingSoon") {
        Loop::defer("alertPS5Available", [
            'source' => 'bestbuy',
            'url' => 'https://www.bestbuy.ca/en-ca/product/playstation-5-console-online-only/14962185',
            'availability' => $data['availabilities'][0]['shipping']['status'],
            'response_data' => $data,
        ]);
    }
    $logger->info(sprintf("%s %s %s", $status, $reason, $data['availabilities'][0]['shipping']['status']));
}

function alertPS5Available($cbData) {
    // @TODO
}
