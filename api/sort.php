<?php
class sort
{
    
    public function getAllSorts()
    {
        $sorts = db::get_all("select * from `enovel_sorts`");
        if (empty($sorts)) {
            response::make('暂无小说类别', 302);
        }
        return $sorts;
    }
    
    public function getBooksBySort()
    {
        $sortId = isset($_POST['sortId']) ? $_POST['sortId'] : 0;
        if (floor($sortId) != $sortId || $sortId <= 0) {
            response::make('类别ID有误', 301);
        }
        $sort = db::get_one("select name from `enovel_sorts` where `id` = '{$sortId}' limit 1");
        if (empty($sort)) {
            response::make('类别不存在', 303);
        }
        $page = isset($_POST['page']) && intval($_POST['page']) > 0 ? intval($_POST['page']) : 1;
        $num = isset($_POST['num']) && intval($_POST['num']) > 0 && intval($_POST['num']) < 100 ? intval($_POST['num']) : 10;
        $start = ($page - 1) * $num;
        $orderBy = isset($_POST['orderBy']) && strtolower($_POST['orderBy']) == 'asc' ? 'asc' : 'desc';
        $books = db::get_all("select * from `enovel_books` inner join (select `id` from `enovel_books` where `book_sort` = '{$sortId}' order by `post_time` {$orderBy} limit {$start},{$num}) as sort_books using (id)");
        if (empty($books)) {
            response::make('该类别暂无小说', 304);
        }
        foreach ($books as $k => $v) {
            $books[$k]['book_sort'] = $sort['name'];
            $books[$k]['book_cover'] = config('static_url') . '/cover/' . $v['book_cover'];
            $books[$k]['book_status'] = $v['book_status'] ? '已完结' : '连载中';
        }
        return $books;
    }
    
}