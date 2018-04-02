<?php 

!defined('DEBUG') AND exit('Access Denied.');

include _include(APP_PATH.'plugin/skiy_wx_login/model/wechat.class.php');

$action = param(1);

$home_url = http_url_path();

$wxlogin = kv_get('skiy_wx_login');

//授权登录
if (empty($action) || ($action == 'create')) {
    $create_action = param(2);

    //已登录用户不可调用新建微信用户，并转跳至绑定微信接口
    if (! empty($user)) {
        $link = redirect('bind');
        http_location($link);
    }

    if (empty($create_action)) {
        $link = redirect('create', 'callback');
        http_location($link);
    } else if ($create_action == 'callback') {
        $wx_config = [
            'appid' => $wxlogin['appid'],
            'appsecret' => $wxlogin['appsecret'],
        ];
        $wechat = new Wechat($wx_config);
        $wx_token = $wechat->getOauthAccessToken();
        if (empty($wx_token)) {
            message($wechat->errCode, $wechat->errMsg);
        }
    
        $access_token = $wx_token['access_token'];
        $openid = $wx_token['openid'];
    
        include _include(APP_PATH.'plugin/skiy_wx_login/model/wx_login.func.php');
    
        // 如果有 openid，则直接自动登陆
        $user = wx_login_read_user_by_openid($openid);
        if (! $user) {
            $wxuser = $wechat->getOauthUserinfo($access_token, $openid);
    
            if (empty($wxuser)) {
                message($wechat->errCode, $wechat->errMsg);
            }
    
            //$wxuser['headimgurl'];
            $user = wx_login_create_user($wxuser['nickname'], '', $openid);
            if (empty($user)) {
                message($errno, '获取 openid 失败，错误原因：'.$errstr);
            }
        }
        
        $uid = $user['uid'];
        
        $last_login = array(
            'login_ip' => $longip, 
            'login_date' => $time, 
            'logins+' => 1
        );
        user_update($user['uid'], $last_login);
        
        $_SESSION['uid'] = $uid;
        user_token_set($uid);
        
        message(0, jump('登陆成功', $home_url, 2));
    }

//绑定微信    
} else if ($action == 'bind') {
    $bind_action = param(2);

    //未登录则不可操作
    if (empty($user)) {
        http_location($home_url);
    }

    include _include(APP_PATH.'plugin/skiy_wx_login/model/wx_login.func.php');

    if (empty($bind_action)) {
        $uid = $user['uid'];

        $uid_binded = wx_had_bind_user_by_uid($uid);
        if (! empty($uid_binded)) {
            message(1, jump('该帐号已经绑定微信', $home_url, 2));
        }

        $link = redirect('bind', 'callback');
        http_location($link);
    } else if ($bind_action == 'callback') {
        $wx_config = [
            'appid' => $wxlogin['appid'],
            'appsecret' => $wxlogin['appsecret'],
        ];
        $wechat = new Wechat($wx_config);
        $wx_token = $wechat->getOauthAccessToken();
        if (empty($wx_token)) {
            message($wechat->errCode, $wechat->errMsg);
        }

        $access_token = $wx_token['access_token'];
        $openid = $wx_token['openid'];

        $wx_binded = wx_had_bind_user_by_openid($openid);
        if (! empty($wx_binded)) {
            message(0, jump('微信已经绑定UID', $home_url, 2));
        }

        $uid = $user['uid'];

        $bind = wx_bind_uid($uid, $openid);
        if (empty($bind)) {
            message(-1, '绑定微信失败');
        }

        $redirect_url = http_url_path().url('my.htm');
        message(0, jump('绑定微信成功', $redirect_url, 2));

    }
} else {
    http_location($home_url);
}

/**
 * 重定向 微信授权
 */
function redirect($param1='callback', $param2='') {
    global $wxlogin;
    $wx_config = [
        'appid' => $wxlogin['appid'],
        'appsecret' => $wxlogin['appsecret'],
    ];
    $wechat = new Wechat($wx_config);

    $uri = (! empty($param2)) ? '-' . $param2 : '';
    $return_url = http_url_path().url('wx_login-' . $param1 . $uri);
    $link = $wechat->getOauthRedirect($return_url, '');
	return $link;
}