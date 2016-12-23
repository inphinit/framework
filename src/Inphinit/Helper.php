<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Helper
{
    const URL_UTF8 = 2;
    const URL_ASCII = 1;

    public static function extractVersion($version)
    {
        if (preg_match('#^(\d+)\.(\d+)\.(\d+)(\-([\w.\-]+)|)$#', $version, $match) > 0) {
            return (object) array(
                'major' => $match[1],
                'minor' => $match[2],
                'patch' => $match[3],
                'extra' => empty($match[5]) ? null : $match[5]
            );
        }

        return false;
    }

    public static function toAscii($text)
    {
        $encode = mb_detect_encoding($text, mb_detect_order(), true);
        return 'ASCII' === $encode ? $text : iconv($encode, 'ASCII//TRANSLIT//IGNORE', $text);
    }

    public static function url($text, $type = null)
    {
        $text = preg_replace('#[`\'"\^~\{\}\[\]\(\)]#', '', $text);
        $text = preg_replace('#[\n\s\/\p{P}]#u', '-', $text);

        if ($type === Helper::URL_UTF8) {
            $text = preg_replace('#[^\d\p{L}\p{N}\-]#u', '', $text);
        } elseif ($type === Helper::URL_ASCII) {
            $text = preg_replace('#[^\d\p{L}\-]#u', '', $text);
            $text = self::url($text);
        } else {
            $text = self::toAscii($text);
            $text = preg_replace('#[^a-z\d\-]#i', '', $text);
        }

        $text = preg_replace('#[\-]+[\-]#', '-', $text);
        return trim($text, '-');
    }

    public static function camelCase($str, $delimeter = '-', $glue = '')
    {
        return implode($glue, array_map('ucfirst', explode($delimeter, strtolower($str))));
    }

    public static function arrayPath($path, array $items)
    {
        $paths = explode('.', $path);

        foreach ($paths as $value) {
            $items = is_array($items) && array_key_exists($value, $items) ? $items[$value] : false;

            if ($items === false) {
                break;
            }
        }

        return $items;
    }
}
