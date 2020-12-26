<?php

use Amp\Http\Client\Cookie\CookieInterceptor;
use Amp\Http\Client\Cookie\InMemoryCookieJar;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Logger;

require_once "vendor/autoload.php";

ini_set('zend.assertions', 0);
const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0";

Loop::run(function () {
    $handler = new StreamHandler(Amp\ByteStream\getStdout());
    $handler->setFormatter(new ConsoleFormatter());

    $mailHandler = new NativeMailerHandler("hussainweb@gmail.com", "Stock Checker Script", "hussainweb@gmail.com");
    $mailHandler->setContentType("text/html");

    $logger = new Logger('ps5-stock-checker');
    $logger->setTimezone(new DateTimeZone("America/Toronto"));
    $logger->useMicrosecondTimestamps(false);
    $logger->pushHandler($handler);
    $logger->pushHandler($mailHandler);

    $httpClientBuilder = new HttpClientBuilder();
    $httpClientBuilder->followRedirects(0);
    $httpClientBuilder->interceptNetwork(new CookieInterceptor(new InMemoryCookieJar()));
    $httpClient = $httpClientBuilder->build();

    $walmartData = [
        'client' => $httpClient,
        'logger' => $logger->withName('walmart-ps5-stock-checker'),
        'delay' => [260, 390]
    ];
    $bestbuyData = [
        'client' => $httpClient,
        'logger' => $logger->withName('bestbuy-ps5-stock-checker'),
        'delay' => [50, 70]
    ];

    Loop::delay(250, "getStockFromWalmartCa", $walmartData);
    Loop::delay(1500, "getStockFromBestBuyCa", $bestbuyData);
});

function getStockFromWalmartCa($_, $cbData): \Generator
{
    /** @var \Amp\Http\Client\HttpClient $client */
    $client = $cbData['client'];
    /** @var Logger $logger */
    $logger = $cbData['logger'];

    // Delay is large enough for us to do this early.
    $delay = $cbData['delay'];
    Loop::delay(1000 * rand($delay[0], $delay[1]), __FUNCTION__, $cbData);

    $url = "https://www.walmart.ca/en/ip/playstation5-console/6000202198562";
    $request = new Request($url);
    $request->setHeader("User-Agent", USER_AGENT);
    /** @var \Amp\Http\Client\Response $response */
    $response = yield $client->request($request);

    if ($response->getStatus() != 200) {
        $logger->error("Initial page load failed", ['status' => $response->getStatus(), 'reason' => $response->getReason()]);
        return;
    }

    $correlationId = $response->getHeader("wm_qos.correlation_id");
    if (!$correlationId) {
        $logger->error("Could not find correlation ID", ['body' => yield $response->getBody()->buffer()]);
        return;
    }
    $logger->info("Walmart Initial page load correlation ID", ['correlationId' => $correlationId]);

    // Now make the Ajax request for the page
    $url = "https://www.walmart.ca/api/product-page/v2/price-offer";
    $request = new Request($url, "POST", '{"fsa":"L5R","products":[{"productId":"6000202198562","skuIds":["6000202198563"]}],"lang":"en","pricingStoreId":"3055","fulfillmentStoreId":"1061","experience":"whiteGM"}');
    $request->setHeader("User-Agent", USER_AGENT);
    $request->setHeader("Accept", "application/json");
    $request->setHeader("Accept-Language", "en-US,en;q=0.5");
    $request->setHeader("Referer", "https://www.walmart.ca/en/ip/playstation5-console/6000202198562");
    $request->setHeader("wm_qos.correlation_id", $correlationId);
    $request->setHeader("content-type", "application/json");

    /** @var \Amp\Http\Client\Response $response */
    $response = yield $client->request($request);
    $status = $response->getStatus();
    if ($status != 200) {
        $logger->error("Walmart Check failed", ['status' => $status, 'reason' => $response->getReason()]);
        return;
    }

    $resp_json = yield $response->getBody()->buffer();
    $data = \json_decode($resp_json, true);
    if ($data['offers']['6000202198563']['gmAvailability'] != "OutOfStock") {
        Loop::defer("alertPS5Available", [
            'source' => 'walmart',
            'url' => 'https://www.walmart.ca/en/ip/playstation5-console/6000202198562',
            'availability' => $data['offers']['6000202198563']['gmAvailability'],
            'response_data' => $data,
            'logger' => $logger,
        ]);
    }
    $logger->info("Walmart Check complete", ['status' => $status, 'availability' => $data['offers']['6000202198563']['gmAvailability']]);
}

function getStockFromBestBuyCa($_, $cbData): \Generator
{
    /** @var \Amp\Http\Client\HttpClient $client */
    $client = $cbData['client'];
    /** @var Logger $logger */
    $logger = $cbData['logger'];

    // Delay is large enough for us to do this early.
    $delay = $cbData['delay'];
    Loop::delay(1000 * rand($delay[0], $delay[1]), __FUNCTION__, $cbData);

    $url = "https://www.bestbuy.ca/ecomm-api/availability/products?accept=application%2Fvnd.bestbuy.standardproduct.v1%2Bjson&accept-language=en-CA&locations=202%7C926%7C233%7C938%7C622%7C930%7C207%7C954%7C57%7C245%7C617%7C795%7C916%7C910%7C544%7C203%7C990%7C927%7C977%7C932%7C62%7C931%7C200%7C237%7C942%7C965%7C956%7C943%7C937%7C213%7C984%7C982%7C631%7C985&postalCode=L5R1V4&skus=14962185";
    $request = new Request($url);
    $request->setHeader("User-Agent", USER_AGENT);
    $request->setHeader("Accept", "*/*");
    $request->setHeader("Accept-Language", "en-US,en;q=0.5");
    $request->setHeader("Referer", "https://www.bestbuy.ca/en-ca/product/playstation-5-console-online-only/14962185");
    $request->setHeader("x-dtpc", "1$232704806_254h7vPCCHWJFTHUVPCJEUIQPMTKPGFCPHSGOJ-0e2");
    $request->setHeader("Cache-Control", "max-age=0");

    /** @var \Amp\Http\Client\Response $response */
    $response = yield $client->request($request);
    $status = $response->getStatus();
    if ($status != 200) {
        $logger->error("BestBuy Check failed", ['status' => $status, 'reason' => $response->getReason()]);
        return;
    }

    $resp_json = yield $response->getBody()->buffer();
    $resp_json = $string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', trim($resp_json));
    $data = \json_decode($resp_json, true);
    if ($data['availabilities'][0]['shipping']['status'] != "ComingSoon") {
        Loop::defer("alertPS5Available", [
            'source' => 'bestbuy',
            'url' => 'https://www.bestbuy.ca/en-ca/product/playstation-5-console-online-only/14962185',
            'availability' => $data['availabilities'][0]['shipping']['status'],
            'response_data' => $data,
            'logger' => $logger,
        ]);
    }
    $logger->info("BestBuy Check complete", ['status' => $status, 'availability' => $data['availabilities'][0]['shipping']['status']]);
}

function alertPS5Available($_, $cbData)
{
    /** @var Logger $logger */
    $logger = $cbData['logger'];
    $logger->alert("Stock found!", $cbData);
}
