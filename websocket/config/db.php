<?php
$db['master'] = array(
    'type'    => Swoole\Database::TYPE_MYSQL,
    'host'    => "127.0.0.1",
    'port'    => 3306,
    'dbms'    => 'mysql',
    'engine'  => 'MyISAM',
    'user'    => "root",
    'passwd'  => "bufferme",
    'name'    => "mostat",
    'charset' => "utf8",
    'setname' => true,
);
return $db;