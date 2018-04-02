<?php

/**
 * 微信登录
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/xiuno-paythread.html
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

//付费主题
$sql = "CREATE TABLE IF NOT EXISTS `{$tablepre}skiy_post_pay_thread` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '序号',
    `tid` int(11) NOT NULL COMMENT '主题ID',
    `price` int(11) NOT NULL COMMENT '价格(分)',
    `create_date` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='付费主题'";
db_exec($sql);

//付费主题订单
$sql = "CREATE TABLE IF NOT EXISTS `{$tablepre}skiy_post_pay_order` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '序号',
    `tid` int(11) NOT NULL COMMENT '主题ID',
    `uid` int(11) NOT NULL COMMENT '下单者',
    `pay` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已支付(0未支付,1已支付)',
    `order_id` varchar(40) NOT NULL COMMENT '订单ID',
    `total_price` int(11) NOT NULL COMMENT '付款金额(分)',
    `paid_time` int(11) NOT NULL DEFAULT '0' COMMENT ' 支付时间',
    `create_date` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='付费主题订单'";
db_exec($sql);

// 初始化
$kv = kv_get('skiy_pay_thread');
if (empty($kv)) {
	$kv = array(
        'app_id' => '', 
        'app_secret' => '',
        'mch_id' => '',
        'api_key' => '',
    );
	kv_set('skiy_pay_thread', $kv);
}