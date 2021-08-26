<?php

$url = "https://apis.map.qq.com/ws/district/v1/list";

$result = curl_get($url . '?key=你的腾讯地图key');

if ($result['status'] != 0) {
    exit('请求失败');
}

foreach ($result['result'] as $k1 => $v1) {
    foreach ($v1 as $k => $v) {
        $id = $v['id'];

        $first_2  = substr($id, 0, 2);
        $middle_2 = substr($id, 2, 2);
        $last_2   = substr($id, 4, 2);

        if ($last_2 == '00') {
            if ($middle_2 == '00') {
                // 此为省
                // 添加省份
                $province_id = Db::name('tencent_map')->insertGetId([
                    'superior_id' => 0,
                    'name'        => $v['fullname'],
                    'level'       => 1,
                    'code'        => $v['id'],
                    'code2'       => $first_2
                ]);
                $array       = [
                    '北京市',
                    '天津市',
                    '上海市',
                    '重庆市'
                ];
                if (in_array($v['fullname'], $array)) {
                    // 添加直辖市同名二级市
                    Db::name('tencent_map')->insertGetId([
                        'superior_id' => $province_id,
                        'name'        => $v['fullname'],
                        'level'       => 2,
                        'code'        => $first_2 . '0100',
                        'code2'       => '01'
                    ]);
                }
            } else {
                // 此为市
                $province = Db::name('tencent_map')
                    ->where([
                        'level' => 1,
                        'code2' => $first_2
                    ])
                    ->field(['id'])
                    ->find();
                // 添加市
                Db::name('tencent_map')->insertGetId([
                    'superior_id' => $province['id'],
                    'name'        => $v['fullname'],
                    'level'       => 2,
                    'code'        => $v['id'],
                    'code2'       => $middle_2
                ]);
            }
        } else {
            // 此为区
            $province = Db::name('tencent_map')
                ->where([
                    'level' => 1,
                    'code2' => $first_2
                ])
                ->field(['id'])
                ->find();
            $city_id  = Db::name('tencent_map')
                ->where([
                    'superior_id' => $province['id'],
                    'level'       => 2,
                    'code2'       => $middle_2,
                    'add_type'    => 1
                ])
                ->value('id');
            // 如果是县级市，省直辖县/区，自治县/区，添加相同名称作为市
            if (!$city_id) {
                $city_id = Db::name('tencent_map')
                    ->insertGetId([
                        'superior_id' => $province['id'],
                        'name'        => $v['fullname'],
                        'level'       => 2,
                        'code'        => $first_2 . $middle_2 . '00',
                        'code2'       => $middle_2,
                        'add_type'    => 2
                    ]);
            }
            Db::name('tencent_map')->insertGetId([
                'superior_id' => $city_id,
                'name'        => $v['fullname'],
                'level'       => 3,
                'code'        => $v['id'],
                'code2'       => $last_2
            ]);
        }
    }
}