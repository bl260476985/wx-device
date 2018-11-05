<?php

namespace App\Utils;

final class NumTransNameHelper
{
    const STATION_BASE_STATUS = [
        1 => '使用中',
        2 => '测试中',
    ];
    const DEVICE_BASE_TYPE = [
        1 => '烟感',
        2 => '智能节点',
        3 => '门磁',
        4 => '智能锁',
        5 => '断路监测仪'
    ];
    const WARNING_TYPE = [
        1 => '电池低电量告警',
        2 => '监测故障告警',
        3 => '电池低电量恢复',
        4 => '监测故障恢复',
        5 => '设备报警',
        6 => '设备恢复',
    ];
    const DOOR_LOCK_STATUS = [
        -1 => '',
        0 => '开启',
        1 => '关闭',
    ];
}