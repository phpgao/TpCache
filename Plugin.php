<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho缓存插件
 *
 * @package TpCache
 * @author 老高
 * @version 0.7
 * @link http://www.phpgao.com
 */
class TpCache_Plugin implements Typecho_Plugin_Interface
{
    public static $key = null;
    public static $cache = null;
    public static $html = null;
    public static $path = null;
    public static $sys_config = null;
    public static $plugin_config = null;
    public static $request = null;

    public static function activate()
    {
        //页面收尾
        Typecho_Plugin::factory('index.php')->begin = array('TpCache_Plugin', 'C');
        Typecho_Plugin::factory('index.php')->end = array('TpCache_Plugin', 'S');

        //页面编辑
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('TpCache_Plugin', 'post_update');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('TpCache_Plugin', 'post_update');

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
        try {
            $uninstall_sql = 'DROP TABLE IF EXISTS `%prefix%cache`';
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $sql = str_replace('%prefix%', $prefix, $uninstall_sql);
            $db->query($sql);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
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
        $element = new Typecho_Widget_Helper_Form_Element_Checkbox('cache_page', $list, array('index', 'post', 'search', 'page', 'author', 'tag'), '需要缓存的页面');
        $form->addInput($element);

        $list = array('关闭', '开启');
        $element = new Typecho_Widget_Helper_Form_Element_Radio('login', $list, 1, '是否对已登录用户失效', '已经录用户不会触发缓存策略');
        $form->addInput($element);

        $list = array('关闭', '开启');
        $element = new Typecho_Widget_Helper_Form_Element_Radio('enable_ssl', $list, '0', '是否支持SSL');
        $form->addInput($element);

        $list = array(
            '0' => '不使用缓存',
            'memcached' => 'Memcached',
            'memcache' => 'Memcache',
            'redis' => 'Redis',
            'mysql' => 'Mysql'
        );
        $element = new Typecho_Widget_Helper_Form_Element_Radio('cache_driver', $list, '0', '缓存驱动');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('expire', null, '86400', '缓存过期时间', '86400 = 60s * 60m *24h，即一天的秒数');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('host', null, '127.0.0.1', '主机地址', '主机地址，一般为127.0.0.1');
        $form->addInput($element);

        $element = new Typecho_Widget_Helper_Form_Element_Text('port', null, '11211', '端口号', '端口号，memcache对应11211，Redis对应6379，其他类型随意填写');
        $form->addInput($element);

        $list = array('关闭', '开启');
        $element = new Typecho_Widget_Helper_Form_Element_Radio('is_debug', $list, 0, '是否开启debug');
        $form->addInput($element);

        $list = array('关闭', '清除所有数据');
        $element = new Typecho_Widget_Helper_Form_Element_Radio('is_clean', $list, 0, '清除所有数据');
        $form->addInput($element);
    }

    /**
     * 手动保存配置句柄
     * @param $config array 插件配置
     * @param $is_init bool 是否初始化
     */
    public static function configHandle($config, $is_init)
    {
        if ($is_init != true && $config['cache_driver'] != '0') {

            $driver_name = $config['cache_driver'];
            $class_name = "typecho_$driver_name";
            $file_path = "driver/$class_name.class.php";
            require_once 'driver/cache.interface.php';
            require_once $file_path;
            self::$cache = call_user_func(array($class_name, 'getInstance'), self::$plugin_config);
            try {
                if ($config['is_clean'] == '1') self::$cache->flush();
            } catch (Exception $e) {
                print $e->getMessage();
                die;
            }
            // 删除缓存仅生效一次
            $config['is_clean'] = '0';
        }

        Helper::configPlugin('TpCache', $config);
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
        $start = microtime(true);
        // 插件初始化
        if (!self::init()) return false;
        // 前置条件检查
        if (!self::pre_check()) return false;



        try {
            $data = self::get(self::$path);
            if ($data != false) {
                $data = unserialize($data);
                //如果超时
                if ($data['c_time'] + self::$plugin_config->expire <= time()) {

                    if (self::$plugin_config->is_debug) echo "Expired!\n";
                    $data['c_time'] = $data['c_time'] + 20;
                    self::set(self::$path, serialize($data));
                } else {
                    if (self::$plugin_config->is_debug) echo "Hit!\n";
                    if ($data['html']) echo $data['html'];
                    $end = microtime(true);
                    $time = number_format(($end - $start), 6);
                    if (self::$plugin_config->is_debug) echo 'This page loaded in ', $time, ' seconds';
                    die;
                }
            } else {
                if (self::$plugin_config->is_debug) echo "Can't find cache!";
            }

        } catch (Exception $e) {
            echo $e->getMessage();
        }
        // 先进行一次刷新
        ob_flush();

    }

    /**
     * 前置检查
     * @return bool
     */
    public static function pre_check()
    {
        //对登录用户失效
        if (self::check_login()) return false;
        //针对POST失效
        if (self::$request->isPost()) return false;
        //是否支持SSL
        if (self::$plugin_config->enable_ssl == '0' && self::$request->isSecure() == true) return false;
        return true;
    }

    /**
     * 判断用户是否登录
     * @return bool
     * @throws Typecho_Widget_Exception
     */
    public static function check_login()
    {
        //http与https相互独立
        return (self::$plugin_config->login && Typecho_Widget::widget('Widget_User')->hasLogin());
    }

    /**
     * 根据配置判断是否需要缓存
     * @param string 路径信息
     * @return bool
     */
    public static function needCache($path)
    {
        //后台数据不缓存
        $pattern = '#^' . __TYPECHO_ADMIN_DIR__ . '#i';
        if (preg_match($pattern, $path)) return false;

        //action动作不缓存
        $pattern = '#^/action#i';
        if (preg_match($pattern, $path)) return false;

        $_routingTable = self::$sys_config->routingTable;

        $exclude = array('_year', '_month', '_day', '_page');

        foreach ($_routingTable[0] as $key => $route) {
            if ($route['widget'] != 'Widget_Archive') continue;

            if (preg_match($route['regx'], $path, $matches)) {
                $key = str_replace($exclude, '', str_replace($exclude, '', $key));

                if (in_array($key, self::$plugin_config->cache_page)) {
                    if (self::$plugin_config->is_debug) echo "This page needs to be cached!\n" . '
<a href="http://www.phpgao.com/tpcache_for_typecho.html" target="_blank"> Bug Report </a>';
                    self::$path = $path;
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * 缓存后置操作
     */
    public static function S()
    {
        //对登录用户失效
        if (self::check_login()) return;

        //若self::$key不为空，则使用缓存
        if (is_null(self::$key)) return;


        $html = ob_get_contents();

        if (!empty($html)) {
            $data = array();
            $data['c_time'] = time();
            $data['html'] = $html;
            //更新缓存
            if (self::$plugin_config->is_debug) echo "Cache updated!\n";
            self::set(self::$key, serialize($data));
        }

    }


    /**
     * 编辑文章后更新缓存
     * @param $contents
     * @param $class
     */
    public static function post_update($contents, $class)
    {
        if ('publish' != $contents['visibility'] || $contents['created'] > time()) {
            return;
        }
        //获取系统配置
        $options = Helper::options();

        if(!$options->plugin('TpCache')->cache_driver){
            return;
        }
        //获取文章类型
        $type = $contents['type'];
        //获取路由信息
        $routeExists = (NULL != Typecho_Router::get($type));

        if (!is_null($routeExists)) {
            $db = Typecho_Db::get();
            $contents['cid'] = $class->cid;
            $contents['categories'] = $db->fetchAll($db->select()->from('table.metas')
                ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $contents['cid'])
                ->where('table.metas.type = ?', 'category')
                ->order('table.metas.order', Typecho_Db::SORT_ASC));
            $contents['category'] = urlencode(current(Typecho_Common::arrayFlatten($contents['categories'], 'slug')));
            $contents['slug'] = urlencode($contents['slug']);
            $contents['date'] = new Typecho_Date($contents['created']);
            $contents['year'] = $contents['date']->year;
            $contents['month'] = $contents['date']->month;
            $contents['day'] = $contents['date']->day;
        }

        //生成永久连接
        $path_info = $routeExists ? Typecho_Router::url($type, $contents) : '#';

        if (self::init($path_info)) self::delete($path_info);
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
    public static function comment_update($comment)
    {
        $req = new Typecho_Request();
        // 获取评论的PATH_INFO
        $path_info = $req->getPathInfo();
        // 删除最后的 /comment就是需删除的key
        $article_url = preg_replace('/\/comment$/i','',$path_info);

        self::init($article_url);
        
        self::delete($article_url);
    }

    /**
     * 插件配置初始化
     * @param $pathInfo
     * @return bool
     * @throws Typecho_Plugin_Exception
     */
    public static function init($pathInfo='')
    {
        if (is_null(self::$sys_config)) {
            self::$sys_config = Helper::options();
        }
        if (is_null(self::$plugin_config)) {
            self::$plugin_config = self::$sys_config->plugin('TpCache');
        }

        if (self::$plugin_config->cache_driver == '0') {
            return false;
        }

        if(empty($pathInfo)){

            if (is_null(self::$request)) {
                self::$request = new Typecho_Request();
            }

            //获取路径信息
            $pathInfo = self::$request->getPathInfo();

        }
        //判断是否需要缓存
        if (!self::needCache($pathInfo)) return false;

        self::init_driver();

        return true;
    }

    /**
     * 插件驱动初始化
     * @return bool
     * @throws Typecho_Plugin_Exception
     */
    public static function init_driver(){
        if (is_null(self::$cache)) {
            $driver_name = self::$plugin_config->cache_driver;
            $class_name = "typecho_$driver_name";
            $file_path = "driver/$class_name.class.php";
            require_once 'driver/cache.interface.php';
            require_once $file_path;
            self::$cache = call_user_func(array($class_name, 'getInstance'), self::$plugin_config);
        }
    }


    public static function set($path, $data)
    {

        if (!is_null(self::$key)) return self::$cache->set(self::$key, $data);
        $prefix = self::$request->getUrlPrefix();
        self::$key = md5($prefix . $path);

        return self::$cache->set(self::$key, $data);
    }

    public static function add($path, $data)
    {

    }

    public static function get($path)
    {
        if (!is_null(self::$key)) return self::$cache->get(self::$key);
        $prefix = self::$request->getUrlPrefix();
        self::$key = md5($prefix . $path);
        return self::$cache->get(self::$key);
    }

    /**
     * 删除指定路径
     * @param string $path 待删除路径
     * @param null $del_home 是否删除首页缓存
     */
    public static function delete($path, $del_home = null)
    {
        $prefixs = array(
            'http'
            . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] :
                ($_SERVER['SERVER_NAME'] . (in_array($_SERVER['SERVER_PORT'], array(80, 443))
                        ? '' : ':' . $_SERVER['SERVER_PORT']))
            ),
            'https'
            . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] :
                ($_SERVER['SERVER_NAME'] . (in_array($_SERVER['SERVER_PORT'], array(80, 443))
                        ? '' : ':' . $_SERVER['SERVER_PORT']))
            ),
        );
        $keys = array();
        if (!is_array($path)) {
            $keys[] = $path;
        } else {
            $keys = $path;
        }


        foreach ($keys as $v) {
            foreach ($prefixs as $prefix) {
                echo $prefix . $v;
                @self::$cache->delete(md5($prefix . $v));
            }
        }

        if (is_null($del_home)) {
            foreach ($prefixs as $prefix) {
                echo $prefix . '/';
                @self::$cache->delete(md5($prefix . '/'));
            }
        }
    }
}
