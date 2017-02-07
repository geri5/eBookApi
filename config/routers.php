<?php

$api = substr($api, 0, 1) == '/' ? substr($api, 1) : $api; // 如果api前面有/就去除，防止apache，nginx不同

router::group(array(
    'index' => function () {
        response::make('ENOVEL OPEN API');
    },
    'book/getBookInfo' => array('book', 'getBookInfo'),
    'book/getBookSource' => array('book', 'getBookSource'),
    'book/getChapterList' => array('book', 'getChapterList'),
    'book/getChapterContent' => array('book', 'getChapterContent'),
    'sort/getAllSorts' => array('sort', 'getAllSorts'),
    'sort/getBooksBySort' => array('sort', 'getBooksBySort'),
    // 'search' => array('search', 'searchByBaidu'), // 使用百度站内搜索API引擎
    // 'search/suggest' => array('search', 'suggestByBaidu'), // 使用百度的搜索建议
    'search' => array('search', 'searchByXS'), // 使用XunSearch全文检索引擎
    'search/suggest' => array('search', 'suggestByXS'), // 使用XunSearch的搜索建议
));

router::group(array(
    '@^search/map_(\d+)_(\d+)\.xml$@' => array('search', 'baiduSearchMap'),
    '@^search/mapIndex_(\d+)\.xml$@' => array('search', 'baiduSearchMapIndex'),
), 'reg');
