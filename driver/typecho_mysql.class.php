<?php

class typecho_mysql implements TpCache
{

    private static $_instance = null;
    private $mc = null;
    private $host = '127.0.0.1';
    private $port = 11211;
    private $expire = 86400;
    private $db = null;

    private function __construct($option = null)
    {
        $this->host = $option->host;
        $this->port = $option->port;
        $this->expire = $option->expire;
        $this->init($option);
    }

    static public function getInstance($option)
    {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self($option);
        }
        return self::$_instance;
    }

    public function init($option)
    {
        $this->db = Typecho_Db::get();
        $prefix = $this->db->getPrefix();
        $table_name = $prefix . 'cache';
        $sql_detect = "SHOW TABLES LIKE '%" . $table_name . "%'";

        if(count($this->db->fetchAll($sql_detect)) == 0){
            $this->install_db();
        }else{
            // 用访问触发缓存过期
            $this->db->query($this->db->delete('table.cache')->where('time <= ?', (time() - $this->expire) ));
        }
    }

    public function install_db()
    {
        $install_sql = '
DROP TABLE IF EXISTS `%prefix%cache`;
CREATE TABLE `%prefix%cache` (
  `key` char(32) NOT NULL,
  `data` text,
  `time` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=%charset%';

        $prefix = $this->db->getPrefix();
        $search = array('%prefix%', '%charset%');
        $replace = array($prefix, str_replace('UTF-8', 'utf8', Helper::options()->charset));
        $sql = str_replace($search, $replace, $install_sql);
        $sqls = explode(';', $sql);

        foreach ($sqls as $sql) {
            try{
                $this->db->query($sql);
            }catch (Typecho_Db_Exception $e){
                echo $e->getMessage();
            }
        }
    }

    public function add($key, $value, $expire = null)
    {
        $this->db->query($this->db->insert('table.cache')->rows(array(
                'key' => $key,
                'data' => $value,
                'time' => time()
            )));
    }

    public function delete($key)
    {
        return $this->db->query($this->db->delete('table.cache')->where('key = ?', $key));
    }

    public function set($key, $value, $expire = null)
    {
        $this->delete($key);
        $this->add($key, $value);
    }

    public function get($key)
    {
        $rs = $this->db->fetchRow($this->db->select('*')->from('table.cache')->where('key = ?', $key));
        if(count($rs) == 0){
            return false;
        }else{
            return $rs['data'];
        }
    }

    public function flush()
    {
        return $this->db->query($this->db->delete('table.cache'));
    }
}