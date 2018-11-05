<?php

namespace App\Http\Controllers\Api\V1\DeviceClient;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Utils\AccountHelper;
use App\Utils\VarStore;

class DeviceController extends Controller
{

    /** 继承Controller 为了获取到openid
     * login
     * @param  Request $req
     * @return Response
     */
    public function login(Request $req)
    {
        $sessionId = trim($req->header('SESSIONID', ''));
        $resdata = VarStore::get('wxpublic_user');
        $data = json_decode(trim($req->input('content', '')), true);
        if (empty($data)) {
            return $this->fail('数据格式不对');
        }
        if (!isset($data['name']) || empty($data['name'])) {
            return $this->fail('请输入用户名');
        }
        if (!isset($data['passwd']) || empty($data['passwd'])) {
            return $this->fail('请输入密码');
        }
        $name = trim($data['name']);
        $pwd = trim($data['passwd']);

        $parts = explode(':', base64_decode(substr($pwd, strpos($pwd, ' '))));
        if (!is_array($parts)) {
            return $this->fail('密码解析失败');
        }
        $pwd = isset($parts[1]) ? $parts[1] : '';

        $user = DB::table('system_user')->select('id')->where('name', $name)->where('is_del', 0)->first();
        if (empty($user)) {
            return $this->fail('系统账户不存在');
        }
        $authen = DB::table('system_user_authen')->select('id', 'name', 'seed', 'pass')->where('user_id', $user['id'])->where('is_del', 0)->first();
        if (empty($authen) || (AccountHelper::encryptPassword($pwd, $authen['seed']) != $authen['pass'])) {
            return $this->fail('用户名或密码错误');
        }
        try {
            DB::transaction(function () use ($resdata, $user, $sessionId) {
                $openId = isset($resdata['open_id']) ? $resdata['open_id'] : '';
                //更新数据库
                if (!empty($openId)) {
                    DB::table('wx_user')->where('open_id', $openId)->where('type', 1)->where('is_del', 0)->update([
                        'user_id' => $user['id'],
                    ]);
                    //更新缓存数据
                    if (isset($resdata['user_id'])) {
                        $resdata['user_id'] = $user['id'];
                    }
                    Cache::store('redis')->forever('WXPUBLIC_REGISTER_' . $sessionId, $resdata);
                }
            });
        } catch (\Exception $e) {
            $resmsg = '系统账户绑定失败，请重试!';
        };
        return $this->success();
    }
}