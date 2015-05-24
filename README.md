# TpCache
 A typecho plugin for cache

## 功能

减缓网站并发压力而开发的缓存插件。

## 注意

1. 测试版，不稳定
1. 目前仅支持memcache，后续会有更多驱动支持，如数据库、redis、文件缓存。
1. 非js方式的访问统计插件会失效
1. BUG请在[缓存插件TpCache for Typecho][1]页汇报

## 使用

请配置好memcache和php的memcached扩展

## 安装

请将文件夹**重命名**为TpCache。再拷贝至`usr/plugins/下`


## 升级

请先**禁用此插件**后再升级，很多莫名其妙的问题都是因为没有先禁用而直接升级导致的！


  [1]: http://www.phpgao.com/tpcache_for_typecho.html