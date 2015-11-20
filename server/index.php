<?php

include_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

include_once 'lib/rb.config.php';


//$isbn = 9789862168370;

if (isset($_GET["isbn"]) === FALSE) {
    $json = json_encode(array(
        "error" => "NO_ISBN"
            ), JSON_UNESCAPED_UNICODE);
    echo $json;
    book_list_log($json);
    exit();
}

$isbn = $_GET["isbn"];

// ---------------------
// 先取得快取
$result = R::find("cache_query_result", 'isbn = ? AND timestamp > ?'
        , array(
            $isbn,
            time() - $CONFIG["cache_sec"]
        ));

//echo time()-100;
if (count($result) > 0) {
    $json = "";
    foreach ($result as $r) {
        $json = "" . $r->json;
        //echo "/*" . $r->timestamp . "*/";
    }
    echo $json;
    book_list_log($json);
    
    exit();
}
//echo count($result);
//exit();

// ----------------------
// 準備開始查詢

$url = "http://jenda.lib.nccu.edu.tw/search~S5*cht/?searchtype=i&searcharg=" . $isbn  . "&searchscope=5&sortdropdown=-&SORT=DZ&extended=0&SUBMIT=%E6%9F%A5%E8%A9%A2&availlim=1&searchlimits=&searchorigarg=X%7Bu8CC8%7D%7Bu4F2F%7D%7Bu65AF%7D%7Bu50B3%7D%26SORT%3DD#.Vk6H3HYrLRY";

// 測試檔案
//$url = "query_test/isbn_not_found.html";
//$url = "query_test/found_book_available.html";
//$url = "query_test/found_book_multi_available.html";
//$url = "query_test/found_book_not_available.html";
$content = file_get_contents($url);
//echo $content;

//exit();

require 'lib/querypath/src/qp.php';

$qp = htmlqp($url);

if ($qp->find('.msg td:contains("無查獲符合查詢條件的館藏;相近 國際標準號碼 是:")')->size() > 0 
        || $qp->find('.msg td:contains("無查獲符合的,可用相近 國際標準號碼 的是:")')->size() > 0 ) {
    
    // ---------------------------------------------
    // isbn_not_found
    // ---------------------------------------------
    
    
    $data = array(
        "error" => "NOT_FOUND"
    );
}   //if (htmlqp($url, '.msg td:contains("無查獲符合查詢條件的館藏;相近 國際標準號碼 是:")')->size() > 0) {
else if ($qp->find('.bibItemsEntry td:contains("可流通")')->size() === 0) {
    
    // ---------------------------------------------
    // found_book_not_available
    // ---------------------------------------------
    
    $full_title = $qp->find('.bibInfoLabel:contains("題名/作者")')->eq(0)->next()->find("strong:first")->text();
    $title = substr($full_title, 0, strpos($full_title, " / "));
    $title = trim($title);

    $data = array(
        "error" => "NOT_AVAILABLE",
        "title" => $title
    );
}   // else if ($qp->find('.bibItemsEntry td:contains("可流通")')->size() === 0) {
else {

    // ---------------------------------------------
    // found_book_available
    // ---------------------------------------------
    
    $data = array();

    $author = $qp->find('.bibInfoLabel:contains("作者")')->eq(0)->next()->text();
//echo $author;

    $full_title = $qp->find('.bibInfoLabel:contains("題名/作者")')->eq(0)->next()->find("strong:first")->text();
    $title = substr($full_title, 0, strpos($full_title, " / "));
    $title = trim($title);
//echo $title;

    //$isbn = $qp->find('.bibInfoLabel:contains("國際標準書號")')->eq(0)->next()->text();
    //$isbn = substr($isbn, 0, strpos($isbn, " : "));
    //$isbn = trim($isbn);
    //$isbn = intval($isbn);

    $available_td_list = $qp->find('.bibItemsEntry td:contains("可流通")');
    for ($i = 0; $i < $available_td_list->size(); $i++) {
        $call_number = $available_td_list->eq($i)->prev()->text();
        $call_number = trim($call_number);

        $location = $available_td_list->eq($i)->prev()->prev()->text();
        $location = trim($location);
        array_push($data, array(
            "title" => $title,
            "call_number" => $call_number,
            "location" => $location,
            "isbn" => $isbn
        ));
    }
}   // if (htmlqp($url, '.bibItemsEntry td:contains("可流通")')->size() > 0) {

// ---------------------------
// 轉換
$json = json_encode($data, JSON_UNESCAPED_UNICODE);

// ---------------------------
// 備份快取資料
$result = R::findOrCreate('cache_query_result', [
    'isbn' => $isbn
]);
$result->isbn = $isbn;

$result->json = $json;
$result->timestamp = time();
 R::store($result);


echo $json;
book_list_log($json);