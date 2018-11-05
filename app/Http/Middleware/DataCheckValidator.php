<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Cache;
use App\Utils\VarStore;
use Closure;

class DataCheckValidator extends Validator
{
    const SOURCE_TYPE = [
        'sirius' => 'DzI3ZTkxODIXUzTzZjdhZXTlOTc8lPX7',
    ];

    public function __construct()
    {
        $this->responseCode = 5000;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed  session(['key' => 'value']);
     */
    public function handle($request, Closure $next)
    {
        $sessionId = trim($request->header('SESSIONID', ''));
        if (empty($sessionId)) {
            return $this->fail('缺少必要的请求参数');
        }
        $login = Cache::store('redis')->get('WXPUBLIC_REGISTER_' . $sessionId);
        if ($login !== null && is_array($login)) {
            VarStore::put('wxpublic_user', $login);
        }
        $tem_openid = isset($login['open_id']) ? $login['open_id'] : '';
        if (empty($tem_openid)) {
            return $this->fail('缺少必要的openid参数');
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
        logger('name:' . $name . ',sign:' . $sign . ',random:' . $random);
        return $next($request);
    }
}
