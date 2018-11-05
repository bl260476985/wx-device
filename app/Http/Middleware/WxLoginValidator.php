<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\DB;
use App\Utils\AccountHelper;
use App\Utils\VarStore;
use Closure;

class WxLoginValidator extends Validator
{

    public function __construct()
    {
        $this->responseCode = 5003;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $wxmini_user = VarStore::get('wxmini_user');
        if (!isset($wxmini_user['user_id']) || empty($wxmini_user['user_id'])) {
            return $this->fail('未登录');
        }
        $user = DB::table('system_user')->where('id', $wxmini_user['user_id'])->where('is_del', 0)->first();
        if (empty($user)) {
            return $this->fail('用户不存在');
        } else {
            AccountHelper::cachePut(1, $user['id'], $user);//对系统用户数据进行缓存
            VarStore::put('currentUserId', $user['id']);
        }
        return $next($request);
    }

}
