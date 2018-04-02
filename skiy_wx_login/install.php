<?php

/**
 * 微信登录
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;
$sql = "CREATE TABLE IF NOT EXISTS `{$tablepre}skiy_wx_login` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '序号',
    `uid` int(11) NOT NULL COMMENT '用户ID',
    `openid` varchar(64) NOT NULL COMMENT '微信openid',
    `create_date` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='微信登录'";
db_exec($sql);

// 初始化
$kv = kv_get('skiy_wx_login');
if (empty($kv)) {
	$kv = array(
        'app_id' => '', 
        'app_secret' => '',
        'qrcode_expiry' => 120,
    );
	kv_set('skiy_wx_login', $kv);
}