<?php

!defined('DEBUG') AND exit('Access Denied.');

include _include(APP_PATH.'plugin/skiy_pay_thread/model/wechat.class.php');

$action = param(1);
$action2 = param(2);

$home_url = http_url_path();

if (empty($action)) {
    http_location($home_url);
}

$wxlogin = kv_get('skiy_pay_thread');

//订单
if ($action == 'order') {
    //创建订单
    if ($action2 == 'create') {
        $tid = param(3);
        $uid = param(4);
    
        if (empty($tid) || empty($uid)) {
            message(0, jump('参数有误，创建订单失败', $home_url, 2));
        }

        $token = 'sign-' . $tid . '-' . $uid;
        $link = redirect('order', $token);
        http_location($link);

    //授权    
    } else if ($action2 == 'buy') {
        $p = param(3);
        $paramstr = urldecode($p);
?>
        <script type="text/javascript">
    
        var paramStr = '<?php echo $paramstr; ?>';
       var param = JSON.parse(paramStr);

           WeixinJSBridge.invoke(
               'getBrandWCPayRequest', {
                   "appId": param.appId, //公众号ID，由商户传入
                   "timeStamp": param.timeStamp, //时间戳，自1970年以来的秒数
                   "nonceStr": param.nonceStr, //随机串
                   "package": param.package,
                   "signType": param.signType, //微信签名方式：
                   "paySign": param.paySign //微信签名
               },
               function (res) {
                   if (res.err_msg == "get_brand_wcpay_request:ok") {
                       alert(111);
                   } else {
                       alert(1222);
                   }
               }
           );

       </script>
<?php

    } else if ($action2 == 'sign') {
        // $notify_url = 'https://www.gxvtc.com/123.htm'; 

        // $wx_config = array(
        //     'appid' => $wxlogin['app_id'],
        //     'appsecret' => $wxlogin['app_secret'],
        //     'mch_id' => $wxlogin['mch_id'],
        //     'mch_api_key' => $wxlogin['api_key'],
        // );
        // $wechat = new Wechat($wx_config);
        // $wx_token = $wechat->getOauthAccessToken();
        // if (empty($wx_token)) {
        //     message($wechat->errCode, $wechat->errMsg);
        // }
    
        // $access_token = $wx_token['access_token'];
        // $openid = $wx_token['openid'];            

        // $total_fee = 1;
        // $order_id = 'fdafdsafsda';
        // $body_content = 'content';

        // //生成微信订单
        // $wxpay_order_params = array(
        //     'openid' => $openid,
        //     'body' => $body_content,
        //     'out_trade_no' => $order_id,
        //     'total_fee' => $total_fee, //总价(分)
        //     'spbill_create_ip' => $_SERVER['SERVER_ADDR'], //服务器IP
        //     'notify_url' => $notify_url, //支付回调地址
        // );
          
        // $param = $wechat->PayUnifiedOrder($wxpay_order_params, true);

        // $param_json = json_encode($param);

        $param_json = '{"appId":"wx53960c78d6738ba4","timeStamp":" 1522600854","nonceStr":"7g0vy0gxp01d9r025vmqsyz45qkf267m","package":"prepay_id=wx20180402004054f6d8aa7e4d0038194473","signType":"MD5","paySign":"D73909ED2F759DDECE19271BEF9A1785"}';

        $link = http_url_path().url('pay_thread-order-buy-' . urlencode($param_json));
        http_location($link);
    }
}

/**
 * 重定向 微信授权
 */
function redirect($param1='callback', $param2='') {
    global $wxlogin;
    $wx_config = [
        'appid' => $wxlogin['app_id'],
        'appsecret' => $wxlogin['app_secret'],
    ];
    $wechat = new Wechat($wx_config);

    $uri = (! empty($param2)) ? '-' . $param2 : '';
    $return_url = http_url_path().url('pay_thread-' . $param1 . $uri);
    $link = $wechat->getOauthRedirect($return_url, '');
	return $link;
}

?>
