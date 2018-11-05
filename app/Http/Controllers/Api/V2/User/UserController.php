<?php

namespace App\Http\Controllers\Api\V2\User;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Utils\AccountHelper;
use App\Utils\VarStore;

class UserController extends Controller
{

    /** 继承Controller 为了获取到openid
     * login
     * @param  Request $req
     * @return Response
     */
    public function bind(Request $req)
    {
        $resdata = VarStore::get('wxmini_user');
        $name = trim($req->input('name', ''));
        $passwd = trim($req->input('passwd', ''));

        if (!isset($name) || empty($name)) {
            return $this->fail('请输入用户名');
        }
        if (!isset($passwd) || empty($passwd)) {
            return $this->fail('请输入密码');
        }

        $user = DB::table('system_user')->select('id')->where('name', $name)->where('is_del', 0)->first();
        if (empty($user)) {
            return $this->fail('系统账户不存在');
        }
        $authen = DB::table('system_user_authen')->select('id', 'name', 'seed', 'pass')->where('user_id', $user['id'])->where('is_del', 0)->first();
        if (empty($authen) || (AccountHelper::encryptPassword($passwd, $authen['seed']) != $authen['pass'])) {
            return $this->fail('用户名或密码错误');
        }
        try {
            DB::transaction(function () use ($resdata, $user) {
                $openId = isset($resdata['open_id']) ? $resdata['open_id'] : '';
                //更新数据库
                if (!empty($openId)) {
                    DB::table('wx_user')->where('open_id', $openId)->where('type', 2)->where('is_del', 0)->update([
                        'user_id' => $user['id'],
                    ]);
                }
            });
        } catch (\Exception $e) {
            $resmsg = '系统账户登录失败，请重试!';
        };
        return $this->success();
    }

    /** 继承Controller 为了获取到openid
     * login
     * @param  Request $req
     * @return Response
     */
    public function unbind(Request $req)
    {
        $resdata = VarStore::get('wxmini_user');
        $openId = isset($resdata['open_id']) ? $resdata['open_id'] : '';
        if (!isset($openId) || empty($openId)) {
            return $this->fail('系统异常');
        }
        $user = DB::table('wx_user')->select('id')->where('open_id', $openId)->where('type', 2)->where('is_del', 0)->first();
        if (empty($user)) {
            return $this->fail('系统账户不存在');
        }
        try {
            DB::transaction(function () use ($user) {
                //更新数据库
                DB::table('wx_user')->where('id', $user['id'])->where('is_del', 0)->update([
                    'user_id' => 0,
                ]);
            });
        } catch (\Exception $e) {
            $resmsg = '系统账户退出失败，请重试!';
        };
        return $this->success();
    }

    /**
     * get current
     * @param  Request $req
     * @return
     */
    public function getCurrent(Request $req)
    {
        parent::getBaseInfo();
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        if (!empty($userInfo) && is_array($userInfo)) {
            unset($userInfo['type']);
            unset($userInfo['enterprise_id']);
            unset($userInfo['status']);
            unset($userInfo['country']);
            unset($userInfo['country_id']);
            unset($userInfo['province_id']);
            unset($userInfo['city_id']);
            unset($userInfo['district_id']);
            unset($userInfo['address']);
            unset($userInfo['province']);
            unset($userInfo['city']);
            unset($userInfo['district']);
            unset($userInfo['group_id']);
            unset($userInfo['is_del']);
            unset($userInfo['created_at']);
            unset($userInfo['updated_at']);
            unset($userInfo['deleted_at']);
            $userInfo['has_right'] = $userInfo['type'] == 2 ? 0 : 1;
        }

        return $this->success($userInfo);
    }
}