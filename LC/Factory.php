<?php

/**
 * Factory library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\LC;

class Factory
{
    protected static array $objects = [];

    /**
     * @return static
     * @throws \ReflectionException
     */
    public static function new(): static
    {
        return self::getObj(get_called_class(), func_get_args());
    }

    /**
     * @param string $class_name
     * @param array  $class_params
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function getObj(string $class_name, array $class_params = []): object
    {
        $key = $class_name;

        if (!empty($class_params)) {
            $key .= json_encode($class_params);
        }

        $hash_key = hash('md5', $key);

        if (!isset(self::$objects[$hash_key])) {
            if (method_exists($class_name, '__construct') && 1 === count($class_params)) {
                $pass_params = current($class_params);

                if (is_array($pass_params) && !array_is_list($pass_params)) {
                    $class_params = self::buildArgs(Reflect::getMethod($class_name, '__construct')->getParameters(), $pass_params);
                }

                unset($pass_params);
            }

            self::$objects[$hash_key] = new ('\\' . trim($class_name, '\\'))(...$class_params);
        }

        unset($class_name, $class_params, $key);
        return self::$objects[$hash_key];
    }

    /**
     * @param array $param_reflects
     * @param array $data_package
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function buildArgs(array $param_reflects, array $data_package): array
    {
        $result = ['args' => [], 'diff' => []];

        foreach ($param_reflects as $param_reflect) {
            $param_info = Reflect::getParameterInfo($param_reflect);

            if (!$param_info['build_in']) {
                $result['args'][] = self::getObj($param_info['type'], $data_package);
                continue;
            }

            if (!isset($data_package[$param_info['name']])) {
                $param_info['has_default']
                    ? $result['args'][] = $param_info['default_value']
                    : $result['diff'][] = '$' . $param_info['name'] . ' not found';
                continue;
            }

            if ('int' === $param_info['type'] && is_numeric($data_package[$param_info['name']])) {
                $result['args'][] = (int)$data_package[$param_info['name']];
            } elseif ('float' === $param_info['type'] && is_numeric($data_package[$param_info['name']])) {
                $result['args'][] = (float)$data_package[$param_info['name']];
            } elseif ('string' === $param_info['type'] && (is_string($data_package[$param_info['name']]) || is_numeric($data_package[$param_info['name']]))) {
                $result['args'][] = trim((string)$data_package[$param_info['name']]);
            } elseif ('array' === $param_info['type'] && is_array($data_package[$param_info['name']])) {
                $result['args'][] = $data_package[$param_info['name']];
            } elseif ('bool' === $param_info['type'] && is_bool($data_package[$param_info['name']])) {
                $result['args'][] = $data_package[$param_info['name']];
            } elseif ('object' === $param_info['type'] && is_object($data_package[$param_info['name']])) {
                $result['args'][] = $data_package[$param_info['name']];
            } elseif (is_null($param_info['type'])) {
                $result['args'][] = $data_package[$param_info['name']];
            } else {
                $result['diff'][] = '$' . $param_info['name'] . ': expected \'' . $param_info['type'] . '\', but was \'' . $data_package[$param_info['name']] . '\'';
            }
        }

        unset($param_reflects, $data_package, $param_reflect, $param_info);
        return $result;
    }

    /**
     * @param object $object
     *
     * @return void
     */
    public static function destroy(object $object): void
    {
        $keys = array_keys(self::$objects, $object, true);

        foreach ($keys as $key) {
            unset(self::$objects[$key]);
        }

        unset($object, $keys, $key);
    }
}