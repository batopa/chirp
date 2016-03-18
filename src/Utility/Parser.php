<?php
/**
 * This file is part of Chirp package
 *
 * Copyright (c) 2015 Alberto Pagliarini
 *
 * Licensed under the MIT license
 * https://github.com/batopa/chirp/blob/master/LICENSE
 */
namespace Bato\Chirp\Utility;

class Parser
{
    /**
     * Normalize a string trimming trailing '/'
     * and replacing remained '/' with '-'
     *
     * Example:
     * - ///first//second///// => first-second
     *
     * @param string $name the string to normalize
     * @return string
     */
    public static function normalize($name)
    {
        $name = trim($name, '/');
        return preg_replace('/\/+/', '-', $name);
    }

    /**
     * Match $data returning false if $conditions are not sotisfied.
     * Return true if all conditions are met
     *
     * @param array $data an array of data
     * @param array $conditions an array if filter vars
     * @return bool
     */
    public static function match(array $data, array $conditions = array())
    {
        $default = [
            'require' => [],
            'grep' => []
        ];
        $conditions = array_intersect_key($conditions, $default) + $default;

        foreach ($conditions as $key => $value) {
            $method = 'match' . ucfirst($key);
            if (!self::{$method}($data, $conditions[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match require
     * Check if $data contains all required keys with not empty values.
     * It returns false if some reuired keys is missing
     *
     * @param array $data an array of data
     * @param array $require an array if required keys
     * @return bool
     */
    protected static function matchRequire(array $data, array $require = array())
    {
        foreach ($require as $name) {
            if (!self::hasKey($data, $name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a key is present in array and it's value is not empty or match $matchValue value
     * $key can be a point separated string to traversing the array
     *
     * Examples:
     *
     * ```
     * $data = [
     *     'name1' => [
     *          'name2' => 'value',
     *          'name3' => null
     *     ]
     * ];
     *
     * Parser::hasKey($data, 'name1'); // return true
     * Parser::hasKey($data, 'name1.name2'); // return true
     * Parser::hasKey($data, 'name2'); // return false
     * Parser::hasKey($data, 'name1.name3'); // return false
     * Parser::hasKey($data', name1.name4'); // return false
     * Parser::hasKey($data, 'name1.name2', '/alu/'); // return true
     * Parser::hasKey($data, 'name1.name2', '/^alu/'); // return false
     * ```
     * @param array $data an array to check
     * @param string $key the key name
     * @param string $matchPattern a regexp used to match value
     * @return bool
     */
    public static function hasKey(array $data, $key, $matchPattern = null)
    {
        $keys = array_filter(explode('.', $key));
        foreach ($keys as $k => $n) {
            if (empty($data[$n])) {
                return false;
            }
            if (array_key_exists($k + 1, $keys)) {
                $data = $data[$n];
            } elseif (is_string($matchPattern) && !preg_match($matchPattern, $data[$n])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Match grep
     * Check if $grep keys exist in $data and their value match $grep values
     * $grep is an array as
     *
     * ```
     * [
     *     'grep_key' => 'grep_value',
     *     'grep_key2.grep_key3' => 'grep_value2'
     * ]
     * ```
     *
     * @param array $data an array of data
     * @param array $grep
     * @return bool
     */
    protected static function matchGrep(array $data, array $grep = array())
    {
        if (empty($grep)) {
            return true;
        }

        foreach ($grep as $key => $value) {
            $matchPattern = self::grepPattern($value);
            if (!self::hasKey($data, $key, $matchPattern)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Given a string or an array of string
     * it builds the regexp pattern to use in self::matchGrep()
     *
     * Example:
     * ```
     * $data = ['one', 'two', 'three'];
     * ```
     * will produce the pattern `/((^|\s|\W)one\b)|((^|\s|\W)two\b)|((^|\s|\W)three\b)/i`
     *
     * @param array|string $data
     * @return string
     */
    private static function grepPattern($data)
    {
        $stringPattern = '((^|\s|\W)%s\b)';
        $multipleStringPattern = '%s|%s';
        $globalPattern = '/%s/i';
        if (!is_array($data)) {
            $data = sprintf($stringPattern, $data);
            return sprintf($globalPattern, $data);
        }

        $result = '';
        foreach ($data as $string) {
            $string = sprintf($stringPattern, $string);
            if (empty($result)) {
                $result = $string;
            } else {
                $result = sprintf($multipleStringPattern, $result, $string);
            }
        }
        return sprintf($globalPattern, $result);
    }
}
