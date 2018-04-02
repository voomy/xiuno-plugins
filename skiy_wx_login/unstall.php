<?php

/**
 * 卸载微信登录
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;
$sql = "DROP TABLE IF EXISTS `{$tablepre}skiy_wx_login`";

db_exec($sql);
