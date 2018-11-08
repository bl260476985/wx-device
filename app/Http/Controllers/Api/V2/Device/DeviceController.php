<?php

namespace App\Http\Controllers\Api\V2\Device;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Controller;
use App\Utils\CommonHelper;
use App\Utils\AccountHelper;
use App\Utils\NumTransNameHelper as TransHelper;
use GuzzleHttp\Client;

class DeviceController extends Controller
{
    /*
     * 设备搜索
     * @param Request $req
     * @return Response
     */
    public function search(Request $req)
    {
        parent::getBaseInfo();
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $device_type_trans = TransHelper::DEVICE_BASE_TYPE;
        $door_lock_status = TransHelper::DOOR_LOCK_STATUS;
        $offset = (int)$req->input('offset', 0);
        $limit = (int)$req->input('limit', 20);
        if ($offset < 0) $offset = 0;
        if ($limit <= 0) $limit = 20;
        $query = trim($req->input('query', ''));
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


        $devices_base = DB::table('device')->select('id', 'device_name', 'device_number', 'province_id', 'city_id', 'station_id',
            'district_id', 'address', 'longitude', 'latitude', 'provider', 'device_type', 'device_remarks', 'device_status',
            'warning_content', 'device_heart_info', 'recented_at', 'hearted_at', 'device_open_info');
        if (!empty($query)) {
            $devices_base->where('device_number', 'like', "%$query%")->orWhere('device_name', 'like', "%$query%");
        }
        $devices = $devices_base->whereIn('station_id', $stationIds)
            ->where('is_del', 0)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
        $stationIds = $devices->pluck('station_id')->all();
        $stationInfo = DB::table('station')->select('id', 'name')->whereIn('id', $stationIds)->get()->keyBy('id')->all();

        $result['list'] = $devices->map(function ($item) use ($device_type_trans, $stationInfo, $door_lock_status) {
            $module = $item;
            $module['device_type_name'] = isset($device_type_trans[$item['device_type']]) ? $device_type_trans[$item['device_type']] : '';
            $module['status_name'] = self::statusTrans($item['device_status'], $item['device_type']);//设备类型1烟感 2智能节点 3门磁 4智能锁5报警设备
            $module['station_name'] = isset($stationInfo[$item['station_id']]['name']) ? $stationInfo[$item['station_id']]['name'] : '';//所属设备组或所属线路
            //心跳
            $detail_info = empty($item['device_heart_info']) ? [] : json_decode($item['device_heart_info'], true);
            $uploaded_at = $item['hearted_at'];

            $door_status = isset($detail_info['info']['door_status']) ? $detail_info['info']['door_status'] : -1;//门状态 0开启
            $lock_status = isset($detail_info['info']['lock_status']) ? $detail_info['info']['lock_status'] : -1;//锁状态 1关闭
            //定义不同设备类型返回的多余参数
            $module['temperature'] = isset($detail_info['info']['temperature']) ? $detail_info['info']['temperature'] : '';//温度
            $module['dampness'] = isset($detail_info['info']['humidity']) ? $detail_info['info']['humidity'] : '';//湿度
            $module['beam'] = isset($detail_info['info']['light']) ? $detail_info['info']['light'] . '%' : '';//光照
            $module['inundate'] = (isset($detail_info['info']['water']) && $detail_info['info']['water'] == 1) ? '是' : '否';//是否浸水 是 否
            $module['real_voltage'] = !empty($item['voltage']) ? $item['voltage'] . 'v' : '';//实时电压
            $module['real_quantity'] = isset($detail_info['quantity']) ? $detail_info['quantity'] : '';//电量
            $module['signal'] = isset($detail_info['info']['signal']) ? $detail_info['info']['signal'] : '';//信号
            $module['door_status'] = isset($door_lock_status[$door_status]) ? $door_lock_status[$door_status] : '';
            $module['lock_status'] = isset($door_lock_status[$lock_status]) ? $door_lock_status[$lock_status] : '';
            $module['warning_type'] = isset($detail_info['warning_type']) ? $detail_info['warning_type'] : '';//报警类型或停电类型
            $module['warning_content'] = $item['warning_content'];//报警内容或停电原因
            $module['uploaded_at'] = $uploaded_at;//最后上报时间或停电时间
            unset($module['device_status']);
            unset($module['hearted_at']);
            unset($module['recented_at']);
            unset($module['device_heart_info']);
            unset($module['device_open_info']);
            return $module;
        })->all();
        return $this->success($result);
    }

    /**获取设备详情
     * @param Request $req
     * @return Response
     */

    public function get(Request $req)
    {
        $device_type_trans = TransHelper::DEVICE_BASE_TYPE;
        $id = (int)$req->input('id', 0);//设备id
        if (!isset($id) || empty($id)) {
            return $this->fail('缺少设备id参数');
        }
        $device = CommonHelper::isDataExists('device', 'id', $id)->select('id', 'device_name', 'device_number',
            'address', 'longitude', 'latitude', 'provider', 'device_type', 'device_remarks', 'pic_ids', 'open_push', 'open_type')
            ->where('is_del', 0)
            ->first();
        if (!empty($device)) {
            $device['device_type_name'] = isset($device_type_trans[$device['device_type']]) ? $device_type_trans[$device['device_type']] : '';
            $device['pic_urls'] = [];
            if (!empty($device['pic_ids'])) {
                $pic_ids = explode(',', $device['pic_ids']);
                $pic_ids = collect($pic_ids)->unique()->all();
                if (count($pic_ids) > 0) {
                    $device['pic_urls'] = DB::table('device_pic')->select('id', 'url')
                        ->whereIn('id', $pic_ids)
                        ->where('is_del', 0)
                        ->get()
                        ->map(function ($item) {
                            $module = $item;
                            $module['real_url'] = Config::get('app.pic_url') . $item['url'];
                            unset($module['url']);
                            return $module;
                        })->all();
                }
            }
            //获取配置时间
            $device['open_config'] = DB::table('push_config')->select('name', 'value')->where('type', 1)->where('is_del', 0)->get()->toArray();

            unset($device['pic_ids']);
        }
        return $this->success($device);

    }

    /**状态转换
     * @param $device_status
     * @param $device_type
     */
    private static function statusTrans($device_status, $device_type)
    {//设备类型默认1烟感 2智能节点 3门磁 4智能锁5断路检测仪
        //设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
        $status_name = '未知';
        if ($device_status == 2) {
            if (in_array($device_type, [1, 2, 4, 5])) {
                $status_name = '正常';
            } else if (in_array($device_type, [3])) {
                $status_name = '关闭';//门磁关闭
            }
        } else if ($device_status == 3) {
            if (in_array($device_type, [1, 4, 5])) {
                $status_name = '报警';
            } else if (in_array($device_type, [2])) {
                $status_name = '异常';
            } else if (in_array($device_type, [3])) {
                $status_name = '开启';//门磁关闭
            }
        } else if ($device_status == 4) {
            $status_name = '故障中';
        } else if ($device_status == 5) {
            $status_name = '操作中';
        } else if ($device_status == 6) {
            $status_name = '离线中';
        }
        return $status_name;
    }

    /**
     * @param Request $req
     * @return Response
     */

    public function update(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $enterpriseMapIds = $this->enterpriseMapIds;
        $enterpriseMap = $this->enterpriseMap;
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $id = (int)$req->input('id', 0);//设备id
        $name = trim($req->input('device_name', ''));
        $address = trim($req->input('address', ''));
        $pic_ids = trim($req->input('pic_ids', ''));//图片id用逗号隔开
        $longitude = trim($req->input('longitude', ''));//经度
        $latitude = trim($req->input('latitude', ''));//纬度
        $remark = trim($req->input('provider', ''));//备注

        if (!isset($id) || empty($id)) {
            return $this->fail('缺少设备id参数');
        }
        if (!isset($name) || empty($name)) {
            return $this->fail('设备名称不能为空');
        }
        if (empty($longitude) || empty($latitude)) {
            return $this->fail('经纬度信息不能为空');
        }
        $device = CommonHelper::isDataExists('device', 'id', $id)->where('is_del', 0)->first();
        if (empty($device)) {
            return $this->fail('此设备不存在');
        }
        $fromStation = CommonHelper::isDataExists('station', 'id', $device['station_id'])->where('is_del', 0)->first();
        if (empty($fromStation)) {
            return $this->fail('设备组不存在');
        }
        if (!AccountHelper::canManageTheEnterprise($enterpriseMap, $userInfo, $fromStation['enterprise_id'])) {
            return $this->fail('您没有权限编辑此设备');
        }
        $is_fail = 0;
        $province = '';
        $city = '';
        $district = '';
        $province_id = 0;
        $city_id = 0;
        $district_id = 0;
        if ($address != $device['address']) {
            //调取腾讯api获取用户输入地址的详细信息
            try {
                $res_json = AccountHelper::positionGet($latitude, $longitude);
                if (!empty($res_json)) {
                    if (isset($res_json['status']) && $res_json['status'] == 0) {
                        $ad_info = isset($res_json['ad_info']) ? $res_json['ad_info'] : '';
                        $district = isset($ad_info['district']) ? $ad_info['district'] : $device['district'];
                        $city = isset($ad_info['city']) ? $ad_info['city'] : $device['city'];
                        $province = isset($ad_info['province']) ? $ad_info['province'] : $device['province'];
                        $district_id = isset($ad_info['adcode']) ? $ad_info['adcode'] : (int)$device['district_id'];
                        //获取对应的市id 和 省份 id
                        $area_info = AccountHelper::adinfoGet($district_id);
                        $district_id = isset($area_info['district_id']) ? (int)$area_info['district_id'] : (int)$device['district_id'];
                        $city_id = isset($area_info['city_id']) ? (int)$area_info['city_id'] : (int)$device['city_id'];
                        $province_id = isset($area_info['province_id']) ? (int)$area_info['province_id'] : (int)$device['province_id'];
                    }
                }
            } catch (\Exception $e) {
                logger('wx mini latitude longitude translate error');
                $is_fail = 1;
            }
        }
        $update = [
            'device_name' => $name,
            'address' => $address,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'pic_ids' => $pic_ids,
            'provider' => $remark
        ];
        if ($address != $device['address'] && $is_fail == 0) {
            $update['province_id'] = $province_id;
            $update['city_id'] = $city_id;
            $update['district_id'] = $district_id;
            $update['province'] = $province;
            $update['city'] = $city;
            $update['district'] = $district;
        }

        DB::table('device')->where('id', $id)->update($update);

        return $this->success();

    }

    /**上传图片
     * @param Request $req
     * @return
     */
    public function uploadPic(Request $req)
    {
        $pic_id = 0;
        if (!$req->hasFile('pic_label')) {
            return $this->fail('缺少文件参数');
        }
        $file = $req->file('pic_label');
        if (!empty($file)) {
            $year_m = date('Y-m-d');
            $array = explode('-', $year_m);
            $folder = $array[0] . '/' . $array[1] . '/' . $array[2];
            $dirpath = base_path() . '/public/uploads/' . $folder;
            $this->createFolder($dirpath);
            $realPath = $file->getRealPath();//临时文件的绝对路径
            $extension = $file->getClientOriginalExtension();//原始文件的后缀名
            $newName = date('YmdHis') . mt_rand(100, 999) . '.' . $extension;
            $path = $file->move($dirpath, $newName);
            $filepath = 'uploads/' . $folder . '/' . $newName;
            if ($path) {
                $pic_id = parent::getUid();
                $insert = [
                    'id' => $pic_id,
                    'url' => $filepath,
                ];
                DB::table('device_pic')->insert($insert);
            }
        } else {
            return $this->fail('图片上传失败');
        }
        if (empty($pic_id)) {
            $this->fail('图片上传失败');
        }
        return $this->success(['pic_id' => $pic_id]);
    }

    /*
    * 获取操作日志
    * @param Request $req
    * @return Response
    */
    public function searchLog(Request $req)
    {
        $id = (int)$req->input('device_id', 0);
        $result = ['list' => []];

        $device = DB::table('device')->select('id', 'device_name', 'device_type')
            ->where('id', $id)
            ->where('is_del', 0)
            ->first();
        if (!empty($device)) {
            if ($device['device_type'] == 4) {
                $logs = DB::table('device_operate_log')->select('id', 'content', 'operator_name', 'type', 'created_at')
                    ->where('device_id', $id)
                    ->where('is_del', 0)
                    ->orderBy('created_at', 'desc')
                    ->get();
                $result['list'] = $logs->map(function ($item) {
                    $module = $item;
                    $module['type_name'] = $item['type'] == 1 ? '网页端' : '移动端';
                    unset($module['type']);
                    return $module;
                })->all();
            }
        }

        return $this->success($result);
    }

    /**
     * 创建目录
     * @param $path
     */
    public function createFolder($path)
    {
        if (!file_exists($path)) {
            $this->createFolder(dirname($path));
            mkdir($path, 0777);
        }
    }

    /**设置推送方式
     * @param Request $req
     * @return Response
     */

    public function pushSet(Request $req)
    {
        $id = (int)$req->input('id', 0);//设备id
        $open_push = (int)($req->input('open_push', ''));//1打开0为关闭
        $open_type = (int)($req->input('open_type', ''));//-1为手动开启大于0则为系统开启存储秒为单位
        if (!isset($id) || empty($id)) {
            return $this->fail('缺少设备id参数');
        }
        if (empty($open_type)) {
            return $this->fail('缺少开启方式参数');
        }
        $device = CommonHelper::isDataExists('device', 'id', $id)->select('id', 'open_push')
            ->where('is_del', 0)
            ->first();
        if (empty($device)) {
            return $this->fail('设备不存在');
        }
        $update = [
            'open_push' => $open_push,
            'open_type' => $open_type,
            'open_update_at' => date('Y-m-d H:i:s')
        ];
        DB::table('device')->where('id', $id)->update($update);
        info('wixin pushSet data:', $update);
        return $this->success($device);

    }

    /**
     * unlock 智能锁远程开锁
     * @param  Request $req
     * @return Response
     */
    public function unlock(Request $req)
    {
        parent::getBaseInfo();
        //获取查看归属自己的用户
        $userInfo = $this->curAdminUser;//获取系统用户基本信息
        $cur_id = $userInfo['id'];
        $cur_name = $userInfo['name'];
        $id = (int)$req->input('id', 0);//设备id
        if (empty($id)) {
            return $this->fail('设备id不能为空');
        }
        $device = DB::table('device')->select('id', 'device_number')
            ->where('id', $id)
            ->where('is_del', 0)
            ->first();
        if (empty($device)) {
            return $this->fail('此设备不存在');
        } else if (empty($device['device_number'])) {
            return $this->fail('此设备串号为空');
        }
        $did = trim($device['device_number']);
        $url = 'http://101.89.137.126:10223/api/machine/sendOpenCommand?did=' . $did . '&imei_flg=true';
        try {
            $client = new Client(['timeout' => 15]);
            $res = $client->request('GET', $url, [
                'timeout' => 15,
                'connect_timeout' => 2,
            ]);
            if ((int)$res->getStatusCode() !== 200) {
                return $this->fail('unlock response code error');
            }
            $body = json_decode(trim((string)$res->getBody()), true);
            $insert = [
                'id' => parent::getUid(),
                'device_id' => $id,
                'type' => 2,
                'operator_id' => $cur_id,
                'operator_name' => $cur_name,
            ];
            if ($body == '201') {
                //解锁成功，记录入库
                $insert['content'] = '开启智能锁成功';
                DB::table('device_operate_log')->insert($insert);
            } else {
                //插入开锁失败的记录
                $insert['content'] = '开启智能锁失败';
                DB::table('device_operate_log')->insert($insert);
            }
        } catch (\Exception $e) {
            return $this->fail('unlock fail');
        }

        return $this->success();
    }


}