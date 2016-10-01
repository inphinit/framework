<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Form
{
    private static $useXhtml = '';
    private static $preAttrs = array();

    private static $alloweds = 'button|checkbox|color|date|datetime|email|file|hidden|image|month|number|password|radio|range|reset|search|submit|tel|text|time|url|week';

    public static function xhtml($xhtml)
    {
        self::$useXhtml = $xhtml === true ? ' /' : '';
    }

    public static function setup($byType, array $attributes)
    {
        if (0 !== preg_match('#^(' . self::$alloweds . '|select|form)$#', $type)) {
            self::$preAttrs[$byType] = $attributes;
        }
    }

    public static function comboRange($name, $low, $high, $step = null, $value = null, $attributes = null)
    {
        if (is_numeric($low) && is_numeric($high)) {
            if ($step !== null) {
                $range = range($low, $high, $step);
                $range = array_combine($range, $range);
            } else {
                $range = range($low, $high);
                $range = array_combine($range, $range);
            }

            return self::combo($name, $range, $value, $attributes);
        }
        return null;
    }

    private static function entities($data)
    {
        return strtr($data, array(
                '<' => '&lt;',
                '>' => '&gt;',
                '"' => '&quot;'
            ));
    }

    public static function combo($name, $options = null, $value = null, $attributes = null)
    {
        $input = self::applyAttr($attributes, '<select{{attr}}>', $name, 'select');

        foreach ($options as $key => $val) {
            $input .= '<option value="' . self::entities($val) . '"';

            if ($value === $val) {
                $input .= ' selected';
            }

            $input .= '>' . $key . '</option>';
        }

        return $input . '</select>';
    }

    public static function input($type, $name, $attributes = null, $value = null)
    {
        if ($type === 'textarea') {
            $input = '<textarea{{attr}}>';

            if ($value !== null) {
                $input .= self::entities($value);
            }

            $input .= '</textarea>';
        } elseif (0 !== preg_match('#^(' . self::$alloweds . ')$#', $type)) {
            $input = '<input type="' . $type . '" value="';

            if ($value !== null) {
                $input .= self::entities($value);
            }

            $input .= '"{{attr}}' . self::$useXhtml . '>';
        } else {
            return null;
        }

        return self::applyAttr($attributes, $input, $name, $type);
    }

    private static function applyAttr($attributes, $field, $name, $type)
    {
        $attrs = '';

        if (false === is_array($attributes)) {
            $attributes = array();
        }

        if (in_array($type, array_keys(self::$preAttrs))) {
            $attributes = self::$preAttrs[$type] + $attributes;
        }

        if ($name !== null) {
            $attributes = array( 'name' => $name ) + $attributes;
        }

        if ($type !== 'select' && $type !== 'textarea' && $type !== 'form') {
            $attributes = array( 'type' => $type ) + $attributes;
        }

        foreach ($attributes as $key => $val) {
            $attrs .= ' ' . $key . '="' . self::entities($val) . '"';
        }

        return str_replace('{{attr}}', $attrs, $field);
    }
}
