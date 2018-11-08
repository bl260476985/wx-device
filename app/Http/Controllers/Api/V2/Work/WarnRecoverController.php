<?php

namespace App\Http\Controllers\Api\V2\Work;

use App\Utils\CommonHelper;
use App\Utils\OperateRecordHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Utils\AccountHelper;
use App\Utils\KeySorter;
use App\Utils\VarStore;
use App\Utils\NumTransNameHelper as TransHelper;

class WarnRecoverController extends Controller
{
    /**
     * search 实时统计报警信息
     * @param  Request $req
     * @return
     */
    public function warnSearch(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $warning_type = TransHelper::WARNING_TYPE;
        $offset = (int)$req->input('offset', 0);
        $limit = (int)$req->input('limit', 20);
        if ($offset < 0) $offset = 0;
        if ($limit <= 0) $limit = 20;
        $is_limit = 0;//是否增加绑定限制
        if ($userInfo['type'] == 2) {
            $is_limit = 1;
        }
        $cur_id = $userInfo['id'];
        $result = ['list' => []];
        $stationIds = DB::table('station')->select('id')->when(!empty($is_limit), function ($query) use ($cur_id) {
            return $query->where('bind_user_id', $cur_id);
        })->whereIn('enterprise_id', $enterpriseMapIds)
            ->where('is_del', 0)
            ->get()
            ->pluck('id')
            ->all();
        $deviceIds = DB::table('device')->select('id')->whereIn('station_id', $stationIds)->get()->pluck('id')->all();
        //获取报警信息
        $warns = DB::table('device_warning')->select('id', 'warning_content', 'uploaded_at')
            ->whereIn('device_id', $deviceIds)
            ->whereIn('fault_type', [1, 2, 5])
            ->where('type', 1)
            ->where('is_del', 0)
            ->orderBy('uploaded_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result['list'] = $warns->map(function ($item) use ($warning_type) {
            $module = $item;
            $module['type_name'] = '设备报警';
            return $module;
        })->all();

        return $this->success($result);
    }

    /**
     * get 获取单个报警信息
     * @param  Request $req
     * @return
     */
    public function get(Request $req)
    {
        $id = (int)$req->input('warning_id', 0);

        //获取报警信息
        $warn = DB::table('device_warning')->select('id', 'device_id', 'device_number', 'fault_type', 'type', 'warning_content', 'detail', 'uploaded_at', 'warn_status')
            ->where('id', $id)
            ->where('is_del', 0)
            ->first();

        if (!empty($warn)) {
            $device = DB::table('device')->select('device_name', 'longitude', 'latitude')->where('id', $warn['device_id'])->first();
            $warn['device_name'] = isset($device['device_name']) ? $device['device_name'] : '';
            $warn['longitude'] = isset($device['longitude']) ? $device['longitude'] : '';
            $warn['latitude'] = isset($device['latitude']) ? $device['latitude'] : '';
            //获取处理记录
            $records = DB::table('device_warndeal')->select('id', 'deal_type', 'user_name', 'created_at')
                ->where('warn_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();
            $warn['records'] = $records->map(function ($item) {
                $module = $item;
                $module['type_name'] = $item['deal_type'] == 2 ? '维护' : '完成';
                unset($module['deal_type']);
                return $module;
            })->all();
            unset($warn['detail']);
            unset($warn['fault_type']);
            unset($warn['type']);
        }
        return $this->success($warn);
    }

    /**
     * dealWarn 处理报警
     * @param  Request $req
     * @return
     */
    public function dealWarn(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $cur_id = $userInfo['id'];
        $cur_name = $userInfo['name'];
        $id = (int)$req->input('warning_id', 0);

        //获取报警信息
        $warn = DB::table('device_warning')->select('id', 'warn_status')
            ->where('id', $id)
            ->where('is_del', 0)
            ->first();
        if (empty($warn)) {
            return $this->fail('缺少报警id参数');
        } else if ($warn['warn_status'] == 2) {
            return $this->fail('此报警不在待处理状态');
        }
        try {
            DB::transaction(function () use ($cur_id, $id, $cur_name) {
                DB::table('device_warning')->where('id', $id)->update([
                    'warn_status' => 2
                ]);
                $warn_id = parent::getUid(); //生成唯一标识
                DB::table('device_warndeal')->insert([
                    'id' => $warn_id,
                    'warn_id' => $id,
                    'deal_type' => 1,
                    'deal_user_id' => $cur_id,
                    'user_name' => $cur_name
                ]);
            });
        } catch (\Exception $e) {
            return $this->fail('此报警处理失败');
        }
        return $this->success();
    }

}