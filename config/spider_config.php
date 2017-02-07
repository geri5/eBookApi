<?php
/**
 * 这里是爬虫的一些基本设置
 * Mysql和Redis设置在inc_config.php
 * 可以单独为规则制定配置，在返回的数组中加成员：规则名（规则文件中的ruleName），例如23us，未指定的规则使用default配置
 * 除log_write外与PHPSpider的配置项相同
 */
$configs = array(
    'default' => array(
        'log_show' => false,
        'log_write' => false, //是否写出info类型日志到文件
        'tasknum' => 5, //进程数，windows下无效
        'output_encoding' => 'UTF-8',
        'use_redis' => true,
        'max_try' => 3, //请求失败最大重试数
        'wait_time' => 60, //采集完一轮后休息多少秒再继续
    ),
    'biquge' => array(
        'log_show' => false,
        'log_write' => false,
        'tasknum' => 15,
        'output_encoding' => 'UTF-8',
        'use_redis' => true,
        'max_try' => 5,
        'wait_time' => 5,
    ),
);
