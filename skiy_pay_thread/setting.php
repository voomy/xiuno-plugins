<?php

/**
 * 微信登录插件配置
 */

!defined('DEBUG') AND exit('Access Denied.');

if ($method == 'GET') {
	$kv = kv_get('skiy_pay_thread');
	
	$input = array();
	$input['app_id'] = form_text('app_id', $kv['app_id']);
	$input['app_secret'] = form_text('app_secret', $kv['app_secret']);
	$input['mch_id'] = form_text('mch_id', $kv['mch_id']);
	$input['api_key'] = form_text('api_key', $kv['api_key']);
	
	include _include(APP_PATH.'plugin/skiy_pay_thread/setting.htm');
	
} else {

	$kv = array();
	$kv['app_id'] = param('app_id');
	$kv['app_secret'] = param('app_secret');
	$kv['mch_id'] = param('mch_id');
	$kv['api_key'] = param('api_key');
	
	kv_set('skiy_pay_thread', $kv);
	
	message(0, '修改成功');
}
