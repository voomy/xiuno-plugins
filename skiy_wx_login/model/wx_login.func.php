<?php
!defined('DEBUG') AND exit('Access Denied.');

/**
 * 微信登录
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

/**
 * 获取用户信息
 * $openid 微信openid
 */
function wx_login_read_user_by_openid($openid) {
    $arr = db_find_one('skiy_wx_login', array('openid' => $openid));
    if ($arr) {
        $arr2 = user_read($arr['uid']);
        if ($arr2) {
            $arr = array_merge($arr, $arr2);
        } else {
            db_delete('skiy_wx_login', array('openid' => $openid));
            return FALSE;
        }
    }
    return $arr;
}

/**
 * 微信 openid 已绑定用户
 */
function wx_had_bind_user_by_openid($openid) {
    $arr = db_find_one('skiy_wx_login', array('openid' => $openid));
    if ($arr) {
        return $arr;
    }
    return FALSE;
}

/**
 * UID 已绑定微信
 */
function wx_had_bind_user_by_uid($uid) {
    $arr = db_find_one('skiy_wx_login', array('uid' => $uid));
    if ($arr) {
        return $arr;
    }
    return FALSE;
}

/**
 * 绑定失败
 */
function wx_bind_uid($uid, $openid) {
    global $time;

    $bind = array(
        'uid' => $uid,
        'openid' => $openid,
        'create_date' => $time
    );

    $r = db_insert('skiy_wx_login', $bind);
    if (empty($r)) {
        return FALSE;
    };

    return TRUE;
}

/**
 * 创建新用户
 * $username 用户名
 * $avatar_url_2 头像URL
 * $openid 微信openid
 */
function wx_login_create_user($username, $avatar_url_2, $openid) {
    global $conf, $time, $longip;
    $arr = wx_login_read_user_by_openid($openid);
    if ($arr) return xn_error(-2, '已经注册');

    // 自动产生一个用户名
    $r = user_read_by_username($username);
    if ($r) {
        // 特殊字符过滤
        $username = xn_substr($username . '_' . $time, 0, 31);
        $r = user_read_by_username($username);
        if ($r) return xn_error(-1, '用户名被占用。');
    }
    // 自动产生一个 Email
    $email = "{$openid}@wx.com";
    $r = user_read_by_email($email);
    if ($r) return xn_error(-1, 'Email 被占用');

    // 随机密码
    $password = md5(rand(1000000000, 9999999999) . $time);
    $user = array(
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'gid' => 101,
        'salt' => rand(100000, 999999),
        'create_date' => $time,
        'create_ip' => $longip,
        'avatar' => 0,
        'logins' => 1,
        'login_date' => $time,
        'login_ip' => $longip,
    );

    $uid = user_create($user);
    if (empty($uid)) return xn_error(-1, '注册失败');

    $user = user_read($uid);

    $bind = array(
        'uid' => $uid,
        'openid' => $openid,
        'create_date' => $time
    );

    $r = db_insert('skiy_wx_login', $bind);
    if (empty($r)) return xn_error(-1, '注册失败');

    runtime_set('users+', '1');
    runtime_set('todayusers+', '1');

    // 头像不重要，忽略错误。
    if (!empty($avatar_url_2) && (filter_var($avatar_url_2, FILTER_VALIDATE_URL))) {
        $filename = "{$uid}.png";
        $dir = substr(sprintf("%09d", $uid), 0, 3) . '/';
        $path = $conf['upload_path'] . 'avatar/' . $dir;
        !is_dir($path) AND mkdir($path, 0777, TRUE);

        $data = file_get_contents($avatar_url_2);
        file_put_contents($path . $filename, $data);

        user_update($uid, array('avatar' => $time));
    }
    return $user;

}

/**
 * 判断微信客户端
 * @return bool
 */
function is_weixin() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($user_agent, 'MicroMessenger')) {
        return true;
    }
    return false;
}