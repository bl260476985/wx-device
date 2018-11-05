<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use App\Utils\VarStore;


final class AccountHelper
{
    const TYPE_MAP = [
        1 => 'SW_WINXIN_SYSTEMUSER_',
        2 => 'A',
        3 => 'B',
        4 => 'C',
        5 => 'D',
    ];

    /**
     * validate password
     * @param  string $pwd
     * @return boolean
     */
    public static function isPasswordValid($pwd)
    {
        $matches = [];
        if (preg_match('/^[A-Za-z0-9@&#\.]{4,16}$/', $pwd, $matches)) {
            return true;
        }
        return false;
    }

    /**
     * encrypt password
     * @param  string $pwd
     * @param  string $salt
     * @return string
     */
    public static function encryptPassword($pwd, $salt)
    {
        return md5('Sirius_Brain_' . $salt . $pwd . '_PWD');
    }

    /**
     * generate face file name
     * @return string
     */
    public static function generateFaceFileName()
    {
        $random = microtime(true) . mt_rand(1, 99999);
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 16);
    }

    /**
     * generate uuid
     * @return string
     */
    public static function generateUUID()
    {
        $random = microtime(true) . mt_rand(1, 99999);
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 32);
    }

    /**
     * generate session id
     * @param  [type] $userId    [description]
     * @param  [type] $timestamp [description]
     * @return [type]            [description]
     */
    public static function generateSESSIONID($userId, $timestamp)
    {
        $random = $userId . $timestamp;
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 32);
    }

    /**
     * do login
     * @param  array $user
     * @return
     */
    public static function login($userId, $open_id)
    {

        $timestamp = microtime(true);
        // $sessionId = self::generateSESSIONID($userId, $timestamp);
        $sessionId = md5($open_id);
        Cache::store('redis')->put('FROG_WEIXIN_LOGIN_INFO' . $sessionId, [
            'userId' => $userId,
            'timestamp' => $timestamp,
            'open_id' => $open_id
        ], 7 * 24 * 60);

        VarStore::put('sessionId', $sessionId);

        return $sessionId;
    }

    /**
     * createSession
     * @param  array $user
     * @return
     */
    public static function createSession($openId, $key)
    {
        $sessionId = self::generateSESSIONID($openId, $key);
        return $sessionId;
    }

    /**
     * do login
     * @param  array $user
     * @return
     */
    public static function appLogin($code)
    {
        $openId = $code['openid'];
        $session_key = $code['session_key'];
        $timestamp = microtime(true);
        $sessionId = self::generateSESSIONID($openId, $timestamp);
        Cache::store('redis')->put('WXMINI_TOKEN_INFO_' . $sessionId, [
            'session_key' => $session_key,
            'open_id' => $openId,
        ], 24 * 60);
        info('wxmini user(openid:' . $openId . ', session_key:' . $session_key . ') login.');
        return $sessionId;
    }

    /**
     * do logout
     * @param  array $userput
     * @return void
     */
    public static function logout($sessionId)
    {
        $userId = VarStore::get('dw_userId');
        Cache::store('redis')->forget('FROG_WEIXIN_LOGIN_INFO' . $sessionId);
        Log::info('user(id:' . $userId . ') logout.');
    }


    /**
     * generateMicro
     * @param
     * @return
     */
    public static function generateMicro()
    {
        $msectime = 0;
        list($mesc, $sec) = explode(' ', microtime());
        $msectime = sprintf('%03d', floatval($mesc) * 1000);
        return $msectime;
    }


    /**
     * generateStream
     * @param
     * @return
     */
    public static function generateStream($userId)
    {
        $userStreamId = '';
        $tempNumber = 100000000;
        $newNumber = $tempNumber + (int)$userId;
        $realNumber = substr($newNumber, -8);
        $msectime = self::generateMicro();
        $userStreamId = 'F' . date('YmdHis') . $msectime . $realNumber . mt_rand(100000, 999999);
        return $userStreamId;
    }

    // 生成序列号
    public static function sequenceNumber($userId, $time)
    {
        $orderNumber = '';
        $tempNumber = 100000000;
        $newNumber = $tempNumber + (int)$userId;
        $realNumber = substr($newNumber, -8);
        $orderNumber = date('YmdHis', $time) . $realNumber . mt_rand(100000, 999999);
        return $orderNumber;

    }

    // kafka下发充电序列号
    public static function generateKafkaSequence($userId, $time, $portId)
    {
        $sequenceNumber = '';
        $tempNumber = '100000000';
        $newUserNumber = $tempNumber . (int)$userId;
        $newPortNumber = $tempNumber . (int)$portId;
        $realUserNumber = substr($newUserNumber, -8);
        $realPortNumber = substr($newPortNumber, -8);
        $sequenceNumber = date('YmdHis', $time) . $realUserNumber . $realPortNumber;
        return $sequenceNumber;

    }

    /** 插入流水记录
     * insertStream
     * @param
     * @return
     */
    public static function insertStream($userId, $info)
    {
        $streamId = 0;
        $userStreamId = self::generateStream($userId);

        $insert = [
            'stream_number' => $userStreamId,
            'user_id' => (int)$userId,
            'enterprise_id' => (int)$info['enterprise_id'],
            'order_id' => (int)$info['order_id'],
            'order_number' => $info['order_number'],
            'action_source' => (int)$info['action_source'],
            'action_type' => (int)$info['action_type'],
            'pre_balance' => (int)$info['pre_balance'],
            'pay_balance' => (int)$info['pay_balance'],
            'cur_balance' => (int)$info['cur_balance'],
            'coupon_id' => $info['coupon_id'],
            'coupon_pay' => $info['coupon_pay'],
            'coupon_type' => $info['coupon_type'],
            'coupon_original' => $info['coupon_original'],
            'payed_at' => $info['payed_at'],
            'remarks' => $info['remarks'],
        ];
        $streamId = DB::table('balance_stream')->insertGetId($insert);

        return $streamId;
    }


    // openId加密
    public static function encrypt($openId)
    {
        $r = time() . ':' . $openId;
        $str = base64_encode($r);
        return $str;

    }

    // openId解密
    public static function decrypt($str)
    {
        $parts = explode(':', base64_decode($str));
        if (count($parts) !== 2) {
            return false;
        }
        $openId = $parts['1'];
        $sign = $parts['0'];
        return $openId;
    }

    /** 装换对应数字为字符类型
     * insertStream
     * @param
     * @return
     */

    public static function numberString($type)
    {
        $type_str = '';
        switch ($type) {
            case 1:
                $type_str = self::TYPE_MAP[1];
                break;
            case 2:
                $type_str = self::TYPE_MAP[2];
                break;
            case 3:
                $type_str = self::TYPE_MAP[3];
                break;
            default:
                break;
        }

        return $type_str;
    }


    /** 根据类型判断数据缓存是否存在
     * insertStream
     * @param
     * @return
     */

    public static function cacheHas($type, $content)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        $info = Cache::store('redis')->get($type_str);
        if (empty($info)) {
            return false;
        }
        return true;
    }

    /** 根据类型进行数据缓存
     * insertStream
     * @param
     * @return
     */

    public static function cachePut($type, $content, $data)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        if (!empty($type_str)) {
            Cache::store('redis')->forever($type_str, $data);
        }
        return true;

    }

    /** 获取用户基础数据
     * insertStream
     * @param
     * @return
     */

    public static function cacheGet($type, $content)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        $info = Cache::store('redis')->get($type_str);
        return $info;
    }

    /**
     * can manage the enterprise
     * @param array $enterpriseMap
     * @param array $opSystemUser
     * @param int $enterpriseId
     * @return boolean
     */
    public static function canManageTheEnterprise($enterpriseMap, $opSystemUser, $enterpriseId)
    {
        if ($opSystemUser['type'] == 0 || self::have($enterpriseMap, $opSystemUser['enterprise_id'], $enterpriseId)) {
            return true;
        }
        return false;
    }

    /**
     * check parent enterprise and child enterprise
     * @param array $enterpriseMap
     * @param int $parentId
     * @param int $childId
     * @return boolean
     */
    public static function have($enterpriseMap, $parentId, $childId)
    {
        if ($parentId == 0) {
            return true;
        }
        if (!isset($enterpriseMap[$parentId]) || !isset($enterpriseMap[$childId])) {
            return false;
        }

        if ($childId == $parentId) {
            return true;
        }
        $id = $childId;
        $deps = 4;
        while ($deps > 0) {
            $id = $enterpriseMap[$id]['parent_id'];
            if (!isset($enterpriseMap[$id])) {
                return false;
            }
            if ($id == $parentId) {
                return true;
            }

            $deps--;
        }
        return false;
    }

    /** 获取腾讯位置信息
     * insertStream
     * @param
     * @return
     */

    public static function positionGet($latitude, $longitude)
    {
        $res_json = [];
        $key = Config::get('app.tx_map_key');
        $location = urlencode($latitude . ',' . $longitude);
        $tecent_url = "https://apis.map.qq.com/ws/geocoder/v1/?location=$location&key=$key";
        $json = file_get_contents($tecent_url);
        $res_json = json_decode($json, true);
        return $res_json;
    }

    /** 获取本地行政区域id
     * insertStream
     * @param
     * @return
     */

    public static function adinfoGet($district_id)
    {
        $result = [
            'district_id' => 0,
            'city_id' => 0,
            'province_id' => 0,
        ];
        $infos = Cache::store('redis')->get('WXMINI_ADINFO_AREAINFO');
        if (empty($infos)) {
            //像缓存中插入行政区域数据
            $info = DB::table('area')->select('id', 'parent_id', 'level')->get()->keyBy('id')->all();
            Cache::store('redis')->forever('WXMINI_ADINFO_AREAINFO', $info);
            //再取一次
            $infos = Cache::store('redis')->get('WXMINI_ADINFO_AREAINFO');
        }
        if ($infos !== null && is_array($infos)) {
            $level = isset($infos[$district_id]['level']) ? $infos[$district_id]['level'] : 0;
            if ($level == 4) {
                $result['district_id'] = isset($infos[$district_id]['parent_id']) ? $infos[$district_id]['parent_id'] : 0;
                $result['city_id'] = isset($infos[$result['district_id']]['parent_id']) ? $infos[$result['district_id']]['parent_id'] : 0;
                $result['province_id'] = isset($infos[$result['city_id']]['parent_id']) ? $infos[$result['city_id']]['parent_id'] : 0;
            } else if ($level == 3) {
                $result['district_id'] = $district_id;
                $result['city_id'] = isset($infos[$result['district_id']]['parent_id']) ? $infos[$result['district_id']]['parent_id'] : 0;
                $result['province_id'] = isset($infos[$result['city_id']]['parent_id']) ? $infos[$result['city_id']]['parent_id'] : 0;
            } else if ($level == 2) {
                $result['district_id'] = 0;
                $result['city_id'] = $district_id;
                $result['province_id'] = isset($infos[$result['city_id']]['parent_id']) ? $infos[$result['city_id']]['parent_id'] : 0;
            } else if ($level == 1) {
                $result['district_id'] = 0;
                $result['city_id'] = 0;
                $result['province_id'] = $district_id;
            }
        }
        return $result;
    }


}