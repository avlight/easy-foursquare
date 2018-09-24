<?php

namespace Lib;

use Lib\Cookie;

class CurlCookie implements \Iterator
{
    /**
     * Массив объектов Lib\Cookie
     *
     * @var array
     */
    protected $_collection = [];

    /* ============ Iterator implementation start ============= */

    public function current() {
        return current($this->_collection);
    }

    public function next() {
        return next($this->_collection);
    }

    public function key() {
        return key($this->_collection);
    }

    public function valid() {
        $c = current($this->_collection);
        return isset($c);
    }

    public function rewind() {
        reset($this->_collection);
    }

    /**
     * Искать в коллекции по имени куки.
     *
     * @param string $name
     * @return mixed|null
     */
    public function findByName(string $name) {
        $obj = null;
        while ($this->valid()) {
//            var_dump(1, $this->current());
            if ($this->current()->get("name") == $name) {
                return $this->current();
            }
            $this->next();
        }
        return $obj;
    }

    /**
     * CurlCookie constructor.
     * @param string $cookieString
     */
    public function __construct(string $cookieString = "")
    {
        $this->_collection = null;

        if (!empty($cookieString)) {
            $this->setRawString($cookieString);
        }
    }

    /**
     * Импортировать строку, преобразовать в объект Cookie
     *
     * @param string $raw
     */
    public function setRawString(string $raw) {
        $attributes = explode("; ", $raw);
        $this->_setCookie($attributes);
    }

    /**
     * Преобразование строки в объект Cookie
     *
     * @param array $attributes
     */
    public function _setCookie(array $attributes) {
        $gen = $this->_splitAttributeAndValue($attributes);

        $cookies = [];
        $cookieObj = new Cookie();
        while ($gen->valid()) {
            $pair = $gen->current();
            $cookieObj->setAttribute(strtolower($pair[0]), $pair[1] ?? null);
            $gen->next();
        }
        $this->_collection[$cookieObj->get("name")] = $cookieObj;
    }

    /**
     * Метод-генератор, перебирающий атрибуты куки
     *
     * @param array $attributes
     * @return \Generator
     */
    protected function _splitAttributeAndValue(array $attributes) {
        $names = [
            "name",
            "value",
            "domain",
            "expire",
            "path",
            "secure",
            "httpOnly",
        ];

        $first = true; // первая пара - это всегда "имя" и "значение" куки
        $values = [];
        $i = 0; // счетчик имен атрибутов
        foreach ($attributes as &$attr) {
            $pair = explode('=', $attr);

            // обработка имени и значения куки
            if ($i < 2) {
                yield [$names[0], $pair[0]];
                yield [$names[1], $pair[1]];
                $i = 2;
                continue;
            }

            yield $pair;
            $i++;
        }
    }

    /**
     * Добавить куки в коллекцию
     *
     * @param array $cookies
     * @throws \Exception
     */
    public function append(array $cookies) {

        if (empty($cookies)) {
            throw new \Exception("At least one element in the array is required!");
        }

        // определить количество элементов
        if (count($cookies) < 2) {
        // один элемент
            $this->appendOne(current($cookies));
        } else {
            $this->appendMany($cookies);
        }
    }

    /**
     * Добавить одну куку в коллекцию
     *
     * @param string $cookieString
     */
    public function appendOne(string $cookieString) {
        $this->setRawString($cookieString);
    }

    /**
     * Добавить множество кук в коллекцию.
     *
     * @param array $cookieStringItems
     */
    public function appendMany(array $cookieStringItems) {
        foreach ($cookieStringItems as &$cookiesString) {
            $this->appendOne($cookiesString);
        }
    }

    /**
     * Добавить куки осуществив поиск их в заголовках (headers)
     *
     * @param array $headers
     */
    public function appendFromHeaders(array $headers) {
        //TODO
        $cookies = null;
        if (isset($headers["Set-Cookie"])) {
            $cookies = $headers["Set-Cookie"];
        } else if (isset($headers["set-cookie"])) {
            $cookies = $headers["set-cookie"];
        }

        if (is_array($cookies)) {
            $this->appendMany($cookies);
        } else if (is_string($cookies)) {
            $this->appendOne($cookies);
        }
    }

    /**
     * Вернуть собранную коллекцию.
     *
     * @param boolean $simple В простом формате ключ => значение (имя, значение)
     * @return array|null
     */
    public function collection($simple = false) {
        if (!$simple) return $this->_collection;
        return $this->getPairs();
    }

    public function getPairs() {
        $pairs = [];
        foreach ($this->collection() as &$cookie) {
            $pairs[] = (string) $cookie;
        }
        return $pairs;
    }

    /**
     * Разбирает строку с атрибутами куки на составляющие
     *
     * @todo Нужен рефакторинг, задействовать функционал защищенного метода _splitAttributeAndValue
     * @param string $raw
     * @return array
     */
    public static function parsePairs(string $raw) {
        $pairs = [];

        $pairs = explode("; ", $raw);
        $values = [];
        $first = true;
        foreach ($pairs as &$pair) {
            list($key, $val) = explode("=", $pair);
            if ($first) {
                $first = false;

                // Это пара "variable_name"="value" преобразуется в (см. нижу)
                $values["name"] = $key;
                $values["value"] = $val;
                continue;
            }
            $values[$key] = $val;
        }

        return $values;
    }

    /**
     * Экспорт коллекции в строку, удобную для чтения CURL
     *
     * @return string
     */
    public function __toString()
    {
        // TODO: Implement __toString() method.
        $arr = [];
        foreach ($this->_collection as &$item) {
            $arr[] = (string) $item;
        }
        return join("; ", $arr);
    }

    public function getHeader() {
        $header = "";

        $cookies = [];
        foreach ($this->collection() as &$cookie) {
            $cookies[] = (string) $cookie;
        }

        $header = "Cookie: " . join("; ", $cookies);

        return $header;
    }
}