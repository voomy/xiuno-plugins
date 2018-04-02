<?php

/**
 * 付费主题方法
 */

 /**
  * 判断是否为付费主题
  */
function thread_is_pay_thread($tid) {
    $arr = db_find_one('skiy_post_pay_thread', array('tid' => $tid));
	if ($arr) {
       return $arr;
    }
	return FALSE;
}

/**
 * 用户是否已支付此主题
 */
function thread_paid_by_uid($uid, $tid) {
    $where = array(
        'tid' => $tid,
        'uid' => $uid,
        'pay' => 1,
    );

    $arr = db_find_one('skiy_post_pay_order', $where);
	if ($arr) {
       return $arr;
    }
	return FALSE; 
}