<?php
/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 7/26/18
 * Time: 2:15 PM
 */

namespace Lib;

use Lib\CurlCookie;
use Lib\Cookie;

class HttpUtils
{
    /**
     * Разделяет текст ответа на заголовки и тело (HTML)
     *
     * @param string
     *
     * @return array
     */
    public static function separateCurlResponse(string $response, int $headerSize = 0) {
        if ($headerSize == 0) {
            $headerSize = self::calcHeaderSize($response);
        }

        $headerOutput = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $body = trim($body);
        unset($response);

        // Форматирование заголовков ключ => значение
        $headersFormatter = function(array $headers) {
            $status = trim(array_shift($headers));

            $formattedHeaders = [];
            $formattedHeaders["status"] = [$status];
            unset($status);

            foreach ($headers as &$header) {
                $parts = explode(": ", $header);
                if (2 != count($parts)) continue;

                $key = $parts[0];
                $value = trim($parts[1]);
                $formattedHeaders[$key][] = $value;
            }

            foreach ($formattedHeaders as $key => &$value) {
                if (count($value) == 1) {
                    $value = $value[0];
                }
                $formattedHeaders[$key] = $value;
            }

            $filtered = array_filter($formattedHeaders, function($item) {
                return (
                    is_array($item)
                    && count($item) > 1
                    && count(array_unique($item)) != count($item)
                );
            });

            // Исключить повторяющиеся
            foreach ($filtered as $key => &$items) {
                $formattedHeaders[$key] = array_unique($items);
            }

            return $formattedHeaders;
        };
        $headers = $headersFormatter(explode("\n", $headerOutput));

        return [
            "headers" => $headers,
            "body" => $body,
        ];
    }

    public static function calcHeaderSize(string $response) {
        $pattern = "\n\n<";
        mb_ereg_search_init($response);
        $result = mb_ereg_search_pos($pattern);

        if (!is_array($result)) {
            throw new \Exception("Error finding end of title lines!");
        }

        return $result[0];
    }

    public static function parseHeadersCookies(array $headers, string $key = 'Set-Cookie') {
        if (!isset($headers[$key])) {
            return null;
        }

        $cookies = $headers[$key];
        unset($headers);

        if (is_array($cookies)) {
            //Do nothing
        } else if (is_string($cookies)) {
            $cookies = [$cookies];
        }

        $cc = new CurlCookie();
        $cc->append($cookies);

        return $cc->collection();
    }

    public static function splitQuery(string $query) {
        if (empty($query)) {
            return [];
        }

        $list = explode("&", $query);
        $params = [];
        foreach ($list as &$item) {
            $result = explode("=", $item);
            if (empty($result[1])) {
                $result[1] = null;
            }
            $params[$result[0]] = $result[1];
        }

        return $params;
    }
}