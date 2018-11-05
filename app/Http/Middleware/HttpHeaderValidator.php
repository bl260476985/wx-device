<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Cache;
use App\Utils\VarStore;
use Closure;
use Illuminate\Support\Facades\DB;

class HttpHeaderValidator extends Validator
{

    public function __construct()
    {
        $this->responseCode = 5000;
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
        $sessionId = trim($request->header('SESSIONID', ''));
        if (empty($sessionId)) {
            return $this->fail('缺少必要的请求参数');
        }
        $login = Cache::store('redis')->get('WXMINI_TOKEN_INFO_' . $sessionId);
        $tem_openid = '';
        if ($login !== null && is_array($login)) {
            $tem_openid = isset($login['open_id']) ? $login['open_id'] : '';
            if (empty($tem_openid)) {
                return $this->fail('缺少必要的openid参数');
            }
            $user_admin = DB::table('wx_user')->select('user_id')->where('open_id', $tem_openid)->where('type', 2)->where('is_del', 0)->first();
            if (!empty($user_admin)) {
                $login['user_id'] = $user_admin['user_id'];
            } else {
                $login['user_id'] = 0;
            }
            VarStore::put('wxmini_user', $login);
        } else {
            $this->responseCode = 5001;
            return $this->fail('token已失效，请重新获取');
        }

        info($request->method() . ' ' . $request->path() . ' ' . $request->header('SESSIONID', '') . ' ' . $request->ip() . ' ' . $tem_openid, $request->all());

        $authorization = trim($request->header('Authorization', ''));
        $authorization = substr($authorization, strpos($authorization, ' '));
        $parts = explode(':', base64_decode($authorization));
        if (count($parts) !== 2) {
            return $this->fail('请求参数错误');
        }
        $name = trim($parts[0]);
        $sign = trim($parts[1]);
        $random = trim($request->header('r', ''));
        if (empty($name) || empty($sign) || empty($random)) {
            return $this->fail('请求参数错误');
        }
        logger('wxmini request api name:' . $name . ',sign:' . $sign . ',random:' . $random);
        return $next($request);
    }

}
