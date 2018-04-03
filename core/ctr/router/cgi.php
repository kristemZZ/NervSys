<?php

/**
 * cgi Router Module
 *
 * Copyright 2016-2018 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

namespace core\ctr\router;

use core\ctr\router;

class cgi extends router
{
    //Module list
    private static $module = [];

    //Method list
    private static $method = [];

    //Object list
    private static $object = [];

    //Mapping list
    private static $mapping = [];

    /**
     * Run CGI Router
     */
    public static function run(): void
    {
        //Read data
        self::read_data();

        //Prepare cmd
        self::prep_cmd();

        //Parse cmd
        self::parse_cmd();

        //Execute cmd
        self::execute_cmd();
    }

    /**
     * Prepare CGI data
     */
    private static function read_data(): void
    {
        if ('' !== parent::$cmd) return;

        //Read data
        self::read_http();
        self::read_input();

        //Get cmd value
        $val = parent::opt_val(parent::$data, ['c', 'cmd']);
        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) parent::$cmd = &$val['data'];

        unset($val);
    }

    /**
     * Get data from HTTP Request
     */
    private static function read_http(): void
    {
        $data = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : $_REQUEST);

        //Collect data
        if (!empty($data)) parent::$data += $data;
        if (!empty($_FILES)) parent::$data += $_FILES;

        unset($data);
    }

    /**
     * Get data from raw input stream
     */
    private static function read_input(): void
    {
        $input = file_get_contents('php://input');
        if (false === $input) return;

        $data = json_decode($input, true);
        if (is_array($data) && !empty($data)) parent::$data += $data;

        unset($input, $data);
    }

    /**
     * Prepare "cmd" data
     */
    private static function prep_cmd(): void
    {
        if (empty(parent::$conf_cgi)) return;

        //Explode command
        $data = false !== strpos(parent::$cmd, '-') ? explode('-', parent::$cmd) : [parent::$cmd];

        //Parse mapping keys
        foreach ($data as $key => $value) {
            if (isset(parent::$conf_cgi[$value])) {
                $data[$key] = parent::$conf_cgi[$value];
                self::$mapping[parent::$conf_cgi[$value]] = $value;
            }
        }

        //Rebuild command
        parent::$cmd = implode('-', $data);

        unset($data, $key, $value);
    }

    /**
     * Parse "cmd" data
     */
    private static function parse_cmd(): void
    {
        //Extract "cmd" list
        $list = false !== strpos(parent::$cmd, '-') ? explode('-', parent::$cmd) : [parent::$cmd];

        //Parse "cmd" values
        foreach ($list as $item) {
            if ('' === $item) continue;

            //Get module value
            $module = self::get_module($item);

            //Save module & method & function
            if ('' !== $module) {
                if (!isset(self::$module[$module])) self::$module[$module] = [];//Save module
                if (!in_array($item, self::$module[$module], true)) self::$module[$module][] = $item;//Save method
            } elseif (!in_array($item, self::$method, true)) self::$method[] = $item;//Save function
        }

        unset($list, $item, $module);
    }

    /**
     * Execute cmd
     */
    private static function execute_cmd(): void
    {
        //Check module data
        if (empty(self::$module)) {
            debug('CGI', 'Command ERROR!');
            return;
        }

        //Execute queue list
        foreach (self::$module as $module => $method) {
            //Load Module config file
            $conf = realpath(ROOT . '/' . $module . '/conf.php');
            if (false !== $conf) require $conf;

            //Call API
            self::call_api($method);
        }

        unset($module, $method, $conf);
    }

    /**
     * Get module value
     *
     * @param string $lib
     *
     * @return string
     */
    private static function get_module(string $lib): string
    {
        //Trim with "/"
        $lib = trim($lib, " /\t\n\r\0\x0B");

        //Detect module
        $pos = strpos($lib, '/');
        $module = false !== $pos ? substr($lib, 0, $pos) : '';

        unset($lib, $pos);
        return $module;
    }

    /**
     * API Caller
     *
     * @param $lib
     */
    private static function call_api(array $lib): void
    {
        foreach ($lib as $class) {
            //Get root class
            $space = '\\' . strtr($class, '/', '\\');
            //Call methods
            class_exists($space) ? self::call_class($class, $space) : debug(self::map_key($class), 'Class [' . $space . '] NOT found!');
        }

        unset($lib, $class, $space);
    }

    /**
     * Class Caller
     *
     * @param $class
     * @param $space
     */
    private static function call_class(string $class, string $space): void
    {
        //Check API TrustZone
        if (!isset($space::$tz) || !is_array($space::$tz)) {
            debug(self::map_key($class), 'TrustZone NOT Open!');
            return;
        }

        //Call "init" method without permission
        if (method_exists($space, 'init')) {
            try {
                self::call_method($class, $space, 'init');
            } catch (\Throwable $exception) {
                debug(self::map_key($class, 'init'), 'Execute Failed! ' . $exception->getMessage());
                unset($exception);
            }
        }

        //Check API TrustZone permission
        if (empty($space::$tz)) return;

        //Get API TrustZone list & method list
        $tz_list = array_keys($space::$tz);
        $func_list = get_class_methods($space);

        //Get request list from API TrustZone list
        $method_list = !empty(self::$method) ? array_intersect(self::$method, $tz_list, $func_list) : array_intersect($tz_list, $func_list);

        //Remove "init" method from request list when exists
        if (in_array('init', $method_list, true)) unset($method_list[array_search('init', $method_list, true)]);

        //Process method list
        foreach ($method_list as $method) {
            try {
                //Compare data structure with method TrustZone
                $inter = array_intersect(array_keys(parent::$data), $space::$tz[$method]);
                $diff = array_diff($space::$tz[$method], $inter);

                //Report missing TrustZone data
                if (!empty($diff)) throw new \Exception('TrustZone missing [' . (implode(', ', $diff)) . ']!');

                //Call method
                self::call_method($class, $space, $method);
            } catch (\Throwable $exception) {
                debug(self::map_key($class, $method), 'Execute Failed! ' . $exception->getMessage());
                unset($exception);
            }
        }

        unset($class, $space, $tz_list, $func_list, $method_list, $method, $inter, $diff);
    }

    /**
     * Method Caller
     *
     * @param string $class
     * @param string $space
     * @param string $method
     *
     * @throws \Exception
     * @throws \ReflectionException
     */
    private static function call_method(string $class, string $space, string $method): void
    {
        //Get method reflection object
        $reflect = new \ReflectionMethod($space, $method);

        //Check visibility
        if (!$reflect->isPublic()) return;

        //Mapping data
        $data = self::map_data($reflect);

        //Create object
        if (!$reflect->isStatic()) $space = self::$object[$class] ?? self::$object[$class] = new $space;

        //Call method (with params)
        $result = empty($data) ? forward_static_call([$space, $method]) : forward_static_call_array([$space, $method], $data);

        //Save result (Try mapping keys)
        if (isset($result)) parent::$result[self::map_key($class, $method)] = &$result;

        unset($class, $space, $method, $reflect, $data, $result);
    }

    /**
     * Build mapped data
     *
     * @param $reflect
     *
     * @return array
     * @throws \Exception
     */
    private static function map_data($reflect): array
    {
        //Get method params
        $params = $reflect->getParameters();
        if (empty($params)) return [];

        //Process data
        $data = $diff = [];
        foreach ($params as $param) {
            //Get param name
            $name = $param->getName();

            //Check param data
            if (isset(parent::$data[$name])) {
                switch ($param->getType()) {
                    case 'int':
                        $data[$name] = (int)parent::$data[$name];
                        break;
                    case 'bool':
                        $data[$name] = (bool)parent::$data[$name];
                        break;
                    case 'float':
                        $data[$name] = (float)parent::$data[$name];
                        break;
                    case 'array':
                        $data[$name] = (array)parent::$data[$name];
                        break;
                    case 'string':
                        $data[$name] = (string)parent::$data[$name];
                        break;
                    case 'object':
                        $data[$name] = (object)parent::$data[$name];
                        break;
                    default:
                        $data[$name] = parent::$data[$name];
                        break;
                }
            } else $param->isOptional() ? $data[$name] = $param->getDefaultValue() : $diff[] = $name;
        }

        //Report missing argument data
        if (!empty($diff)) throw new \Exception('Argument missing [' . (implode(', ', $diff)) . ']!');

        unset($reflect, $params, $diff, $param, $name);
        return $data;
    }

    /**
     * Build mapped key
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    private static function map_key(string $class, string $method = ''): string
    {
        $key = '' !== $method ? (self::$mapping[$class . '-' . $method] ?? (self::$mapping[$class] ?? $class) . '/' . $method) : (self::$mapping[$class] ?? $class);

        unset($class, $method);
        return $key;
    }
}