<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Scaffolding;

use Inphinit\Config;
use Inphinit\Exception;

class FileGenerator
{
    const COMMANDS_NAMESPACE = 'Commands\\';
    const CONTROLLERS_NAMESPACE = 'Controllers\\';

    const PARSE_MAX_DEPTH = 5;

    private $data;
    private $vars;
    private static $reserved = array(
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break',
        'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile',
        'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn',
        'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface',
        'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never',
        'new', 'null', 'numeric', 'object', 'or', 'parent', 'print', 'private',
        'protected', 'public', 'readonly', 'require', 'require_once', 'resource',
        'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true',
        'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
        '__class__', '__dir__', '__file__', '__function__',
        '__line__', '__method__', '__namespace__', '__trait__',
    );

    public function __construct()
    {
        $config = new Config('create');
        $this->data = inphinit_sandbox('boot/namespaces.php');
        $this->vars = array(
            'LICENSE' => $config->license,
            'USER' => $config->user,
        );
    }

    public function command($name, $fileTemplate = 'command.ft')
    {
        return $this->createClass(self::COMMANDS_NAMESPACE, $name, $fileTemplate);
    }

    public function controller($name, $fileTemplate = 'controller.ft')
    {
        return $this->createClass(self::CONTROLLERS_NAMESPACE, $name, $fileTemplate);
    }

    private function createClass($namespace, $name, $fileTemplate)
    {
        if (preg_match('#^[A-Za-z_][A-Za-z0-9_]*(?:/[A-Za-z_][A-Za-z0-9_]*)*$#D', $name) !== 1) {
            throw new Exception('Invalid path: ' . $name, 0, 3);
        }

        $parts = explode('/', $name);

        $word = self::findReserved($parts);

        if ($word !== null) {
            throw new Exception('Reserved word cannot be used: ' . $word, 0, 3);
        }

        $ns = $parts;
        $class = array_pop($ns);

        $data = $this->data;

        if (isset($data[$namespace]) === false) {
            throw new Exception('boot namespaces not configured', 0, 3);
        }

        $dir = $data[$namespace];

        if (is_string($dir) === false) {
            throw new Exception('Invalid directory from ' . $namespace . ' namespace', 0, 3);
        }

        $dir = INPHINIT_SYSTEM . '/' . $dir;

        if (is_dir($dir) === false) {
            throw new Exception('Not found directory: ' . $dir, 0, 3);
        }

        $namespace = rtrim($namespace, '\\');

        if ($ns) {
            $namespace .= '\\' . implode('\\', $ns);
        }

        $dest = $dir . '/' . implode('/', $parts) . '.php';
        $permissions = @fileperms($dir);

        if ($permissions === false) {
            throw new Exception('Failed to read directory permissions: ' . $dir, 0, 3);
        }

        $template = self::getTemplateSource($fileTemplate);

        if ($template === false) {
            throw new Exception('Template does not exist or cannot be read: ' . $fileTemplate, 0, 3);
        }

        $template = $this->render($template, array(
            'NAMESPACE' => $namespace,
            'NAME' => $class,
            'FULL_NAME' => $namespace . '\\' . $class
        ));

        $result = $this->createFile($dest, $permissions, $template);

        if ($result !== true) {
            throw new Exception($result, 0, 3);
        }

        return $dest;
    }

    public function view($name, $fileTemplate = 'view.ft')
    {
        if (preg_match('#^[a-z_][a-z0-9_]*(?:/[a-z_][a-z0-9_]*)*$#D', $name) !== 1) {
            throw new Exception('Invalid path: ' . $name, 0, 3);
        }

        $dir = INPHINIT_SYSTEM . '/views';

        if (is_dir($dir) === false) {
            throw new Exception('Not found directory: ' . $dir, 0, 3);
        }

        $dest = $dir . '/' . $name . '.php';
        $permissions = @fileperms($dir);

        if ($permissions === false) {
            throw new Exception('Failed to read directory permissions: ' . $dir, 0, 3);
        }

        $template = self::getTemplateSource($fileTemplate);

        if ($template === false) {
            throw new Exception('Template does not exist or cannot be read: ' . $fileTemplate, 0, 3);
        }

        $template = $this->render($template, array());

        $result = $this->createFile($dest, $permissions, $template);

        if ($result !== true) {
            throw new Exception($result, 0, 3);
        }

        return $dest;
    }

    private static function getTemplateSource($fileTemplate)
    {
        $entries = array(
            // from project
            INPHINIT_SYSTEM . '/stubs/' . $fileTemplate,

            // fallback
            __DIR__ . '/../../stubs/' . $fileTemplate,
        );

        $path = null;

        foreach ($entries as $source) {
            if (is_file($source)) {
                $path = $source;
                break;
            }
        }

        if ($path === null) {
            return false;
        }

        $source_data = file_get_contents($path);

        if ($source_data === false) {
            return false;
        }

        return $source_data;
    }

    private function createFile($dest, $permissions, $data)
    {
        $permissions &= 0777;

        $dir = dirname($dest);

        if (is_dir($dir) === false && mkdir($dir, $permissions, true) === false) {
            return 'Unable to create directory: ' . $dir;
        }

        $handle = @fopen($dest, 'x');

        if ($handle === false) {
            return 'The file cannot be created (it may already exist): ' . $dest;
        }

        if (fwrite($handle, $data) !== strlen($data)) {
            fclose($handle);
            @unlink($dest);
            return 'Saving the file failed or was only partial: ' . $dest;
        }

        fclose($handle);

        return true;
    }

    /**
     * Render a template using a subset of the PhpStorm (Velocity) syntax:
     *
     * - ${VAR}                    variable
     * - #if (${VAR}) ... #end     conditional (optional #else, no nesting)
     * - #parse("File Header.php") include `system/stubs/includes/<filename>.ft`
     *
     * Unknown variables are left untouched.
     */
    private function render($template, array $vars)
    {
        $vars = $vars + $this->vars;

        $template = $this->expandIncludes($template, 0);

        $fill = $vars + array('DATETIME' => gmdate('Y-m-d H:i:s') . ' GMT');
        $re = '/#if\s*\(\s*\$\{(\w+)\}\s*\)(.*?)(?:#else(.*?))?#end[ \t]*\r?\n?/s';

        // #if (${VAR}) ... #else ... #end
        $template = preg_replace_callback($re, function ($match) use ($fill) {
            if (empty($fill[$match[1]])) {
                $body = isset($match[3]) ? $match[3] : '';
            } else {
                $body = $match[2];
            }

            return preg_replace('/^[ \t]*\r?\n/', '', $body);
        }, $template);

        // ${VAR}
        $map = array();

        foreach ($fill as $key => $value) {
            $map['${' . $key . '}'] = $value;
        }

        return strtr($template, $map);
    }

    private function expandIncludes($template, $depth)
    {
        $re = '/#parse\s*\(\s*["\']([^"\']+)["\']\s*\)[ \t]*\r?\n?/';

        return preg_replace_callback($re, function ($match) use ($depth) {
            // Depth limit reached or unsafe name: the directive is removed
            if ($depth >= self::PARSE_MAX_DEPTH || self::isSafeName($match[1]) === false) {
                return '';
            }

            $contents = self::getTemplateSource('includes/' . $match[1] . '.ft');

            if ($contents === false) {
                return '';
            }

            return $this->expandIncludes($contents, $depth + 1);
        }, $template);
    }

    private static function isSafeName($name)
    {
        return is_string($name)
            && $name !== ''
            && strpbrk($name, "/\\\0") === false
            && strpos($name, '..') === false;
    }

    private static function findReserved(array $parts)
    {
        $reserved = array_flip(self::$reserved);

        foreach ($parts as $part) {
            if (isset($reserved[strtolower($part)])) {
                return $part;
            }
        }

        return null;
    }
}
