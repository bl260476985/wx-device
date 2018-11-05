<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\VarStore;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Utils\AccountHelper;
use Illuminate\Support\Facades\Event;
use App\Utils\WXBizDataCrypt;


class WelcomeController extends BaseApiController
{
    //EncodingAESKey=hIBHFPsTPoSUewvPHLjy8kRgDeSlkj9l1OCkQs0cQ0K
    private static $APPID = 'wx7a2aed74a129a0eb';      // 公众号APPID;
    private static $SECRET = 'db8797a99b7d501c9ab9cc3a843e5786'; // '公众号SECRET';
    private static $DOMAIN = 'http://xiao.nbiotsg.com';    // 'http://wx.anyocharging.com';
    private static $XCXAPPID = 'wx7b8e09f78e0272e7';      // 小程序APPID;
    private static $XCXSECRET = 'a6cdd7e0ef0e3814d8b9e721cba349f2'; // '小程序SECRET';

    public function __construct(Request $req)
    {
        parent::__construct($req);
        self::$APPID = Config::get('app.wx_id');
        self::$SECRET = Config::get('app.wx_secret');
        self::$DOMAIN = Config::get('app.url');
        self::$XCXAPPID = Config::get('app.wx_xcx_id');
        self::$XCXSECRET = Config::get('app.wx_xcx_secret');
    }

    /**
     * index
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
    {
        echo 'welcome';
    }

    public function load(Request $req)
    {
        echo file_get_contents(storage_path() . '/app/wxdata');
    }

    public function save(Request $req)
    {
        file_put_contents(storage_path() . '/app/wxdata', $req->input('data'));
    }

    public function notify(Request $req)
    {
        $signature = trim($req->input('signature', ''));
        $timestamp = trim($req->input('timestamp', ''));
        $nonce = trim($req->input('nonce', ''));
        $echo = trim($req->input('echostr', ''));

        $token = 'Sirius2018';
        $arr = [$token, $timestamp, $nonce];
        sort($arr, SORT_STRING);
        $str = implode($arr);
        $str = sha1($str);

        if ($str !== $signature) {
            return 'error';
        }
        if ($echo !== '') {
            return $echo;
        }
        $xml = simplexml_load_string($req->getContent());
        $openId = $xml->FromUserName;
        switch (trim($xml->MsgType)) {
            case 'event':
                $event = $xml->Event;
                if ($event == 'subscribe') {
                    //关注事件
                    return $this->buildSubscribeResponse($xml->ToUserName, $xml->FromUserName);
                } else if (trim($xml->EventKey) == 'NEST_CONNECTION') {
                    return $this->buildClickResponse($xml->ToUserName, $xml->FromUserName);
                } else if (trim($xml->EventKey) == 'NEST_AFTER_SALE') {
                    return $this->buildSaleResponse($xml->ToUserName, $xml->FromUserName);
                } else if ($event == 'TEMPLATESENDJOBFINISH') {
                    //推送模板消息的反馈
                    info('warning notice response: MsgID=' . trim($xml->MsgID) . ' Status=' . trim($xml->Status));
                }
                break;
            default:
                return $this->buildClickResponse($xml->ToUserName, $xml->FromUserName);
                break;
        }

    }

    private function buildSubscribeResponse($fromUserName, $toUserName)
    {

        $content = '你好，欢迎关注蜂巢智网,更多功能敬请期待!';
        $now = time();

        $str = '<xml>';
        $str .= '<ToUserName><![CDATA[' . $toUserName . ']]></ToUserName>';
        $str .= '<FromUserName><![CDATA[' . $fromUserName . ']]></FromUserName>';
        $str .= '<CreateTime>' . $now . '</CreateTime>';
        $str .= '<MsgType><![CDATA[text]]></MsgType>';
        $str .= '<Content><![CDATA[' . $content . ']]></Content>';
        $str .= '</xml>';
        return $str;
    }

    private function buildClickResponse($fromUserName, $toUserName)
    {

        $content = '账户绑定功能已上线!';
        $now = time();

        $str = '<xml>';
        $str .= '<ToUserName><![CDATA[' . $toUserName . ']]></ToUserName>';
        $str .= '<FromUserName><![CDATA[' . $fromUserName . ']]></FromUserName>';
        $str .= '<CreateTime>' . $now . '</CreateTime>';
        $str .= '<MsgType><![CDATA[text]]></MsgType>';
        $str .= '<Content><![CDATA[' . $content . ']]></Content>';
        $str .= '</xml>';
        return $str;
    }

    private function buildSaleResponse($fromUserName, $toUserName)
    {
        $content = "售后质量咨询：刘笑宇\n";
        $content .= "手机：18813138894\n";
        $content .= "邮箱：lxy@nbiotemtc.com";
        $now = time();

        $str = '<xml>';
        $str .= '<ToUserName><![CDATA[' . $toUserName . ']]></ToUserName>';
        $str .= '<FromUserName><![CDATA[' . $fromUserName . ']]></FromUserName>';
        $str .= '<CreateTime>' . $now . '</CreateTime>';
        $str .= '<MsgType><![CDATA[text]]></MsgType>';
        $str .= '<Content><![CDATA[' . $content . ']]></Content>';
        $str .= '</xml>';
        return $str;
    }

    public function action(Request $req)
    {
        $code = trim($req->input('code', ''));
        $state = trim($req->input('state', ''));

        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . self::$APPID . '&secret=' . self::$SECRET . '&code=' . $code . '&grant_type=authorization_code';
        $client = new Client(['timeout' => 10]);
        $res = $client->request('POST', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            logger('request oauth error');
            return;
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            logger('request oauth error:' . $body['errcode'] . ',' . $body['errmsg']);
            return;
        }
        $user_data = [];
        $user_data = $this->login($body['openid']);
        $uri = '';
        $rand = (int)date('YmdHis');
        switch ($state) {
            case 'login':
                $uri = 'login.html';
                $type = 1;
                break;
            default:
                return '参数错误';
                break;
        }
        $url = self::$DOMAIN . '/templates/' . $uri . '#?binduser=' . $user_data['binduser'] . '&token=' . $user_data['sessionId'] . '&' . $rand;
        return redirect()->away($url);
    }

    private function login($openId)
    {
        $user_data = [
            'binduser' => '',
            'sessionId' => '',
        ];
        $sessionId = AccountHelper::createSession($openId, 'WXPUBLIC_REGISTER_');
        $wx_user = Cache::store('redis')->rememberForever('WXPUBLIC_REGISTER_' . $sessionId, function () use ($openId) {
            $wx_user = DB::table('wx_user')->where('open_id', $openId)->where('is_del', 0)->first();
            if (empty($wx_user)) {
                DB::table('wx_user')->insert([
                    'id' => parent::getUid(),
                    'open_id' => $openId,
                    'type' => 1,
                    'status' => 2,
                    'user_id' => 0,
                    'phone' => '',
                    'is_del' => 0,
                ]);
            }
            return $wx_user = DB::table('wx_user')->where('open_id', $openId)->where('is_del', 0)->first();
        });
        if (!empty($wx_user['user_id'])) {
            $user = DB::table('system_user')->select('name')->where('id', $wx_user['user_id'])->where('is_del', 0)->first();
            $user_data['binduser'] = isset($user['name']) ? $user['name'] : '';
        }
        $user_data['sessionId'] = $sessionId;

        return $user_data;
    }

    public function createMenu(Request $req)
    {
        $info = Cache::store('redis')->get('FROG_WEIXIN_ACCESS_TOKEN');
        if ($info === null) {
            return $this->fail('创建失败');
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $info['token'];
        $actions = ['login'];
        $urls = [];
        foreach ($actions as $action) {
            $urls[$action] = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . self::$APPID . '&redirect_uri=' . urlencode(self::$DOMAIN . '/action') . '&response_type=code&scope=snsapi_base&state=' . $action . '#wechat_redirect';
        }
        $req = [
            'button' => [
                [
                    'name' => '认识蜂巢',
                    'sub_button' => [
                        [
                            'type' => 'view',
                            'name' => '走进蒽帛',
                            'url' => 'https://mp.weixin.qq.com/s/VP0CbI_JtOwgkdOPYWstKw',
                        ],
                        [
                            'type' => 'view',
                            'name' => '资讯中心',
                            'url' => 'http://mp.weixin.qq.com/mp/homepage?__biz=MzUyNTQ3Nzk2NQ==&hid=5&sn=6c15727bf1881f9aea71946d62d4e102&scene=18#wechat_redirect',
                        ],
                        [
                            'type' => 'view',
                            'name' => '认识物联',
                            'url' => 'http://v7.rabbitpre.com/m/3iquMjf?lc=2&sui=ntTwJXcW&from=timeline&isappinstalled=0&mobile=1',
                        ],
                    ],
                ],
                [
                    'name' => '产品魅力',
                    'sub_button' => [
                        [
                            'type' => 'view',
                            'name' => '产品中心',
                            'url' => 'http://mp.weixin.qq.com/mp/homepage?__biz=MzUyNTQ3Nzk2NQ==&hid=3&sn=972481b4efbf021962da4d9b62dc3677&scene=18#wechat_redirect',
                        ],
                        [
                            'type' => 'view',
                            'name' => '解决方案',
                            'url' => 'http://mp.weixin.qq.com/mp/homepage?__biz=MzUyNTQ3Nzk2NQ==&hid=4&sn=fd4517b7356f1965b75eeb1c2dc76159&scene=18#wechat_redirect',
                        ],
                    ],
                ],
                [
                    'name' => '蜂巢服务',
                    'sub_button' => [
                        [
                            'type' => 'view',
                            'name' => '账户绑定',
                            'url' => $urls['login'],
                        ],
                        [
                            'type' => 'view',
                            'name' => '联系我们',
                            'url' => 'https://mp.weixin.qq.com/s/TiZgd2KkWj6_0ILpygrW-g',

                        ],
                        [

                            'type' => 'click',
                            'name' => '售后服务',
                            'key' => 'NEST_AFTER_SALE',

                        ],
                    ],
                ],
            ],
        ];
        $client = new Client(['timeout' => 10]);
        $res = $client->request('POST', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
            'body' => json_encode($req, JSON_UNESCAPED_UNICODE),
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            logger('request create menu error');
            return;
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        // print_r($body);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            logger('request create menu error:' . $body['errcode'] . ',' . $body['errmsg']);
            return;
        }
        info('create menu success');
    }

    public function refreshAccessToken(Request $req)
    {
        $info = Cache::store('redis')->get('FROG_WEIXIN_ACCESS_TOKEN');
        if ($info === null || $info['expired'] <= time()) {
            info('try to refresh access token');
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . self::$APPID . '&secret=' . self::$SECRET;
            $client = new Client(['timeout' => 10]);
            $res = $client->request('GET', $url, [
                'timeout' => 10,
                'connect_timeout' => 2,
            ]);
            if ((int)$res->getStatusCode() !== 200) {
                logger('request access token error');
                return;
            }
            $body = json_decode(trim((string)$res->getBody()), true);
            if (isset($body['errcode']) && $body['errcode'] !== 0) {
                logger('request access token error:' . $body['errcode'] . ',' . $body['errmsg']);
                return;
            }
            $expired = $body['expires_in'] - 360;
            $info = ['token' => $body['access_token'], 'expired' => time() + $expired];
            Cache::store('redis')->put('FROG_WEIXIN_ACCESS_TOKEN', $info, (int)($expired / 60));
            info('new access token:' . $info['token']);
            $this->refreshJSAPITicket($info['token']);
            echo 'updated';
        } else {
            echo 'nothing';
        }
    }

    private function refreshJSAPITicket($accessToken)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $accessToken . '&type=jsapi';
        $client = new Client(['timeout' => 10]);
        $res = $client->request('GET', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            logger('request jsapi ticket error');
            return;
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            logger('request jsapi ticket error:' . $body['errcode'] . ',' . $body['errmsg']);
            return;
        }
        $expired = $body['expires_in'] - 360;
        $info = ['ticket' => $body['ticket'], 'expired' => time() + $expired];
        Cache::store('redis')->put('FROG_WEIXIN_JSAPI_TICKET', $info, (int)($expired / 60));
        info('new jsapi ticket:' . $info['ticket']);
    }


    public function miniAction(Request $req)
    {
        $code = trim($req->input('code', ''));
        if (empty($code)) {
            return $this->fail('缺少必要参数');
        }
        $code2session_url = "https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code";
        $code2session_url = sprintf($code2session_url, self::$XCXAPPID, self::$XCXSECRET, $code);
        try {
            $client = new Client(['timeout' => 100]);
            $res = $client->request('POST', $code2session_url, [
                'timeout' => 100,
                'connect_timeout' => 20,
            ]);
            if ((int)$res->getStatusCode() !== 200) {
                logger('wx mini login request openId error');
                return $this->fail('获取openid失败');
            }
            $body = json_decode(trim((string)$res->getBody()), true);
            if (isset($body['errcode']) && $body['errcode'] !== 0) {
                logger('wx mini request openId error:' . $body['errcode'] . ',' . $body['errmsg']);
                return $this->fail('请稍后重试');
            }
            //检测用户是否注册
            $user_info = $this->miniLogin($body['openid'], $body['session_key']);

            $openId = AccountHelper::appLogin($body);

        } catch (\Exception $e) {
            logger($e);
            return $this->fail('请稍后重试');
        }

        return $this->success(['token' => $openId]);
    }

    public function miniLogin($openId, $session_key)
    {
        $wx_user = Cache::store('redis')->rememberForever('WXMINI_REGISTER_' . $openId, function () use ($openId) {
            $wx_user = DB::table('wx_user')->where('open_id', $openId)->where('is_del', 0)->first();
            if (empty($wx_user)) {
                DB::table('wx_user')->insert([
                    'id' => parent::getUid(),
                    'open_id' => $openId,
                    'type' => 2,
                    'status' => 2,
                    'user_id' => 0,
                    'phone' => '',
                    'is_del' => 0,
                ]);
            }
            return $wx_user = DB::table('wx_user')->where('open_id', $openId)->where('is_del', 0)->first();
        });

        return true;
    }

    public function getPhoneNumber(Request $req)
    {

        $openId = $req->header('dwopenId');
        $openId = AccountHelper::decrypt($openId);
        $sessionInfo = Cache::store('redis')->get('XCX_DW_SESSION_KEY' . $openId);
        Log::info($sessionInfo);
        if (empty($sessionInfo)) {
            return $this->fail('获取手机号失败，请重新授权登录');
        }
        $sessionkey = $sessionInfo['session_key'];
        $wx_number = $sessionInfo['open_id'];
        $encryptedData = $req->input('encryptedData');
        $iv = $req->input('iv');
        $portNumber = $req->input('port_number');
        $res = WXBizDataCrypt::WXBizDataCrypt(self::$XCXAPPID, $sessionkey);
        $errCode = WXBizDataCrypt::decryptData($encryptedData, $iv, $data);
        if ($errCode == 0) {
            $data = json_decode($data, true);
            $phone = $data['phoneNumber'];
            Log::info($phone);
        } else {
            return $this->fail('获取手机号失败,请重新尝试');
        }

        $user = DB::table('user')->where('phone', $phone)->where('is_del', 0)->first();
        $salt = Str::random(16);
        $pwdIni = 123456;
        $pwd = AccountHelper::encryptPassword($pwdIni, $salt);
        $now = date('Y-m-d H:i:s');

        if (empty($user)) {
            $userId = DB::table('user')->insertGetId(
                [
                    'phone' => $phone,
                    'country' => '中国',
                    'face' => 'face.png',
                    'type' => 2,
                    'balance' => 0,
                    'status' => 2,
                    'user_src' => 4, //小程序注册
                    'is_del' => 0,
                    'created_at' => $now,
                    'enterprise_id' => 10001
                ]
            );
            DB::table('user_authen')->insert(
                [
                    'phone' => $phone,
                    'pass' => $pwd,
                    'seed' => $salt,
                    'user_id' => $userId,
                    'is_del' => 0,
                    'created_at' => $now,
                ]);

            $res = DB::table('wx_user')->where('open_id', $wx_number)->where('is_del', 0)->update(
                [
                    'phone' => $phone,
                    'user_id' => $userId,
                    'updated_at' => $now,
                ]);

            $sessionId = AccountHelper::login($userId, $wx_number);

//          新用户注册赠送余额
            if ($portNumber != '') {
                $stationInfo = DB::table('device_port')->select('station_id')->where('port_number', $portNumber)->where('is_del', 0)->first();

                Log::info('Sandy debug stationInfo', $stationInfo);
                $registerInfo = ActivityController::getRegisterActivity($stationInfo['station_id'], '1');
                Log::info('Sandy debug registerInfo', $registerInfo);
                if (!empty($registerInfo) && array_key_exists('register', $registerInfo)) {
                    $couponId = $registerInfo['register']['id'];
                    $start_at = $registerInfo['register']['start_at'];
                    $end_at = $registerInfo['register']['end_at'];
                    $present = $registerInfo['register']['present'];
                    $couponUserId = DB::table('coupon_user')->insertGetId([
                        'coupon_id' => $couponId,
                        'user_id' => $userId,
                        'status' => 0,
                        'begined_at' => $start_at,
                        'finished_at' => $end_at,
                        'free_total_cnt' => $present,
                        'created_at' => date('Y-m-d H:i:s'),

                    ]);

                    $info = ['user_id' => $userId, 'enterprise_id' => 10001, 'time' => time(), 'payment' => $present, 'coupon_user_id' => $couponUserId];

                    Event::fire(new Register($info));//事件
                }
            } else {
                $dbInfo = DB::table('system_conf')->select('val')->where('name', 'register_present_money')->where('is_open', 1)->where('is_del', 0)->first();
                if (!empty($dbInfo)) {
                    $info = ['user_id' => $userId, 'enterprise_id' => 10001, 'time' => time(), 'payment' => $dbInfo['val']];
                    Event::fire(new Register($info));//事件

                }
            }
            /*读取数据库注册赠送金额
              $dbInfo = DB::table('system_conf')->select('val')->where('name','register_present_money')->where('is_open',1)->where('is_del',0)->first();
              $info = ['user_id'=>$userId,'enterprise_id'=>10001,'time'=>time(),'payment'=>$dbInfo['val']];
              Event::fire(new Register($info));//事件*/
        } else {
            $res = DB::table('wx_user')->where('open_id', $wx_number)->where('is_del', 0)->update(
                [
                    'phone' => $phone,
                    'user_id' => $user['id'],
                    'updated_at' => $now,
                ]);
            $sessionId = AccountHelper::login($user['id'], $wx_number);
        }

        return $this->success($sessionId);

    }


    public function refreshMiniproAccessToken(Request $req)
    {

        Log::info('try to refresh minipro access token');
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . self::$XCXAPPID . '&secret=' . self::$XCXSECRET;
        $client = new Client(['timeout' => 10]);
        $res = $client->request('GET', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            Log::warning('request access token error');
            return;
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            Log::warning('request access token error:' . $body['errcode'] . ',' . $body['errmsg']);
            return;
        }
        $expired = $body['expires_in'] - 360;
        $info = ['token' => $body['access_token'], 'expired' => time() + $expired];
//        Cache::forget('FROG_MINIPRO_ACCESS_TOKEN');
        Cache::store('redis')->put('FROG_MINIPRO_ACCESS_TOKEN', $info, (int)($expired / 60));
        Log::info('new access token:' . $info['token']);
        echo 'updated';

    }


}
