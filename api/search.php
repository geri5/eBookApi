<?php
class search
{
    
    /**
     * 使用百度站内搜索API引擎
     *
     */
    public function searchByBaidu()
    {
        if (!isset($_POST['keyword']) || $_POST['keyword'] == '') response::make('关键词不能为空', 401);
        $keyword = $_POST['keyword']; // 不要urlencode
        $pn = isset($_POST['num']) && intval($_POST['num']) > 0 && intval($_POST['num']) < 10 ? intval($_POST['num']) : 10; // 每页返回的条数，最多10条
        $page = isset($_POST['page']) && intval($_POST['page']) > 0 ? intval($_POST['page']) - 1 : 0; // 0为第一页，接收的参数1为第1页
        $url = 'http://zhannei.baidu.com/api/customsearch/apisearch';
        $params = array(
            's' => config('searchByBaidu.s'),
            'q' => $keyword,
            'nojc' => 1,
            'rt' => 2,
            'pn' => $pn,
            'p' => $page,
            'tk' => config('searchByBaidu.tk'),
            'v' => config('searchByBaidu.v'),
            'callback' => 'callback',
        );
        requests::$input_encoding = 'utf-8';
        requests::$output_encoding = 'utf-8';
        requests::set_referer(config('domain') . '/s');
        requests::set_useragent('Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.1)');
        $result = requests::get($url, $params);
        if (!isset($_POST['highLight']) || $_POST['highLight'] != 1) {
            $result = preg_replace('@<em>(.*?)<\\\/em>@is', '$1', $result); // 不高亮，去掉<em></em>
        }
        $result = util::parse_jsonp($result);
        if (!isset($result['blockData'])) response::sysError();
        $allSorts = db::get_all("select * from `enovel_sorts`");
        if (empty($allSorts)) response::sysError();
        $sorts = array();
        foreach ($allSorts as $sort) {
            $sorts[$sort['id']] = $sort['name']; // 将分类id作为key，name作为value
        }
        $return = array(
            'novel' => array(),
            'searchInfo' => array(
                'totalNum' => $result['searchInfo']['totalNum'],
                'curPage' => $result['searchInfo']['curPage'] + 1,
            ),
        );
        foreach ($result['blockData'] as $data) {
            $return['novel'][] = array(
                'id' => (int)str_replace(config('domain') . '/b/', '', $data['linkurl']),
                'book_title' => $data['title'],
                'book_author' => $data['summarywords']['author'],
                'book_intro' => $data['abstract'],
                'book_sort' => isset($sorts[$data['summarywords']['genre']]) ? $sorts[$data['summarywords']['genre']] : '其他类型',
                'book_cover' => $data['image'],
                'book_status' => $data['summarywords']['updateStatus'] ? '已完结' : '连载中',
            );
        }
        return $return;
    }
    
    /**
     * 百度的搜索建议
     *
     */
    public function suggestByBaidu()
    {
        if (!isset($_POST['keyword']) || $_POST['keyword'] == '') response::make('关键词不能为空', 401);
        $keyword = $_POST['keyword'];
        $url = 'http://unionsug.baidu.com/su';
        $params = array(
            'wd' => $keyword,
            'p' => 3, // 不知道是啥？
            'cb' => 'callback',
            't' => time(),
        );
        requests::$input_encoding = 'gbk';
        requests::$output_encoding = 'utf-8';
        requests::set_referer('https://www.baidu.com');
        requests::set_useragent('Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.1)');
        $result = requests::get($url, $params);
        $result = util::parse_jsonp($result);
        if (!isset($result['s'])) response::sysError();
        $return = array(
            'keyword' => $keyword,
            'suggestion' => $result['s'],
        );
        return $return;
    }
    
    public function baiduSearchMap($startId, $endId)
    {
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        header('Content-Type:application/xml');
        $replace = function ($v) {
            return str_replace(array('&', '<', '>', '"', "'"), array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'), $v);
        };
        $startId = intval($startId) > 0 ? intval($startId) : 1;
        $endId = intval($endId) > $startId ? intval($endId) : $startId;
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset>';
        $now = date('Y-m-d', time());
        $once = 1000;
        $s = $startId - $once;
        $e = 0;
        while ($e < $endId) {
            $s = ($s + $once) < $endId ? $s + $once : $endId;
            $e = ($s + $once - 1) < $endId ? $s + $once - 1 : $endId;
            $books = db::get_all("select * from `enovel_books` where `id` between {$s} and {$e}");
            foreach ($books as $book) {
                $updateTime = $now;
                $bookUrl = config('domain') . '/b/' . $book['id']; // 用这个作为url，虽然是不存在的，只是为了存上bookId
                $book['book_cover'] = config('static_url') . '/cover/' . $book['book_cover'];
                foreach ($book as $k => $v) {
                    $book[$k] = $replace($v);
                }
                echo "<url><loc><![CDATA[{$bookUrl}]]></loc><lastmod>{$now}</lastmod><data><name>{$book['book_title']}</name><author><name>{$book['book_author']}</name></author><image><![CDATA[{$book['book_cover']}]]></image><description><![CDATA[{$book['book_intro']}]]></description><genre>{$book['book_sort']}</genre><url><![CDATA[{$bookUrl}]]></url><updateStatus>{$book['book_status']}</updateStatus><trialStatus>免费</trialStatus><dateModified>{$updateTime}</dateModified></data></url>";
            }
            unset($books);
        }
        echo '</urlset>';
    }
    
    public function baiduSearchMapIndex($pn)
    {
        header('Content-Type:application/xml');
        $pn = intval($pn) > 0 ? intval($pn) : 10000;
        echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex>';
        $now = date('Y-m-d', time());
        $row = db::get_one("select max(id) as max_id from `enovel_books`");
        $maxId = $row['max_id'];
        $num = ceil($maxId / $pn);
        $i = 0;
        while ($i < $num) {
            $min = $i++ * $pn + 1;
            $max = $i * $pn;
            $url = config('domain') . '/search/map_' . $min . '_' . $max . '.xml';
            echo "<sitemap><loc><![CDATA[{$url}]]></loc><lastmod>{$now}</lastmod></sitemap>";
        }
        echo '</sitemapindex>';
    }
    
    /**
     * 使用XunSearch迅搜作为全文检索引擎
     *
     */
    public function searchByXS()
    {
        if (!isset($_POST['keyword']) || $_POST['keyword'] == '') response::make('关键词不能为空', 401);
        $keyword = $_POST['keyword'];
        $pn = isset($_POST['num']) && intval($_POST['num']) > 0 && intval($_POST['num']) < 10 ? intval($_POST['num']) : 10;
        $page = isset($_POST['page']) && intval($_POST['page']) > 0 ? intval($_POST['page']) : 1;
        $skip = ($page - 1) * $pn;
        $allSorts = db::get_all("select * from `enovel_sorts`");
        if (empty($allSorts)) response::sysError();
        $sorts = array();
        foreach ($allSorts as $sort) {
            $sorts[$sort['id']] = $sort['name']; // 将分类id作为key，name作为value
        }
        try {
            $xs = new XS(__DIR__ . '/../config/' . config('searchByXS.ini'));
            $search = $xs->search;
            $results = $search->setFuzzy()
                ->setAutoSynonyms()
                ->setQuery($keyword)
                ->setLimit($pn, $skip)
                ->search(); // 模糊搜索
            $return = array(
                'novel' => array(),
                'searchInfo' => array(
                    'totalNum' => $search->getLastCount(),
                    'curPage' => $page,
                ),
            );
            foreach ($results as $result) {
                $highLight = isset($_POST['highLight']) && $_POST['highLight'] == 1 ? true : false;
                $return['novel'][] = array(
                    'id' => (int)$result->id,
                    'book_title' => $highLight ? $search->highLight($result->book_title) : $result->book_title,
                    'book_author' => $highLight ? $search->highLight($result->book_author) : $result->book_author,
                    'book_intro' => mb_substr($highLight ? $search->highLight($result->book_intro) : $result->book_intro, 0, 100, 'utf-8'), // 截取100个字，索引中是做的500个字，这里只显示出来前100个字
                    'book_sort' => isset($sorts[$result->book_sort]) ? $sorts[$result->book_sort] : '其他类型',
                    'book_cover' => config('static_url') . '/cover/' . $result->book_cover,
                    'book_status' => $result->book_status ? '已完结' : '连载中',
                );
            }
        } catch (XSException $e) {
            log::add($e->getMessage() . "\n" . $e->getTraceAsString(), 'Error');
            response::sysError();
        }
        return $return;
    }
    
    /**
     * 使用迅搜的搜索建议
     *
     */
    public function suggestByXS()
    {
        if (!isset($_POST['keyword']) || $_POST['keyword'] == '') response::make('关键词不能为空', 401);
        $keyword = $_POST['keyword'];
        try {
            $xs = new XS(__DIR__ . '/../config/' . config('searchByXS.ini'));
            $search = $xs->search;
            $return = array(
                'keyword' => $keyword,
                'suggestion' => $search->getExpandedQuery($keyword, 10),
            );
            return $return;
        } catch (XSException $e) {
            log::add($e->getMessage() . "\n" . $e->getTraceAsString(), 'Error');
            response::sysError();
        }
    }
    
}
