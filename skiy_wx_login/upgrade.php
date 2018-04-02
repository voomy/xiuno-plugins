<?php

/**
 * 微信登录插件更新
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

$kv1 = kv_get('skiy_wx_login');

$kv = array();
$kv['appid'] = $kv1['appid'];
$kv['appsecret'] = $kv1['appsecret'];
$kv['qrcode_expiry'] = isset($kv1['qrcode_expiry']) ? (int)$kv1['qrcode_expiry'] : 120;

kv_set('skiy_wx_login', $kv);