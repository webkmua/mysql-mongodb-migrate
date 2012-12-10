<?php

//Mysql CONFIGURATION
$mysql_host = 'localhost';
$mysql_user = 'root';
$mysql_passwd = '';
$mysql_db = 'wallpaper_in_ua';
$mysql_prefix = '';

//MongoDB CONFIGURATION
$mongo_host = 'localhost';
$mongo_user = 'admin';
$mongo_passwd = 'admin';
$mongo_db = 'wallpaper_in_ua';
$mongo_prefix = '';

//CONNECT TO MYSQL
function connect_mysql($mysql_host, $mysql_user, $mysql_passwd, $mysql_db) {
    $con = mysql_connect($mysql_host, $mysql_user, $mysql_passwd);
    if (!$con) { die('Could not connect: ' . mysql_error());}
    mysql_select_db($mysql_db, $con);
    mysql_set_charset('utf8');
}

//CONNECT TO MONGO
function connect_mongo($mongo_host, $mongo_user, $mongo_passwd, $mongo_db) {
    $mongo = new Mongo('mongodb://' . $mongo_user . ':' . $mongo_passwd . '@' . $mongo_host, array('db' => $mongo_db));
    return new MongoDB($mongo, $mongo_db);
}

$mongoDB = connect_mongo($mongo_host, $mongo_user, $mongo_passwd, $mongo_db);
           connect_mysql($mysql_host, $mysql_user, $mysql_passwd, $mysql_db);



// CATEGORIES MIGRATIONS
//select only parents category without erotic and hentay
$catMysql = mysql_query("SELECT * FROM `categories` WHERE `cat_pid` = 0  AND `cat_id` NOT IN (25, 11)");
$catMongo = $mongoDB->selectCollection('categories');
while ($category = mysql_fetch_array($catMysql, MYSQL_ASSOC)) {

    $category['name'] = $category['cat_ru'];
    unset($category['cat_ru']);
    $category['alias'] = $category['cat_en'];
    unset($category['cat_en']);

    $catMongo->insert(array_filter($category));
}




//TAGS CREATION

//select only child category
$catMysql = mysql_query("SELECT * FROM `categories` WHERE `cat_pid` != 0");
$tagMongo = $mongoDB->selectCollection('tags');
while ($tags = mysql_fetch_array($catMysql, MYSQL_ASSOC)) {

    $tag['name'] = $tags['cat_ru'];
    $tag['alias'] = $tags['cat_en'];
    $tag['cat_pid'] = $tags['cat_pid'];

    $tagMongo->insert(array_filter($tag));
}




//IMG MIGRATION

//prepare array indexes IDs
$catMongo = $mongoDB->selectCollection('categories');
$cursor = $catMongo->find();
$converterID = array();
foreach ($cursor as $obj) {
   if (isset($obj['cat_id']))
       $converterID[$obj['cat_id']] = $obj['_id'];
}

//prepare array indexes TAGs
$catMongo = $mongoDB->selectCollection('categories');
$cursor = $catMongo->find();
$converterID = array();
foreach ($cursor as $obj) {
    if (isset($obj['cat_id']))
        $converterID[$obj['cat_id']] = $obj['_id'];
}

$imgMysql = mysql_query("SELECT * FROM img");
$imgMongo = $mongoDB->selectCollection('wallpapers');

while ($img = mysql_fetch_array($imgMysql, MYSQL_ASSOC)) {
    $img['filename'] = $img['file_name'];
    unset($img['file_name']);
    $img['categories'] = array($converterID[$img['cat_id']]);
    unset($img['cat_id']);
    $img['createdAt'] = new MongoDate($img['time']);
    unset($img['time']);
    $img['user'] = new MongoId("50b626ea8ead0e683b000001");


    $imgMongo->insert(array_filter($img));
}
