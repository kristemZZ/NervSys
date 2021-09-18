<?php

/**
 * Algorithm: VSM
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Algo;

use Core\Factory;

/**
 * Class Vsm
 *
 * @package Algo
 */
class Vsm extends Factory
{
    /**
     * Calculate space vector distance between two data list
     *
     * @param array $src
     * @param array $dst
     * @param array $vct
     *
     * @return float
     */
    private static function getDist(array $src, array $dst, array $vct = []): float
    {
        //Extract factors
        $factor = array_unique(array_merge($src, $dst));

        //Calculate vectors
        $res_vct = $res_src = $res_dst = 0;

        foreach ($factor as $item) {
            $vct_src = in_array($item, $src, true) ? ($vct[$item] ?? 1) : 0;
            $vct_dst = in_array($item, $dst, true) ? ($vct[$item] ?? 1) : 0;

            $res_src += $vct_src ** 2;
            $res_dst += $vct_dst ** 2;
            $res_vct += $vct_src * $vct_dst;
        }

        //Calculate space vector distance
        $vsm = cos($res_vct / (sqrt($res_src * $res_dst)));

        unset($src, $dst, $vct, $factor, $res_vct, $res_src, $res_dst, $item, $vct_src, $vct_dst);
        return $vsm;
    }
}