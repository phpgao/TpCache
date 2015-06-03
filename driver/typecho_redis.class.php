<?php

class typecho_redis implements TpCache{

    private static $_instance = null;
    private $redis = null;
    private $host = '127.0.0.1';
    private $port = 11211;
    private $expire = 86400;

    private function __construct($option=null) {
        $this->host = $option->host;
        $this->port = $option->port;
        $this->expire = $option->expire + 0;
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
        try{
            $this->redis = new Redis();
            $this->redis->connect($this->host, $this->port);
        }catch (Exception $e){
            echo $e->getMessage();
        }
    }

    public function add($key, $value, $expire=null)
    {
        return $this->redis->set($key, $value, is_null($expire) ? $this->expire : $expire);
    }

    public function delete($key)
    {
        return $this->redis->delete($key);
    }

    public function set($key, $value, $expire=null)
    {
        return $this->redis->set($key, $value, is_null($expire) ? $this->expire : $expire);
    }

    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function flush()
    {
        return $this->redis->flushDB();
    }
}