<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Utils\VarStore;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class BaseApiController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * __construct
     * @param Request $req
     */
    public function __construct(Request $req)
    {
        info($req->method() . ' ' . $req->path() . ' ' . $req->header('SESSIONID', '') . ' ' . $req->ip() . ' ' . VarStore::get('dw_userId'), $req->all());

    }

    protected function getBaseInfo()
    {
        //获取当前用户ID
        $userId = VarStore::get('dw_userId');
        if ($userId > 0) {
            $existedUser = DB::table('user')->where('id', $userId)->where('is_del', 0)->first();
        }
    }

    /**
     * build the JSON response
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function buildJSONResponse($code = 0, $msg = '', $data = [])
    {
        return response()->json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * success
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function success($data = [])
    {
        return $this->buildJSONResponse(0, '', $data);
    }

    /**
     * fail
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function fail($msg, $data = [])
    {
        return $this->buildJSONResponse(1, $msg, $data);
    }

    /**
     * redirect
     * @param  string $url
     * @return \Illuminate\Http\Response
     */
    protected function redirect($url)
    {
        return $this->buildJSONResponse(2, '', ['url' => $url]);
    }

    /**
     * @param $param
     * @return array|string
     *
     */
    protected function Dealparam($param)
    {
        $len = strlen($param);

        if ($len < 3) {
            $time = date('Y-m-d H:i:s');
            return 'fail';
        }

        $str_array = explode(',', substr($param, 1, $len - 2));

        return $str_array;


    }

    /**
     * 根据分配长度获取id
     * @param $len
     * @return string
     */
    protected function getUid($len = 9)
    {
        $data = Uuid::uuid1('ef8f9cb');
        $id = $data->getInteger()->getValue();
        $id = substr($id, 0, $len);
        return $id;
    }

}
