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

//扫描二维码登录
} else if ($action == 'scan') {
    $scan_action = param(2);
    $qrcode = param(3);
    $cache_key = 'qrcode_' . $qrcode;
    $expiry_time = 60; //60s后过期

    //定时获取数据
    if ($scan_action == 'get') {
        $qrcode = isset($_SESSION['qrcode']) ? $_SESSION['qrcode'] : '';
        $qrcode_key = 'qrcode_' . $qrcode;
        $data = cache_get($qrcode_key);

        $code = -1;
        $message = array(
            'errmsg' => '未知错误',
        );

        if (empty($data)) {
            $message['errmsg'] = '二维码已失效';
            $message['qrcode'] = $qrcode;
        } else {
            if ($data['status'] == 0) {
                $code = 1;
                $message['errmsg'] = '未扫码';
                $message['time'] = $time;
            } else if (($data['status'] == 1) && ! empty($data['openid'])) {
                $code = 0;
                $message['errmsg'] = '已扫码登录';

                include _include(APP_PATH.'plugin/skiy_wx_login/model/wx_login.func.php');
                $user = wx_login_read_user_by_openid($data['openid']);  
                $uid = $user['uid'];
          
                $last_login = array(
                    'login_ip' => $longip, 
                    'login_date' => $time, 
                    // 'logins+' => 1 //微信扫码登录(本次不增加登录次数)
                );
                user_update($user['uid'], $last_login);
                
                $_SESSION['uid'] = $uid;
                user_token_set($uid);
     
                //删除此次二维码
                unset($_SESSION['qrcode']);
                cache_delete($qrcode_key);
            }
        }

        message($code, $message);

    //微信扫码    
    } else if ($scan_action == 'key') {
        if (empty($qrcode)) {
            message(-1, jump('二维码无效', $home_url, 2));
        }

        $data = cache_get($cache_key);

        //如果缓存的数据无效 且 状态不为未扫码 ($data['status'] != 0)
        if (empty($data) || ($data['status'] != 0)) {
            message(-1, jump('二维码已失效', $home_url, 2));
        }

        $login_url = 'login-' . $qrcode;
        $link = redirect('scan', $login_url);
        http_location($link);
    
    //创建二维码的CODE   
    } else if ($scan_action == 'create') {
        $code_number = strtolower(xn_rand(16));
        $qrcode_key = 'qrcode_' . $code_number; 
        
        //将创建的code保存到session
        $_SESSION['qrcode'] = $code_number;

        $data = array(
            'status' => 0, //未扫码
        );
        cache_set($qrcode_key, $data, $expiry_time);

        //如果存在旧二维码,删除
        if (! empty($qrcode)) {
            cache_delete('qrcode_' . $qrcode);
        }

        $message = array(
            'qrcode' => $code_number
        );
        message(0, $message);
    
    //微信登录回调    
    } else if ($scan_action == 'login') {
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

        $data = array(
            'status' => 1, //更新状态为已扫码
            'openid' => $openid,
        );
        cache_set($cache_key, $data, $expiry_time);
        
        message(0, jump('登陆成功', $home_url, 2));
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