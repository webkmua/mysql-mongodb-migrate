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
$catMysql = mysql_query("SELECT * FROM `categories` WHERE `cat_id` NOT IN (25, 11)");
$catMongo = $mongoDB->selectCollection('categories');
$catMongo->drop();
$tagMongo = $mongoDB->selectCollection('tags');
$tagMongo->drop();
$ConverterCAT = array();
while ($category = mysql_fetch_array($catMysql, MYSQL_ASSOC)) {
      if ($category['cat_pid'] == 0) {
          $categoryMongo['name'] = $category['cat_ru'];
          $categoryMongo['alias'] = $category['cat_en'];
          $categoryMongo['cat_id'] = $category['cat_id'];
          $categoryMongo['cat_pid'] = $category['cat_pid'];
          $catMongo->insert(array_filter($categoryMongo));

          $ConverterCAT[$category['cat_id']] = $category['cat_id'];

      } else {
          $tag['name'] = $category['cat_ru'];
          $tag['alias'] = $category['cat_en'];
          $tag['cat_id'] = $category['cat_id'];

          $tagMongo->insert(array_filter($tag));
          unset($tag);

          $ConverterCAT[$category['cat_id']] = $category['cat_pid'];
      }
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
$tagMongo = $mongoDB->selectCollection('tags');
$cursor = $tagMongo->find();
$converterTAG = array();
foreach ($cursor as $obj) {
    if (isset($obj['cat_id']))
        $converterTAG[$obj['cat_id']] = $obj['_id'];
}

$users = $mongoDB->selectCollection('users');
$user = $users->findOne(array('username' => 'admin'), array('_id'));

$imgMysql = mysql_query("SELECT * FROM img WHERE `rank_count` > 3 AND `author` = '' AND `cat_id` NOT IN (25,11)");
$imgMongo = $mongoDB->selectCollection('wallpapers');
$imgMongo->drop();

while ($img = mysql_fetch_array($imgMysql, MYSQL_ASSOC)) {
    $MongoImg['filename'] = $img['file_name'];
    $MongoImg['categories'] = array($converterID[$ConverterCAT[$img['cat_id']]]);
    $MongoImg['createdAt'] = new MongoDate($img['time']);
    $MongoImg['user'] = new MongoId($user['_id']);
    $MongoImg['old_id'] = $img['img_id'];
    $MongoImg['old_cat_id'] = $img['cat_id'];
    if (isset($converterTAG[$img['cat_id']]))
        $MongoImg['tags'] = array($converterTAG[$img['cat_id']]);

    $imgMongo->insert(array_filter($MongoImg));
    unset($MongoImg);
}

// SUBSCRIBER MIGRATIONS
$subMysql = mysql_query("SELECT * FROM `mailer`");
$subMongo = $mongoDB->selectCollection('subscribers');
$subMongo->drop();
while ($subscriber = mysql_fetch_array($subMysql, MYSQL_ASSOC)) {
    $subscriberMongo['email'] = $subscriber['email'];
    $subscriberMongo['confirmed'] = true;
    $subscriberMongo['categories'] = $subscriber['categories'];
    $subMongo->insert(array_filter($subscriberMongo));
}
