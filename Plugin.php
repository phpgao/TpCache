<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho缓存插件
 *
 * @package TpCache
 * @author 老高
 * @version 0.1
 * @link http://www.phpgao.com
 */
class TpCache_Plugin implements Typecho_Plugin_Interface
{
    public static $cache = null;
    public static $html = null;
    public static $key = null;

    public static function activate()
    {
        Typecho_Plugin::factory('index.php')->begin = array('TpCache_Plugin', 'C');
        Typecho_Plugin::factory('index.php')->end = array('TpCache_Plugin', 'S');
        return '插件安装成功,请设置需要缓存的页面';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $list = array(
            'index' => '首页',
            'archive' => '归档',
            'post' => '文章',
            'attachment' => '附件',
            'category' => '分类',
            'tag' => '标签',
            'author' => '作者',
            'search' => '搜索',
            'feed' => 'feed',
            'page' => '页面',
        );

        $element = new Typecho_Widget_Helper_Form_Element_Checkbox('cache_page', $list, array('index', 'post'), '需要缓存的页面');
        $form->addInput($element);

        $list = array(
            'memcache' => 'Memcache',
            'redis' => 'Redis',
            'file' => '文件'
        );

        $element = new Typecho_Widget_Helper_Form_Element_Radio('cache_driver', $list, 'memcache', '缓存驱动');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('host', null, '127.0.0.1', '主机地址', '主机地址你懂不');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('port', null, '11211', '端口号', '端口号你懂不');
        $form->addInput($element);

        $list = array('关闭', '开启');

        $element = new Typecho_Widget_Helper_Form_Element_Radio('is_debug', $list, 1, '是否开启debug');
        $form->addInput($element);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 缓存前置操作
     */
    public static function C()
    {
        self::$key = self::needCache();

        //key非null则需要缓存
        if (!is_null(self::$key)) {
            try {
                self::$cache = self::getCache();
                $data = self::$cache->get(self::$key);
                if ($data !== false) {
                    $data = unserialize($data);

                    //如果超时
                    if ($data['c_time'] + 4 < time()) {
                        if(Helper::options()->plugin('TpCache')->is_debug) echo "Expired!\n";
                        $data['c_time'] = $data['c_time'] + 1;
                        self::$cache->set(self::$key, serialize($data));
                        self::$html = '';
                    } else {
                        if(Helper::options()->plugin('TpCache')->is_debug) echo "Hit!\n";
                        if ($data['html']) echo $data['html'];
                        die;
                    }
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * 从驱动中获取缓存
     * @return null
     * @throws Typecho_Plugin_Exception
     */
    public static function getCache()
    {
        if (is_null(self::$cache)) {
            $init_options = Helper::options()->plugin('TpCache');
            $driver_name = $init_options->cache_driver;
            $class_name = "typecho_$driver_name";
            $filename = "driver/$driver_name.class.php";
            require_once 'cache.interface.php';
            require_once $filename;
            self::$cache = $class_name::getInstance($init_options);
        }
        return self::$cache;
    }

    /**
     * 缓存后置操作
     */
    public static function S()
    {
        //若self::$key不为空，则使用缓存
        if (!is_null(self::$key)) {
            $html = ob_get_contents();
            if(!empty($html)){
                $data = array();
                $data['html'] = $html;
                $data['c_time'] = time();
                //更新缓存
                if(Helper::options()->plugin('TpCache')->is_debug) echo "Cache updated!\n";
                self::$cache->set(self::$key, serialize($data));
            }
        }
    }


    /**
     * 根据配置判断是否需要缓存
     * @return null|string 后置操作缓存的判断依据
     * @throws Typecho_Plugin_Exception
     */
    public static function needCache()
    {

        /** 获取PATHINFO */
        $req = new Typecho_Request();
        $pathInfo = $req->getPathInfo();
        //var_dump($_SERVER);

        $option = Helper::options();
        $_routingTable = $option->routingTable;

        $exclude = array('_year', '_month', '_day', '_page');

        foreach ($_routingTable[0] as $key => $route) {
            if ($route['widget'] != 'Widget_Archive') continue;

            if (preg_match($route['regx'], $pathInfo, $matches)) {
                $key = str_replace($exclude, '', str_replace($exclude, '', $key));

                if (in_array($key, $option->plugin('TpCache')->cache_page)) {
                    if(Helper::options()->plugin('TpCache')->is_debug) echo "This page needs to be cached!\n";
                    return md5($pathInfo);
                }
            }
        }

        return null;
    }


}