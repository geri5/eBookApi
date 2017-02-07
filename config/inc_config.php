<?php

$GLOBALS['apiConfig'] = array(
    'domain' => 'http://hcxwl.com', // 本API域名，http或https开头，结尾不加/
    'static_url' => 'http://hcxwl.com/static', // public文件夹下static目录的url，以http或https开头，结尾不加/，比如http://static.xxx.com，http://xxx.com/static
    'searchByBaidu' => array(
        // 使用百度站内搜索API，请前往zhannei.baidu.com申请API（选择网页API），开通api的时候选择的域名要与配置中的domain相同
        // 查看百度给的javascript示例，找到类似这一行script.src = "http://zhannei.baidu.com/api/customsearch/apiaccept?sid=一串数字&v=小数版本号&callback=init";
        // 百度站内搜索API提交数据时数据格式选择小说，更新周期1小时，xml地址填写domain/search/mapIndex_10000.xml，domain为api的域名，10000可以替换成其他数字，代表一个xml里存多少本小说，该地址是index型xml
        'v' => '2.0', // 上述url中的参数v
        's' => '7170752674997321592', // 上述url中的参数sid
        'tk' => '527779adef892194775d298aa48b7082', // 访问上述url，找到返回的内容中：window.BCSE_TK = '一串数字字母';，这里填写这串数字字母
    ),
    // 使用迅搜XunSearch作为搜索引擎的话，需要修改config/xs_novel.ini中的端口（8383和8384修改成自己的迅搜的端口，默认是8383和8384）
    // 切换搜索引擎时需要修改config/routers.php，将需要的搜索引擎的add路由代码取消注释，将不需要的注释起来
    // 可以使用nohup & 来执行utils/xunsearch/rebuild_index.sh以每隔10分钟重建索引，或者使用crontab执行php utils/xunsearch/rebuild_index.php
    'searchByXS' => array(
        'ini' => 'xs_novel.ini', // ini文件名，存放在config目录下
    ),
);

$GLOBALS['config']['db'] = array(
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'root',
    'name' => 'novel',
);

$GLOBALS['config']['redis'] = array(
    'host' => '127.0.0.1',
    'port' => 6379,
    'pass' => '',
    'prefix' => 'eBook',
    'timeout' => 30,
);
