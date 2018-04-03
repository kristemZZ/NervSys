<?php

/**
 * Redis Cache Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2018 Jerry Shaw
 * Copyright 2018 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

use core\ctr\router;

class redis_cache extends redis
{
    //Cache life (in seconds)
    public static $life = 600;

    //Cache name
    public static $name = null;

    //Cache prefix
    public static $prefix = 'cache:';

    //Bind session
    public static $bind_session = false;

    //Redis connection
    private static $redis = null;

    /**
     * Set cache
     *
     * @param array $data
     *
     * @return bool
     * @throws \Exception
     */
    public static function set(array $data): bool
    {
        $name = self::get_name();
        $cache = json_encode($data);

        if (is_null(self::$redis)) self::$redis = parent::connect();
        $result = 0 < self::$life ? self::$redis->set($name, $cache, self::$life) : self::$redis->set($name, $cache);

        unset($data, $name, $cache);
        return $result;
    }

    /**
     * Get cache
     *
     * @return array
     * @throws \Exception
     */
    public static function get(): array
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();
        $cache = self::$redis->get(self::get_name());
        if (false === $cache) return [];

        $data = json_decode($cache, true);
        if (!is_array($data)) return [];

        unset($cache);
        return $data;
    }

    /**
     * Delete cache
     *
     * @throws \Exception
     */
    public static function del(): void
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();
        self::$redis->del(self::get_name());
    }

    /**
     * Get cache name
     *
     * @return string
     */
    private static function get_name(): string
    {
        $keys = is_null(self::$name) ? [router::$cmd, router::$data, self::$bind_session ? $_SESSION : []] : self::$name;
        $name = self::$prefix . hash('md5', json_encode($keys));

        unset($keys);
        return $name;
    }
}