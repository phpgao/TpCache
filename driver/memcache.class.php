<?php

class typecho_memcache implements TpCache{

    private static $_instance = null;
    private $mc = null;
    private $host = '127.0.0.1';
    private $port = 11211;

    private function __construct($option=null) {
        $this->host = $option->host;
        $this->port = $option->port;
        $this->init($option);
    }

    static public function getInstance($option) {
        if (is_null ( self::$_instance ) || isset ( self::$_instance )) {
            self::$_instance = new self($option);
        }
        return self::$_instance;
    }

    public function init($option)
    {
        $this->mc = memcache_connect($this->host, $this->port);
    }

    public function add($key, $value)
    {
        return $this->mc->add($key, $value);
    }

    public function del($key)
    {
        return $this->mc->del($key);
    }

    public function set($key, $value)
    {
        return $this->mc->set($key, $value);
    }

    public function get($key)
    {
        return $this->mc->get($key);
    }

}