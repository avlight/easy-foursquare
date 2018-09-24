<?php

namespace Lib;

use Lib\FoursquareApi;
use Lib\HttpUtils;
use Lib\CurlCookie;

/**
 * Class FoursquareClient
 * @package Lib
 *
 * Класс отвечает за обращение к API Foursquare
 */
class FoursquareClient
{
    const REDIRECT_URL = "/api/endpoint-by-code?";
    const CLIENT_KEY = "<your client key>"; //TODO move to config file
    const CLIENT_SECRET = "<your secret>"; //TODO move to config file

    public function __construct()
    {
        //TODO
    }

    public static function getRedirectUrl(array $params = [])
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = "localhost";
        }

        $actualLink = "http://{$_SERVER['HTTP_HOST']}";
        $url = $actualLink . self::REDIRECT_URL;
        $url .= http_build_query($params);
        return $url;
    }

    public static function getClientKey()
    {
        return self::CLIENT_KEY;
    }

    public static function getClientSecret()
    {
        return self::CLIENT_SECRET;
    }

    /**
     * Вызов заданного метода API Foursquare
     *
     * @param string $endpoint
     * @param array $params
     * @throws \Exception
     */
    public function endpointCall(string $endpoint, array $params = [], string $method = "GET")
    {
        $fsApi = new FoursquareApi(self::getClientKey(), self::getClientSecret());

        $redirectUrl = FoursquareClient::getRedirectUrl([
            'endpoint' => $endpoint,
        ]);

        // Ссылка на страницу с формой авторизации
        $authFormLink = $fsApi->AuthenticationLink($redirectUrl);
        $response = self::getAuthFormHTMLContent($authFormLink);
        $html = &$response["body"];
        $headers = &$response["headers"];
        unset($response);

        $cobj = new CurlCookie();
        $cobj->appendFromHeaders($headers);

        $cookies = $cobj->collection();
        unset($cobj);

        // !!! Важно! Если изменится выдача на странице - метод перестанет работать !!!
        $formSubmitData = $this->getFormSubmitData($html);

        $requestHeaders = [
            "Host: foursquare.com",
            "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.162 Safari/537.36",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
            "Accept-Language: en-US,en;q=0.9",
            "Connection: keep-alive",
        ];
        $result = $this->sumbitForm(
            $authFormLink,
            $formSubmitData,
            $requestHeaders,
            $cookies
        );

        if (empty($result)) {
            throw new \Exception("Request result error!");
        }

        $uParts = parse_url($result["location"]);
        $qParams = HttpUtils::splitQuery($uParts['query']);
        $code = $qParams["code"];
        $endpoint = $qParams["endpoint"];

        $token = $fsApi->GetToken($code, $redirectUrl);
        $fsApi->SetAccessToken($token);

        $response = $fsApi->GetPrivate(urldecode($endpoint), $params);

        if (empty($response)) {
            throw new \Exception("Response is empty or incorrect");
        }

        $response = json_decode($response, true);
        return $response;
    }

    public static function getAuthFormHTMLContent(string $link, $headersToo = false) {
        // Получаем содержимое страницы с формой ручной авторизации
        $ch = curl_init($link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $responseText = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return HttpUtils::separateCurlResponse($responseText, $headerSize);
    }

    public static function getFormSubmitData($html) {
        $selector = new \SelectorDOM\SelectorDOM($html);

        $form = current($selector->select("form#loginToFoursquare"));

        $formMethod = $form['attributes']['method'];
        $formData = [];
        $formData['fs-request-signature'] = call_user_func(function () use($form) {
            $signature = null;
            foreach ($form['children'] as $fel) {
                if ($fel['attributes']['name'] == 'fs-request-signature') {
                    $signature = $fel['attributes']['value'];
                    break;
                }
            }
            return $signature;
        });
        $formData['shouldAuthorize'] = call_user_func(function () use($form) {
            $shouldAuthorize = null;
            foreach ($form['children'] as $fel) {
                if ($fel['attributes']['name'] == 'shouldAuthorize') {

                    $shouldAuthorize = $fel['attributes']['value'];
                    break;
                }
            }
            return $shouldAuthorize;
        });
        $formData['emailOrPhone'] = self::getLogin();
        $formData['password'] = self::getPassword();

        return $formData;
    }

    public static function getLogin() {
        return "your login here"; //TODO move to config file and read it from there
    }

    public static function getPassword() {
        return "your password here"; //TODO move to config file and read it from there
    }

    /**
     * Отправить форму с данными авторизации и другими параметрами
     *
     * @param string $authLink Ссылка с формой авторизации
     * @param array $formSubmit Данные формы (POST-параметры
     * @param array $headers Массив с заголовками
     * @param array $cookies Массив объектов Lib\Cookie
     * @throws \Exception
     */
    public static function sumbitForm(
        string $authLink,
        array $formSubmit,
        array $headers = [],
        array $cookies = []
    ) {
        $httpHeaders = [
            // здесь можно определить обязательные заголовки (по умолчанию)
            "Content-Type: application/x-www-form-urlencoded",
            "User-Agent: " . self::_generateRandomUserAgent(),
        ];

        $curl = curl_init();
        $httpHeaders = array_merge($httpHeaders, ["Cookie: " . join("; ", $cookies)]);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $authLink,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($formSubmit, '', '&'),
            CURLOPT_HTTPHEADER => $httpHeaders,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        $info = curl_getinfo($curl);
        curl_close($curl);

        if (empty($info['redirect_url'])) {
            throw new \Exception("Something wrong! Request failed!");
        }

        $response = HttpUtils::separateCurlResponse($response, $headerSize);
        $headers = $response['headers'];
        unset($response);

        if (empty($headers["Location"]) || empty($headers["Set-Cookie"])) {
            throw new \Exception("Something wrong! Incorrect response!");
        }

        $cc = new CurlCookie();
        $cc->appendFromHeaders($headers);
        $cookie = $cc->findByName("oauth_token");
        if (!$cookie) {
            throw new \Exception("oauth_token not found!");
        }
        $oAuthToken = $cookie->get("value");

        return [
            "location" => $headers["Location"],
            "authToken" => $oAuthToken,
        ];
    }

    public static function separateCurlResponse(string $response, int $headerSize) {
        $headerOutput = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        unset($response);

        $headers = explode("\n", $headerOutput);

        // удаление 2 перевода строки ("\n")
        array_pop($headers);
        array_pop($headers);

        // Форматирование заголовков ключ => значение
        $headers = call_user_func(function () use(&$headers) {
            $status = array_shift($headers);

            $formattedHeaders = [];
            $formattedHeaders["status"] = $status;
            unset($status);

            foreach ($headers as &$header) {
                $parts = explode(": ", $header);
                if (2 != count($header)) continue;

                $key = $parts[0];
                $value = $parts[1];
                $formattedHeaders[$key] = $value;
            }

            return $formattedHeaders;
        });

        return [
            "headers" => $headers,
            "body" => $body,
        ];
    }

    protected static function _generateRandomUserAgent() {
        $userAgents = [
            // добавьте
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.162 Safari/537.36",
        ];
        $uagent = $userAgents[array_rand($userAgents)];
        return $uagent;
    }
}