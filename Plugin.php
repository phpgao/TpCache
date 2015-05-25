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
    public static $sys_config = null;
    public static $plugin_config = null;

    public static function activate()
    {
        //页面收尾
        Typecho_Plugin::factory('index.php')->begin = array('TpCache_Plugin', 'C');
        Typecho_Plugin::factory('index.php')->end = array('TpCache_Plugin', 'S');

        //页面编辑
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('TpCache_Plugin', 'update');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('TpCache_Plugin', 'update');

        //评论
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('TpCache_Plugin', 'comment_update');


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
            'memcached' => 'Memcached',
            'memcache' => 'Memcache',
            'redis' => 'Redis',
            'file' => '文件'
        );

        $element = new Typecho_Widget_Helper_Form_Element_Radio('cache_driver', $list, 'memcached', '缓存驱动');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('expire', null, '86400', '缓存过期时间', '86400 = 60s * 60m *24h 你懂不');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('host', null, '127.0.0.1', '主机地址', '主机地址你懂不');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('port', null, '11211', '端口号', '端口号你懂不');
        $form->addInput($element);

        $list = array('关闭', '开启');

        $element = new Typecho_Widget_Helper_Form_Element_Radio('is_debug', $list, 0, '是否开启debug');
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

        self::$sys_config = Helper::options();
        self::$plugin_config = self::$sys_config->plugin('TpCache');

        //获取路径信息
        $pathInfo = self::P();
        if(is_null($pathInfo)) return;


        //判断是否需要路由
        self::$key = self::needCache($pathInfo);

        //key非null则需要缓存
        if (is_null(self::$key)) return;

        self::$key = md5(self::$key);
        try {
            self::$cache = self::getCache();
            $data = self::$cache->get(self::$key);
            if ($data !== false) {
                $data = unserialize($data);
                //如果超时
                if ($data['c_time'] + self::$plugin_config->expire < time()) {
                    if(self::$plugin_config->is_debug) echo "Expired!\n";
                    $data['c_time'] = $data['c_time'] + 20;
                    self::$cache->set(self::$key, serialize($data));
                    self::$html = '';
                } else {
                    if(self::$plugin_config->is_debug) echo "Hit!\n";
                    if ($data['html']) echo $data['html'];
                    die;
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        ob_flush();

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
            $filename = "driver/$class_name.class.php";
            require_once 'driver/cache.interface.php';
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
        if (is_null(self::$key)) return;


        $html = ob_get_contents();
        if(!empty($html)){
            $data = array();
            $data['html'] = $html;
            $data['c_time'] = time();
            //更新缓存
            if(self::$plugin_config->is_debug) echo "Cache updated!\n";
            self::$cache->set(self::$key, serialize($data));
        }

    }

    /**
     * 获取PATHINFO
     * @return null|string
     */
    public static function P(){
        $req = new Typecho_Request();
        if($req->isPost()) return null;
        return $req->getPathInfo();
    }


    /**
     * 根据配置判断是否需要缓存
     * @param  string 路径信息
     * @return null|string 后置操作缓存的判断依据
     * @throws Typecho_Plugin_Exception
     */
    public static function needCache($pathInfo)
    {
        //后台数据不缓存
        $pattern = '#^' . __TYPECHO_ADMIN_DIR__ . '#i';
        if( preg_match($pattern, $pathInfo) ) return null;

        //action不缓存
        $pattern = '#^/action#i';
        if( preg_match($pattern, $pathInfo) ) return null;

        $_routingTable = self::$sys_config->routingTable;

        $exclude = array('_year', '_month', '_day', '_page');

        foreach ($_routingTable[0] as $key => $route) {
            if ($route['widget'] != 'Widget_Archive') continue;

            if (preg_match($route['regx'], $pathInfo, $matches)) {
                $key = str_replace($exclude, '', str_replace($exclude, '', $key));

                if (in_array($key, self::$plugin_config->cache_page)) {
                    if(self::$plugin_config->is_debug) echo "This page needs to be cached!\n" . '
<a href="http://www.phpgao.com/tpcache_for_typecho.html" target="_blank"> Bug Report </a>';
                    return $pathInfo;
                }
            }
        }

        return null;
    }

    /**
     * 编辑文章后更新缓存
     * @param $contents
     * @param $class
     *
     */
    public static function update($contents, $class)
    {


        if ('publish' != $contents['visibility'] || $contents['created'] > time()) {
            return;
        }
        //获取系统配置
        $options = Helper::options();
        //获取文章类型
        $type = $contents['type'];
        //获取路由信息
        $routeExists = (NULL != Typecho_Router::get($type));
        //生成永久连接
        $path_info = $routeExists ? Typecho_Router::url($type, $contents) : '#';

        $key = self::needCache($path_info);

        if(is_null($key)) return;

        //删除缓存
        self::delete_path($key);
    }

    /**
     * 评论更新
     *
     * @access public
     * @param array $comment 评论结构
     * @param Typecho_Widget $post 被评论的文章
     * @param array $result 返回的结果上下文
     * @param string $api api地址
     * @return void
     */
    public static function comment_update($comment){
        $req = new Typecho_Request();
        $root = $req->getRequestRoot();
        $referer = $req->getReferer();
        $key =str_replace($root, '', $referer);
        self::delete_path($key);
    }

    /**
     * 删除指定key
     * @param $key path_info
     * @param null $home 是否删除首页缓存
     */
    public static function delete_path($key, $home=null){
        $keys = array();
        if(!is_array($key)){
            $keys[] = $key;
        }else{
            $keys = $key;
        }

        $cache = self::getCache();
        foreach ($keys as $v) {
            @$cache->delete(md5($v));
        }

        if(is_null($home)) @$cache->delete(md5('/'));
    }


}