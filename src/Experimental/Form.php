<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Form
{
    private static $useXhtml = '';
    private static $preAttrs = array();

    private static $alloweds = 'button|checkbox|color|date|datetime|email|file|hidden|image|month|number|password|radio|range|reset|search|submit|tel|text|time|url|week';

    /**
     * Define new forms for use xhtml (`<input />`) or html format (`<input>`)
     *
     * @param bool $xhtml
     * @return void
     */
    public static function xhtml($xhtml)
    {
        self::$useXhtml = $xhtml === true ? ' /' : '';
    }

    /**
     * Define default attributes for all elements
     *
     * @param string $byType
     * @param array  $attributes
     * @return void
     */
    public static function setup($byType, array $attributes)
    {
        if (0 !== preg_match('#^(' . self::$alloweds . '|select|form)$#', $byType)) {
            self::$preAttrs[$byType] = $attributes;
        }
    }

    /**
     * Generate combo by range
     *
     * @param string      $name
     * @param string|int  $low
     * @param string|int  $high
     * @param int         $step
     * @param string|null $value
     * @param string      $attributes
     * @return string|null
     */
    public static function comboRange($name, $low, $high, $step = null, $value = null, array $attributes = array())
    {
        $range = $step !== null ? range($low, $high, $step) : range($low, $high);
        $range = array_combine($range, $range);

        return self::combo($name, $range, $value, $attributes);
    }

    /**
     * Convert all applicable data to HTML entities
     *
     * @param string $data
     * @return string
     */
    private static function entities($data)
    {
        return strtr($data, array(
                '<' => '&lt;',
                '>' => '&gt;',
                '"' => '&quot;'
            ));
    }

    /**
     * Create a select combo based in an array
     *
     * @param string      $name
     * @param array       $options
     * @param string|null $value
     * @param array       $attributes
     * @return string
     */
    public static function combo($name, array $options, $value = null, array $attributes = array())
    {
        $input = self::setAttr($attributes, '<select{{attr}}>', $name, 'select');

        foreach ($options as $key => $val) {
            $input .= '<option value="' . self::entities($val) . '"';

            if ($value === $val) {
                $input .= ' selected';
            }

            $input .= '>' . $key . '</option>';
        }

        return $input . '</select>';
    }

    /**
     * Create a input or textarea
     *
     * @param string      $type
     * @param string      $name
     * @param string|null $value
     * @param array       $attributes
     * @return string
     */
    public static function input($type, $name, $value = null, array $attributes = array())
    {
        if ($type === 'textarea') {
            $input = '<textarea{{attr}}>';

            if ($value !== null) {
                $input .= self::entities($value);
            }

            $input .= '</textarea>';
        } elseif (preg_match('#^(' . self::$alloweds . ')$#', $type)) {
            $input = '<input type="' . $type . '" value="';

            if ($value !== null) {
                $input .= self::entities($value);
            }

            $input .= '"{{attr}}' . self::$useXhtml . '>';
        } else {
            throw new Exception('Invalid type');
        }

        return self::setAttr($attributes, $input, $name, $type);
    }

    /**
     * set attributes in an element
     *
     * @param array  $attributes
     * @param string $field
     * @param string $name
     * @param string $type
     * @return string
     */
    private static function setAttr(array $attributes, $field, $name, $type)
    {
        $attrs = '';

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
