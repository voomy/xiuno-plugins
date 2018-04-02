<?php

/**
 * 微信登录插件配置
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

!defined('DEBUG') AND exit('Access Denied.');

if ($method == 'GET') {
	$kv = kv_get('skiy_wx_login');
	
	$input = array();
	$input['appid'] = form_text('app_id', $kv['appid']);
	$input['appsecret'] = form_text('app_secret', $kv['appsecret']);
	$input['qrcode_expiry'] = form_text('qrcode_expiry', $kv['qrcode_expiry']);
	
	include _include(APP_PATH.'plugin/skiy_wx_login/setting.htm');
	
} else {

	$kv = array();
	$kv['appid'] = param('app_id');
	$kv['appsecret'] = param('app_secret');
	$qrcode_expiry = param('qrcode_expiry');
	$kv['qrcode_expiry'] = (int)$qrcode_expiry;
	
	kv_set('skiy_wx_login', $kv);
	
	message(0, '修改成功');
}
