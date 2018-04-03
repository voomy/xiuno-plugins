<?php

/**
 * 微信登录
 * Skiychan <dev@skiy.net>
 * https://www.skiy.net/201804025057.html
 */

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);
$action_2 = param(2);

$home_url = http_url_path();

if (empty($action)) {
    http_location($home_url);
}

include _include(APP_PATH . 'plugin/skiy_wx_login/model/wechat.class.php');
include _include(APP_PATH . 'plugin/skiy_wx_login/model/wx_login.func.php');

$wxlogin = kv_get('skiy_wx_login');
$expiry_time = $wxlogin['qrcode_expiry']; //二维码有效时长(秒)

//微信内点击登录
if ($action == 'index') {
    //非微信内直接跳到首页
    if (! $is_weixin()) {
        http_location($home_url);
    }

    //已登录用户不可调用新建微信用户，并转跳至绑定微信接口(个人中心页绑定)
    if (!empty($user)) {
        $link = redirect('bind', 'bind_user-index');
        http_location($link);
    }

    if (empty($action_2)) {
        $link = redirect('index', 'callback');
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

        // 如果有 openid，则直接自动登陆
        $user = wx_login_read_user_by_openid($openid);
        if (!$user) {
            $wxuser = $wechat->getOauthUserinfo($access_token, $openid);

            if (empty($wxuser)) {
                message($wechat->errCode, $wechat->errMsg);
            }

            //$wxuser['headimgurl'];
            $user = wx_login_create_user($wxuser['nickname'], '', $openid);
            if (empty($user)) {
                message($errno, '获取 openid 失败，错误原因：' . $errstr);
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
    $qrcode_bind_pre = 'qr_bind_';

    //如果不是在微信中
    if (! $is_weixin()) {
        //创建绑定二维码
        if ($action_2 == 'create_qrcode') {
            $code = -1;
            $message = array(
                'errmsg' => '未知错误',
            );

            if (empty($user)) {
                $message['errmsg'] = '用户未登录';
                message($code, $message);
            } else {

                $uid_binded = wx_had_bind_user_by_uid($user['uid']);
                if (!empty($uid_binded)) {
                    message(1, '获取二维码失败，该帐号已经被他人绑定微信');
                }
                    
                if (isset($_SESSION['qrcode_bind'])) {
                    $cache_exist = cache_get($qrcode_bind_pre . $_SESSION['qrcode_bind']);
                    //码已失效
                    if (! empty($cache_exist)) {
                        $code_number = $_SESSION['qrcode_bind'];
                    } 
                }
                
                //SESSION 的code已失效
                empty($code_number) && $code_number = strtolower(xn_rand(16));

                $qrcode_key = $qrcode_bind_pre . $code_number;

                $code = 0;
                $message['qrcode'] = $code_number;
                $message['errmsg'] = '获取二维码成功';

                $data = array(
                    'uid' => $user['uid'], 
                );
                cache_set($qrcode_key, $data, $expiry_time);
                $_SESSION['qrcode_bind'] = $code_number;
            }

            message($code, $message);
        }

        //定时检测扫码
        if ($action_2 == 'scan_qrcode') {
            //微信登录
            $qrcode = param(3);
            $cache_key = $qrcode_bind_pre . $qrcode;

            $data = cache_get($cache_key);

            //如果缓存的数据无效 且 状态不为未扫码 ($data['status'] != 0)
            if (empty($data) || empty($data['uid'])) {
                message(-1, '二维码已失效');
            }

            $user = user_read($data['uid']);

            $uid = $user['uid'];

            $last_login = array(
                'login_ip' => $longip,
                'login_date' => $time,
            );
            user_update($user['uid'], $last_login);

            $_SESSION['uid'] = $uid;
            user_token_set($uid);

            //清除 code
            cache_delete($cache_key);

            message(0, '扫码登陆成功');
        }
    }

    //未登录则不可操作
    if (empty($user)) {
        http_location($home_url);
    }

    //微信中绑定用户
    if ($action_2 == 'bind_user') {
        $action_3 = param(3);

        $uid = $user['uid'];
        $uid_binded = wx_had_bind_user_by_uid($uid);
        if (!empty($uid_binded)) {
            message(1, jump('绑定微信失败，该帐号已经被他人绑定', $home_url, 2));
        }

        //绑定授权 - 若是由
        if ($action_3 == 'auth') {
            $link = redirect('bind', 'bind_user-index');
            http_location($link);
        }

        //已授权 - 绑定微信
        if ($action_3 == 'index') {
            $wx_config = [
                'appid' => $wxlogin['appid'],
                'appsecret' => $wxlogin['appsecret'],
            ];
            $wechat = new Wechat($wx_config);
            $wx_token = $wechat->getOauthAccessToken();
            if (empty($wx_token)) {
                message($wechat->errCode, jump('微信授权错误:' . $wechat->errMsg, $home_url, 2));
            }

            $access_token = $wx_token['access_token'];
            $openid = $wx_token['openid'];

            //判断微信是否已绑定其它帐号
            $wx_binded = wx_had_bind_user_by_openid($openid);
            if (!empty($wx_binded)) {
                message(-1, jump('绑定微信失败，该微信已经他人帐号', $home_url, 2));
            }

            $uid = $user['uid'];

            //此微信与此帐号是否已经绑定
            $bind = wx_bind_uid($uid, $openid);
            if (empty($bind)) {
                message(-1, jump('该帐号与微信绑定失败', $home_url, 2));
            }

            $redirect_url = $home_url . 'my.htm';
            message(0, jump('该帐号绑定微信成功', $redirect_url, 2));
        }

    }

//扫描二维码登录
} else if ($action == 'scan') {
    $qrcode = param(3);
    $qrcode_login_pre = 'qr_login_';

    //创建登录二维码
    if ($action_2 == 'create_qrcode') {
        if (isset($_SESSION['qrcode_login'])) {
            $code_exist = cache_get($qrcode_login_pre . $_SESSION['qrcode_login']);
            if (! empty($code_exist)) {
                $code_number = $_SESSION['qrcode_login'];
            }
        }

        empty($code_number) && $code_number = strtolower(xn_rand(16));

        $qrcode_key = $qrcode_login_pre . $code_number;

        $data = array(
            'status' => 0, //未扫码
        );
        cache_set($qrcode_key, $data, $expiry_time);

        //如果存在旧二维码,删除
        if (!empty($qrcode)) {
            cache_delete('qrcode_' . $qrcode);
        }

        //将创建的code保存到session
        $_SESSION['qrcode_login'] = $code_number;

        $message = array(
            'qrcode' => $code_number
        );

        message(0, $message);

        //定时检测微信扫码状态    
    } else if ($action_2 == 'check_qrcode') {
        $qrcode = isset($_SESSION['qrcode_login']) ? $_SESSION['qrcode_login'] : '';

        $code = -1;
        $message = array(
            'errmsg' => '二维码无效',
        );

        if (empty($qrcode)) {
            message($code, $message);
        }

        $qrcode_key = $qrcode_login_pre . $qrcode;
        $data = cache_get($qrcode_key);

        if (empty($data)) {
            $message['errmsg'] = '二维码已失效';
            $message['qrcode'] = $qrcode;
        } else {
            if ($data['status'] == 0) {
                $code = 1;
                $message['errmsg'] = '未扫码';
                $message['time'] = $time;
            } else if (($data['status'] == 1) && !empty($data['openid'])) {
                $code = 0;
                $message['errmsg'] = '已扫码登录';

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
                unset($_SESSION['qrcode_login']);
                cache_delete($qrcode_key);
            }
        }

        message($code, $message);

        //微信扫码该地址    
    } else if ($action_2 == 'scan_qrcode') {
        if (empty($qrcode)) {
            message(-1, jump('二维码无效', $home_url, 2));
        }

        $data = cache_get($qrcode_login_pre . $qrcode);

        //如果缓存的数据无效 且 状态不为未扫码 ($data['status'] != 0)
        if (empty($data) || ($data['status'] != 0)) {
            message(-1, jump('二维码已失效', $home_url, 2));
        }

        //校验码已通过，转跳至登录页面
        $link = redirect('scan', 'login-' . $qrcode);
        http_location($link);

        //已扫二维码登录   
    } else if ($action_2 == 'login') {
        $data = cache_get($qrcode_login_pre . $qrcode);

        //如果缓存的数据无效 且 状态不为未扫码 ($data['status'] != 0)
        if (empty($data) || ($data['status'] != 0)) {
            message(-1, jump('二维码已失效', $home_url, 2));
        }

        $wx_config = [
            'appid' => $wxlogin['appid'],
            'appsecret' => $wxlogin['appsecret'],
        ];
        $wechat = new Wechat($wx_config);
        $wx_token = $wechat->getOauthAccessToken();
        if (empty($wx_token)) {
            message($wechat->errCode, jump('获取微信token错误:'.$wechat->errMsg, $home_url, 2));
        }

        $access_token = $wx_token['access_token'];
        $openid = $wx_token['openid'];

        // 如果有 openid，则直接自动登陆
        $user = wx_login_read_user_by_openid($openid);
        
        //创建新用户
        if (!$user) {
            $wxuser = $wechat->getOauthUserinfo($access_token, $openid);

            if (empty($wxuser)) {
                message($wechat->errCode, jump('获取微信用户信息失败:'.$wechat->errMsg, $home_url, 2));
            }

            //$wxuser['headimgurl'];
            $user = wx_login_create_user($wxuser['nickname'], '', $openid);
            if (empty($user)) {
                message($errno, '创建新用户失败：' . $errstr);
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
        cache_set($qrcode_login_pre . $qrcode, $data, $expiry_time);

        message(0, jump('登陆成功', $home_url, 2));
    }

} else {
    http_location($home_url);
}

/**
 * 重定向 微信授权
 */
function redirect($param1 = '', $param2 = '') {
    global $wxlogin;
    $wx_config = [
        'appid' => $wxlogin['appid'],
        'appsecret' => $wxlogin['appsecret'],
    ];
    $wechat = new Wechat($wx_config);

    $uri = (!empty($param2)) ? '-' . $param2 : '';
    $return_url = http_url_path() . url('wx_login-' . $param1 . $uri);
    $link = $wechat->getOauthRedirect($return_url, '');
    return $link;
}