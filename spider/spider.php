<?php
require __DIR__ . '/../core/spider_init.php';
$ruleDir = __DIR__ . '/rules/';
if (!CFile::_mkdir($ruleDir)) {
    //_mkdir如果已存在目录则直接返回true，不存在则创建
    log::_echo("创建rules目录失败！\n");
    exit;
}
/********* 选择规则 *********/
while (true) {
    $ruleFiles = CFile::_scandir($ruleDir, '', array('xml')); //每次都重新扫描一次规则文件
    if (empty($ruleFiles)) {
        log::_echo("扫描rules目录失败或未发现规则文件！是否重新扫描（Y/N）\n");
        while (true) {
            switch (strtolower(trim(fgets(STDIN)))) {
                case 'y':
                    if (!is_dir($ruleDir) && !CFile::_mkdir($ruleDir)) {
                        log::_echo("创建rules目录失败！\n");
                        exit;
                    }
                    continue 2;
                case 'n':
                    exit;
                default:
                    log::_echo("是否重新扫描（Y/N）\n");
                    break;
            }
        }
    }
    log::_echo("请输入欲使用的规则的数字序号，规则文件存放在rules目录中：\n");
    foreach ($ruleFiles as $key => $name) {
        log::_echo($key . ". {$name}\n");
    }
    $ruleId = trim(fgets(STDIN));
    $ruleId = is_numeric($ruleId) ? intval($ruleId) : -1; //防止输入字符串intval取值为0
    $rulePath = isset($ruleFiles[$ruleId]) ? str_replace('\\', '/', $ruleDir . $ruleFiles[$ruleId]) : '';
    if (is_file($rulePath)) {
        while (true) {
            log::_echo("请输入欲选择的采集模式的数字序号：\n0. 按小说ID采集\n");
            $cMode = trim(fgets(STDIN));
            $cMode = is_numeric($cMode) ? intval($cMode) : -1;
            switch ($cMode) {
                case 0:
                    log::_echo("请输入欲采集的起始小说ID：\n");
                    $startId = intval(fgets(STDIN)); //若为非数字则取0，因为直接intval所以不用trim
                    log::_echo("请输入欲采集的结束小说ID：\n");
                    $endId = intval(fgets(STDIN));
                    if ($endId < $startId) {
                        $endId = $startId;
                    }
                    log::_echo("当前采集配置：\n规则：");
                    echo $ruleFiles[$ruleId]; //这里是与系统文件名编码一致的，不用转换编码
                    log::_echo("\n采集模式：按小说ID采集\n起始ID：{$startId}\n结束ID：{$endId}\n请确认是否正确（Y/N）\n");
                    while (true) {
                        switch (strtolower(trim(fgets(STDIN)))) {
                            case 'y':
                                break 5;
                            case 'n':
                                break 4;
                            default:
                                log::_echo("请确认是否正确（Y/N）\n");
                                break;
                        }
                    }
                default:
                    log::_echo("采集模式有误！\n");
                    break;
            }
        }
    } else {
        log::_echo("不存在该规则！\n");
    }
}
$rule = simplexml_load_file($rulePath);
unset($rulePath);
unset($ruleDir);
unset($ruleId);
unset($ruleFiles);
/********* 规则处理 *********/
$infoRule = $rule->infoRule;
$listRule = $rule->listRule;
$sourceName = (string)$rule->source;
if ($sourceName == '') {
    log::_echo('规则有误，source为空！');
    exit;
}
/********* 爬虫部分 *********/
$sConfig = isset($configs[$sourceName]) ? $configs[$sourceName] : $configs['default'];
$waitTime = isset($sConfig['wait_time']) && $sConfig['wait_time'] > 0 ? $sConfig['wait_time'] : 0;
unset($sConfig['wait_time']);
unset($configs);
$sConfig['name'] = $sourceName;
$sConfig['site_name'] = (string)$rule->siteName;
$sConfig['domains'] = explode('|||', $rule->siteDomain);
if ((string)$rule->encoding != '') {
    $sConfig['input_encoding'] = (string)$rule->encoding;
}
$sConfig['output_encoding'] = 'utf-8';
for ($id = $startId; $id <= $endId; $id++) {
    $sConfig['scan_urls'][] = str_replace(array('{novelId}', '{novelId/1000}'), array($id, intval($id / 1000)), $rule->infoUrl);
}
$spider = new phpspider($sConfig);
unset($sConfig);
unset($rule);
unset($startId);
unset($endId);
$spider->on_status_code = function ($status_code, $url, $content, $spider) {
    // 如果状态码不是2xx,3xx判断为当前页面请求失败
    $s = substr($status_code, 0, 1);
    if ($s == '2' || $s == '3') {
        return $content;
    } else {
        return false;
    }
};
$spider->on_scan_page = function ($page, $content, $spider) use ($sourceName, $infoRule) {
    $novel['book_title'] = selector::select($content, $infoRule->bookTitle->rule, $infoRule->bookTitle->type);
    $novel['book_author'] = selector::select($content, $infoRule->bookAuthor->rule, $infoRule->bookAuthor->type);
    $row = db::get_one("select `id`,`book_cover`,`book_intro`,`book_status` from `enovel_books` where `book_title` = '{$novel['book_title']}' and `book_author` = '{$novel['book_author']}' limit 1");
    if (is_null($row)) {
        return false;
    } elseif (count($row)) {
        // 已经存在这本书
        $bookId = $row['id'];
        $update = array();
        if (empty($row['book_cover']) || !file_exists(__DIR__ . '/../public/static/cover/' . $row['book_cover'])) {
            $update['book_cover'] = selector::select($content, $infoRule->bookCover->rule, $infoRule->bookCover->type);
            $update['book_cover'] = empty($update['book_cover']) || strpos($update['book_cover'], $infoRule->bookCover->default) !== false
                ? '' : $spider->fill_url($update['book_cover'], $page['request']['url']);
            if (!empty($update['book_cover'])) {
                $savePath = CFile::download($update['book_cover'], __DIR__ . '/../public/static/cover/' . util::get_hash($novel['book_title'], 100) . '/' . md5($novel['book_title'] . $novel['book_author']), true, false, false);
                if ($savePath) {
                    $update['book_cover'] = str_replace(__DIR__ . '/../public/static/cover/', '', $savePath);
                } else {
                    $update['book_cover'] = '';
                }
            }
        }
        if (empty($row['book_intro'])) {
            $update['book_intro'] = selector::select($content, $infoRule->bookIntro->rule, $infoRule->bookIntro->type);
            if (!empty($infoRule->bookIntro->replace)) {
                $rms = explode('|||', $infoRule->bookIntro->replace);
                foreach ($rms as $rm) {
                    $r = explode('-->', $rm);
                    $patterns[] = $r[0];
                    $replacements[] = isset($r[1]) ? $r[1] : ''; // 如果不存在-->那么默认为替换成空
                }
                $update['book_intro'] = preg_replace($patterns, $replacements, $update['book_intro']);
            }
            $update['book_intro'] = util::space_html2text($update['book_intro']); // 先根据规则中replace进行替换，再space_html2text
        }
        if ($row['book_status'] == 0) {
            $update['book_status'] = selector::select($content, $infoRule->bookStatus->rule, $infoRule->bookStatus->type);
            $update['book_status'] = $update['book_status'] == $infoRule->bookStatus->endWord ? 1 : 0;
        }
        // 暂时先不更新分类了
        $update = array_filter($update); // 过滤下
        if (!empty($update)) {
            db::update('enovel_books', $update, array("id = '{$bookId}' limit 1")); // 更新，这里不判断是否成功
        }
    } else {
        // 不存在这本书
        $novel['book_cover'] = selector::select($content, $infoRule->bookCover->rule, $infoRule->bookCover->type);
        $novel['book_status'] = selector::select($content, $infoRule->bookStatus->rule, $infoRule->bookStatus->type);
        $novel['book_sort'] = selector::select($content, $infoRule->bookSort->rule, $infoRule->bookSort->type);
        $novel['book_intro'] = selector::select($content, $infoRule->bookIntro->rule, $infoRule->bookIntro->type);
        if (empty($novel['book_title'])
            || empty($novel['book_author'])
            || empty($novel['book_status'])
            || empty($novel['book_sort'])
        ) {
            // 除了封面和简介都不能为空
            return false;
        }
        $novel['book_cover'] = empty($novel['book_cover']) || strpos($novel['book_cover'], $infoRule->bookCover->default) !== false
            ? '' : $spider->fill_url($novel['book_cover'], $page['request']['url']);
        $novel['book_status'] = $novel['book_status'] == $infoRule->bookStatus->endWord ? 1 : 0; // 1为已完结，0为连载中
        if (!empty($infoRule->bookIntro->replace)) {
            $rms = explode('|||', $infoRule->bookIntro->replace);
            foreach ($rms as $rm) {
                $r = explode('-->', $rm);
                $patterns[] = $r[0];
                $replacements[] = isset($r[1]) ? $r[1] : '';
            }
            $novel['book_intro'] = preg_replace($patterns, $replacements, $novel['book_intro']);
        }
        $novel['book_intro'] = util::space_html2text($novel['book_intro']);
        $sortList = explode('|||', $infoRule->bookSort->sortList);
        foreach ($sortList as $sort) {
            $s = explode('-->', $sort);
            if ($novel['book_sort'] == $s[0] && isset($s[1]) && $s[1] != '') {
                $bookSort = $s[1];
                break;
            } else {
                $bookSort = '其他类型';
            }
        }
        $novel['book_sort'] = $bookSort;
        $row = db::get_one("select `id` from `enovel_sorts` where `name` = '{$novel['book_sort']}' limit 1");
        if (is_null($row)) {
            return false;
        } elseif (count($row)) {
            $novel['book_sort'] = $row['id'];
        } else {
            $insertId = db::insert('enovel_sorts', array('name' => $novel['book_sort']));
            if ($insertId) {
                // id从1开始，如果insert失败则insertId为false，否则>=1
                $novel['book_sort'] = $insertId;
            } else {
                return false;
            }
        }
        if (!empty($novel['book_cover'])) {
            $savePath = CFile::download($novel['book_cover'], __DIR__ . '/../public/static/cover/' . util::get_hash($novel['book_title'], 100) . '/' . md5($novel['book_title'] . $novel['book_author']), true, false, false);
            if ($savePath) {
                $novel['book_cover'] = str_replace(__DIR__ . '/../public/static/cover/', '', $savePath); // 入库的封面路径，相对于__DIR__ . '/../public/static/cover/'
            } else {
                $novel['book_cover'] = '';
            }
        }
        $novel['post_time'] = time();
        $bookId = db::insert('enovel_books', $novel);
        if (!$bookId) {
            return false;
        }
    }
    if ((string)$infoRule->listUrl->sameAsInfo == 'true') {
        // 列表页与小说信息页是同一个页面，例如笔趣阁
        $page['attach']['id'] = $bookId;
        call_user_func($spider->on_list_page, $page, $content, $spider); // 直接调用
    } else {
        $listUrl = selector::select($content, $infoRule->listUrl->rule, $infoRule->listUrl->type);
        if (empty($listUrl)) {
            return false;
        }
        $listUrl = $spider->fill_url($listUrl, $page['request']['url']);
        $spider->add_scan_url($listUrl, array(), true, 'list', array('id' => $bookId));
    }
    return false;
};
$spider->on_list_page = function ($page, $content, $spider) use ($sourceName, $listRule) {
    // 暂时不对最新章节等等进行智能比对，后续再实现，目前直接通过章节数比对
    $cTitles = selector::select($content, $listRule->chapterTitle->rule, $listRule->chapterTitle->type);
    $cUrls = selector::select($content, $listRule->chapterUrl->rule, $listRule->chapterUrl->type);
    if ((string)$listRule->reverse == 'true') {
        // 反转章节
        $cTitles = array_reverse($cTitles);
        $cUrls = array_reverse($cUrls);
    }
    // 删除章节，先反转后删除
    $delc = explode('|||', $listRule->delChapter);
    foreach ($delc as $del) {
        if (is_numeric($del) && $del != 0) {
            if ($del > 0) $del--; // 因为splice如果参数是正数，0为第一个元素，所以要减1。负数无需减1，-1就是倒数第一个元素
            array_splice($cTitles, $del, 1);
            array_splice($cUrls, $del, 1);
        }
    }
    $cNum = count($cTitles);
    if ($cNum != count($cUrls)) {
        // 数量不匹配
        return false;
    }
    $sourceDir = __DIR__ . '/../book/' . intval($page['attach']['id'] / 1000) . '/' . $page['attach']['id'];
    $sourceFile = $sourceDir . '/source';
    $cListDir = $sourceDir . '/' . $sourceName;
    $cListFile = $cListDir . '/list';
    if (!CFile::_mkdir($sourceDir) || !CFile::_mkdir($cListDir)) {
        return false;
    }
    if (file_exists($sourceFile)) {
        $c = file_get_contents($sourceFile);
        if ($c === false) {
            return false;
        }
        $source = json_decode($c, true);
    }
    if (isset($source[$sourceName]) && $source[$sourceName]['chapterNum'] >= $cNum)  {
        // 比对章节数，若原先的章节数大于等于现在的，那就不更新
        return false;
    }
    $chapters = array();
    $cid = 0;
    foreach ($cTitles as $title) {
        // 这里可以用cid作数组下标，因为key都是重排过的
        $chapters[$cid] = array(
            'url' => $spider->fill_url($cUrls[$cid++], $page['request']['url']),
            'title' => $title,
        );
    }
    if (file_put_contents($cListFile, json_encode($chapters, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
        // 更新LIST成功，才更新source，若list更新成功但source更新失败影响不大
        $source[$sourceName] = array(
            'chapterNum' => $cNum,
            'lastChapter' => end($cTitles),
            'updateTime' => time(),
        );
        file_put_contents($sourceFile, json_encode($source, JSON_UNESCAPED_UNICODE), LOCK_EX); // 更新source
    }
    return false;
};
unset($sourceName);
unset($infoRule);
unset($listRule);
while (true) {
    $spider->start();
    sleep($waitTime);
    $spider->tnum++;
}
