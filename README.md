## 功能

减缓网站并发压力而开发的缓存插件。

## 注意

1. 支持**Memcache**，**Redis**，**Mysql**三种驱动。
1. **非js方式的**访问统计插件会失效
1. BUG请在[缓存插件TpCache for Typecho][1]页汇报


<!--more-->


## 使用说明

### 后台设置

![后台设置截图][2]

### 组件支持

**请确保你的服务器memcache套件工作正常。**

目前老高提供了php**memcache**与**memcached**的支持，请选择对应的驱动。

memcached配置请参考[Linux服务器配置memcached并启用PHP支持][3]。

Redis配置请参考[Linux服务器配置Redis并启用PHP支持][4]。

### 缓存更新机制

**目前以下操作会触发缓存更新**

- 来自原生评论系统的评论
- 后台文章或页面更新
- 重启memcached
- 缓存到期

### 评论

原生评论简单测试过，没有大问题。

不过既然使用缓存了不如直接使用第三方评论系统，如多说。

## 性能

在老高的烂主机上随便就能跑到保守800的并发(CPU占用不到70%)，什么概念呢？

理论上支持每天**69120000**(60\*60\*24\*800)的PV。

## 下载

<gb user="phpgao" type="download" count="1" size="1" width="200"> TpCache </gb>

<gb user="phpgao" type="star" count="1" size="1" width="200"> TpCache </gb>

## 安装

请将文件夹**重命名**为TpCache。再拷贝至`usr/plugins/下`。

## 升级

请先**禁用此插件**后再升级，很多莫名其妙的问题都是因为没有先禁用而直接升级导致的！


  [1]: http://www.phpgao.com/tpcache_for_typecho.html
  [2]: http://www.phpgao.com/usr/uploads/2015/05/3901966986.jpeg
  [3]: http://www.phpgao.com/php-memcached-extension-installation.html
  [4]: http://www.phpgao.com/redis_php.html