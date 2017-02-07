<?php
class book
{
    
    public $bookId;
    
    public function __construct()
    {
        $this->bookId = isset($_POST['bookId']) ? $_POST['bookId'] : 0;
        if (floor($this->bookId) != $this->bookId || $this->bookId <= 0) {
            response::make('小说ID有误', 201);
        }
    }
    
    public function getBookInfo()
    {
        // 获取小说详情
        $book = db::get_one("select * from `enovel_books` where `id` = '{$this->bookId}' limit 1");
        if (empty($book)) {
            response::make('小说不存在', 202);
        } else {
            if (strtolower($_POST['lb']) == 'br') $book['book_intro'] = nl2br($book['book_intro']);
            $sort = db::get_one("select name from `enovel_sorts` where `id` = '{$book['book_sort']}' limit 1");
            $book['book_sort'] = empty($sort) ? '其他类型' : $sort['name'];
            $book['book_cover'] = config('static_url') . '/cover/' . $book['book_cover'];
            $book['book_status'] = $book['book_status'] ? '已完结' : '连载中';
            return $book;
        }
    }
    
    public function getBookSource()
    {
        // 获取小说可用源
        $source = json_decode(@file_get_contents(__DIR__ . '/../book/' . intval($this->bookId / 1000) . '/' . $this->bookId . '/source'), true);
        if (empty($source)) {
            response::make('该小说暂无可用源', 203);
        }
        $id = 0;
        $rSource = array();
        foreach ($source as $k => $v) {
            $v['source'] = $k;
            $rSource[++$id] = $v;
        }
        return $rSource;
    }
    
    public function getChapterList()
    {
        $cList = json_decode(@file_get_contents(__DIR__ . '/../book/' . intval($this->bookId / 1000) . '/' . $this->bookId . '/' . $_POST['source'] . '/list'), true);
        if (empty($cList)) {
            response::make('该小说暂无此源', 204);
        }
        return $cList;
    }
    
    public function getChapterContent()
    {
        // TODO:代理，UA....分布式
        $chapterId = isset($_POST['chapterId']) ? $_POST['chapterId'] : 0;
        if (floor($chapterId) != $chapterId || $chapterId <= 0) {
            response::make('章节ID有误', 205);
        }
        $cList = $this->getChapterList();
        if (!isset($cList[$chapterId])) {
            response::make('章节不存在', 206);
        }
        $cFile = __DIR__ . '/../book/' . intval($this->bookId / 1000) . '/' . $this->bookId . '/' . $_POST['source'] . '/chapter_' . $chapterId;
        if (file_exists($cFile)) {
            $chapter = json_decode(@file_get_contents($cFile), true);
            if (empty($chapter['content']) || $chapter['url'] != $cList[$chapterId]['url']) {
                // URL有更新或content为空
                $rule = @simplexml_load_file(__DIR__ . '/../content_rules/' . $_POST['source'] . '_content.xml');
                if (empty($rule)) {
                    response::sysError();
                }
                if ((string)$rule->encoding != '') {
                    requests::$input_encoding = (string)$rule->encoding;
                }
                requests::$output_encoding = 'utf-8';
                $content = requests::get($cList[$chapterId]['url']);
                $content = selector::select($content, $rule->chapterContent->rule, $rule->chapterContent->type);
                if (empty($content)) {
                    response::sysError();
                }
                if (!empty($rule->chapterContent->replace)) {
                    $rms = explode('|||', $rule->chapterContent->replace);
                    foreach ($rms as $rm) {
                        $r = explode('-->', $rm);
                        $patterns[] = $r[0];
                        $replacements[] = isset($r[1]) ? $r[1] : '';
                    }
                    $content = preg_replace($patterns, $replacements, $content);
                }
                $content = util::space_html2text($content);
                $chapter['url'] = $cList[$chapterId]['url'];
                $chapter['content'] = $content;
                @file_put_contents($cFile, json_encode($chapter, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
            if (strtolower($_POST['lb']) == 'br') {
                $chapter['content'] = nl2br($chapter['content']);
            }
            $chapter['title'] = $cList[$chapterId]['title'];
        } else {
            // 获取章节内容
            $rule = @simplexml_load_file(__DIR__ . '/../content_rules/' . $_POST['source'] . '_content.xml');
            if (empty($rule)) {
                response::sysError();
            }
            if ((string)$rule->encoding != '') {
                requests::$input_encoding = (string)$rule->encoding;
            }
            requests::$output_encoding = 'utf-8';
            $content = requests::get($cList[$chapterId]['url']);
            $content = selector::select($content, $rule->chapterContent->rule, $rule->chapterContent->type);
            if (empty($content)) {
                response::sysError();
            }
            if (!empty($rule->chapterContent->replace)) {
                $rms = explode('|||', $rule->chapterContent->replace);
                foreach ($rms as $rm) {
                    $r = explode('-->', $rm);
                    $patterns[] = $r[0];
                    $replacements[] = isset($r[1]) ? $r[1] : '';
                }
                $content = preg_replace($patterns, $replacements, $content);
            }
            $content = util::space_html2text($content);
            $chapter['url'] = $cList[$chapterId]['url'];
            $chapter['content'] = $content;
            if (strtolower($_POST['lb']) == 'br') {
                $chapter['content'] = nl2br($chapter['content']);
            }
            @file_put_contents($cFile, json_encode($chapter, JSON_UNESCAPED_UNICODE), LOCK_EX);
            $chapter['title'] = $cList[$chapterId]['title']; // 标题不存
        }
        return $chapter;
    }
    
}