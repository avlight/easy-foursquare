<?php

namespace Lib;

class Cookie
{
    protected $_name = null;
    protected $_value = null;
    protected $_domain = null;
    protected $_expires = null;
    protected $_path = null;
    protected $_secure = null;
    protected $_httpOnly = null;

    public function __construct(
        string $name = null,
        string $value = null,
        string $domain = null,
        int $expire = 0,
        string $path = "/",
        bool $secure = false,
        bool $httpOnly = true
    )
    {
        if (!empty($name)) $this->set($name, $value, $domain, $expire, $path, $secure, $httpOnly);
    }

    /**
     * Установить куку
     *
     * @param string $name
     * @param string $value
     * @param string|null $domain
     * @param int $expire
     * @param string $path
     * @param bool $secure
     * @param bool $httpOnly
     */
    public function set(
        string $name,
        string $value,
        string $domain = null,
        int $expire = 0,
        string $path = "/",
        bool $secure = false,
        bool $httpOnly = true
    ) {
        $this->_name = $name;
        $this->_value = $value;
        $this->_domain = $domain;
        $this->_expires = $expire;
        $this->_path = $path;
        $this->_secure = $secure;
        $this->_httpOnly = $httpOnly;
    }



    /**
     * Получить параметр куки
     *
     * @param string $name
     * @param bool $toString
     * @return mixed|null|string
     */
    public function get(string $name) {
        $field = "_{$name}";
        return isset($this->$field) ? $this->$field : null;
    }

    /**
     * Установить значение атрибута куки
     *
     * @param string $name
     * @param $value
     * @return bool
     */
    public function setAttribute(string $name, $value) {
        $field = "_" . $name;
        $this->$field = $value;
        return true;
    }

    /**
     * Установить или получить значение атрибута куки.
     *
     * @param $name
     * @param array $arguments
     */
    public function __call($name, array $arguments = [])
    {
        // TODO: Implement __call() method.
        $set = false;
        $field = "_" . $name;
        if (!empty($arguments) && property_exists(__CLASS__, $field)) {
            $this->$field = $arguments[0];
        } else if (count($arguments) > 1) {
            // TODO: throw exception
        }

        $this->get($name);
    }

    /**
     * Экспортировать куки
     *
     * @param bool $toString
     * @return array|string
     */
    public function export(bool $toString = false) {
        if ($toString) return $this->exportString();
        return $this->_params;
    }

    /**
     * Экспортировать куку как строку.
     *
     * @todo Уточнить правильный формат куки в текстовом виде (это дополнительный функционал)
     * @return string
     */
    public function exportString($nameValueOnly = true) {

        if ($nameValueOnly) {
            return $this->_exportNameValueOnly();
        }

        $join = [
            $this->_exportNameValueOnly(),
            $this->_expires,
            $this->_path,
            $this->_domain,
        ];

        return join('; ', $join);
    }

    protected function _exportNameValueOnly() {
        return $this->_name . "=" . $this->_value;
    }

    /**
     * Экспорт куки в строку
     *
     * @return string
     */
    public function __toString()
    {
        // Implement __toString() method.
        return $this->exportString(true);
    }


}