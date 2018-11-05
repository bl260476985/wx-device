<?php

namespace App\Http\Controllers\Api\V2\Index;

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

class IndexDeviceController extends Controller
{

    /**
     * search 设备数据统计
     * @param  Request $req
     * @return
     */
    public function deviceStatistics(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $device_base_type = TransHelper::DEVICE_BASE_TYPE;
        $is_limit = 0;//是否增加绑定限制
        if ($userInfo['type'] == 2) {
            $is_limit = 1;
        }
        $cur_id = $userInfo['id'];
        $result = [];
        $stationIds = DB::table('station')->select('id')->when(!empty($is_limit), function ($query) use ($cur_id) {
            return $query->where('bind_user_id', $cur_id);
        })->whereIn('enterprise_id', $enterpriseMapIds)
            ->where('is_del', 0)
            ->get()
            ->pluck('id')
            ->all();

        $deviceNums = DB::table('device')->select(DB::raw('count(id) as device_num'), 'device_type')
            ->whereIn('station_id', $stationIds)
            ->where('is_del', 0)
            ->groupBy('device_type')
            ->get()
            ->keyBy('device_type')
            ->all();
        //计算每个类型
        foreach ($deviceNums as $k => $item) {
            $result[$k]['name'] = isset($device_base_type[$k]) ? $device_base_type[$k] : '';
            $result[$k]['value'] = (int)$item['device_num'];
        }
        $sorter = new KeySorter('value', 'desc');
        $sorter->sort($result);
        $result = array_values($result);

        return $this->success($result);
    }

    /**
     * search 获取设备总数 和 工单总数
     * @param  Request $req
     * @return
     */
    public function headSearch(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息

        $is_limit = 0;//是否增加绑定限制
        if ($userInfo['type'] == 2) {
            $is_limit = 1;
        }
        $cur_id = $userInfo['id'];
        $result = [
            'device_total' => 0,
            'device_warn' => 0
        ];
        $stationIds = DB::table('station')->select('id')->when(!empty($is_limit), function ($query) use ($cur_id) {
            return $query->where('bind_user_id', $cur_id);
        })->whereIn('enterprise_id', $enterpriseMapIds)
            ->where('is_del', 0)
            ->get()
            ->pluck('id')
            ->all();

        $deviceNums = DB::table('device')->select(DB::raw('count(id) as device_num'))
            ->whereIn('station_id', $stationIds)
            ->where('is_del', 0)
            ->first();
        $result['device_total'] = isset($deviceNums['device_num']) ? (int)$deviceNums['device_num'] : 0;

        $deviceIds = DB::table('device')->select('id')->whereIn('station_id', $stationIds)->get()->pluck('id')->all();
        //获取报警信息  1为电池低电量告警2为监测故障告警3电池低电量恢复4监测故障告警恢复5设备报警6设备恢复
        $deviceWarns = DB::table('device_warning')->select(DB::raw('count(id) as device_warn'))
            ->whereIn('device_id', $deviceIds)
            ->whereIn('fault_type', [1, 2, 5])
            ->where('type', 1)
            ->where('is_del', 0)
            ->first();
        $result['device_warn'] = isset($deviceWarns['device_warn']) ? (int)$deviceWarns['device_warn'] : 0;

        return $this->success($result);
    }

    /**
     * search 实时统计报警信息
     * @param  Request $req
     * @return
     */
    public function warning(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $device_base_type = TransHelper::DEVICE_BASE_TYPE;
        $data = json_decode(trim($req->input('content', '')), true);
        if ($data === null) {
            $data = [];
        }
        if (!isset($data['order']) || !in_array($data['order'], [KeySorter::ORDER_ASC, KeySorter::ORDER_DESC])) {
            $data['order'] = KeySorter::ORDER_ASC;
        }
        $data_collection = collect($data);
        $created_st = $data_collection->get('created_st', date('Y-m-01'));
        $created_et = $data_collection->get('created_et', date('Y-m-d'));
        $st = $created_st . " 00:00:00";
        $et = $created_et . " 23:59:59";
        $is_limit = 0;//是否增加绑定限制
        if ($userInfo['type'] == 2) {
            $is_limit = 1;
        }
        $cur_id = $userInfo['id'];
        $result = [];

        for ($i = strtotime($created_st); $i <= strtotime($created_et); $i += 86400) {
            $tdate = date('Y-m-d', $i);
            $result[$tdate] = ['uploaded_at' => (string)$tdate, 'value' => 0];
        }

        $stationIds = DB::table('station')->select('id')->when(!empty($is_limit), function ($query) use ($cur_id) {
            return $query->where('bind_user_id', $cur_id);
        })->whereIn('enterprise_id', $enterpriseMapIds)
            ->where('is_del', 0)
            ->get()
            ->pluck('id')
            ->all();
        $deviceIds = DB::table('device')->select('id')->whereIn('station_id', $stationIds)->get()->pluck('id')->all();
        //获取报警信息
        $deviceWarns = DB::table('device_warning')->select(DB::raw('count(id) as device_warn,DATE_FORMAT(uploaded_at,"%Y-%m-%d") as uploaded_at'))
            ->whereIn('device_id', $deviceIds)
            ->whereIn('fault_type', [1, 2, 5])
            ->where('type', 1)
            ->whereBetween('uploaded_at', [$st, $et])
            ->where('is_del', 0)
            ->groupBy(DB::raw('DATE_FORMAT(uploaded_at,"%Y-%m-%d")'))
            ->get()
            ->keyBy('uploaded_at')
            ->all();
        foreach ($deviceWarns as $k => $item) {
            if (array_key_exists($k, $result)) {
                $result[$k]['uploaded_at'] = $k;
                $result[$k]['value'] = (int)$item['device_warn'];
            }
        }
        //如果倒叙，则重新放入一个新的数组
        if ($data['order'] == 'desc') {
            $new_res = [];
            foreach ($result as $item) {
                array_unshift($new_res, $item);
            }
            $result = $new_res;
        }

        $result = array_values($result);

        return $this->success($result);
    }


    /** 发送的版本消息
     * login
     * @param  Request $req
     * @return
     */
    public function message(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $result = [];
        $message = DB::table('version_log')->select('id', 'title', 'content', 'url', 'type', 'created_at')
            ->whereIn('enterprise_id', $enterpriseMapIds)
            ->where('is_del', 0)
            ->orderBy('created_at', 'desc')
            ->get();
        $result = $message->map(function ($item) {
            $module = $item;
            $module['created_at'] = date('Y.m.d', strtotime($item['created_at']));
            return $module;
        })->all();

        return $this->success($result);
    }

    /** 发送的版本消息
     * login
     * @param  Request $req
     * @return
     */
    public function detail(Request $req)
    {
        $id = (int)$req->input('id', 0);//消息id
        if (!isset($id) || empty($id)) {
            return $this->fail('缺少消息id参数');
        }
        $message = DB::table('version_log')->select('id', 'title', 'content', 'url', 'type', 'created_at')
            ->where('id', $id)
            ->where('is_del', 0)
            ->first();
        if (!empty($message)) {
            $message['created_at'] = date('Y.m.d', strtotime($message['created_at']));
        }
        return $this->success($message);
    }


}