<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Scaffolding;

use Inphinit\App;
use Inphinit\Config;
use Inphinit\Exception;

class FileGenerator
{
    const COMMANDS_NAMESPACE = 'Commands\\';
    const CONTROLLERS_NAMESPACE = 'Controllers\\';

    const PARSE_MAX_DEPTH = 5;

    private $config;
    private $data;
    private $includeDir;

    public function __construct()
    {
        $config = new Config('create');
        $this->data = inphinit_sandbox('boot/namespaces.php');
        $this->includeDir = INPHINIT_SYSTEM . '/stubs/includes/';
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
        $parts = explode('/', $name);

        $ns = $parts;
        $class = array_pop($ns);

        $data = $this->data;

        if (isset($data[$namespace]) === false) {
            throw new Exception('boot namespaces not configured', 0, 3);
        }

        $dir = INPHINIT_SYSTEM . '/' . $data[$namespace];

        if (is_string($dir) === false) {
            throw new Exception('Invalid directory path: ' . $dir, 0, 3);
        }

        if (is_dir($dir) === false) {
            throw new Exception('Not found directory: ' . $dir, 0, 3);
        }

        if ($ns) {
            $namespace .= implode('\\', $ns);
        }

        $dest = $dir . '/' . implode('/', $parts) . '.php';
        $permissions = fileperms($dir);

        if ($permissions === false) {
            throw new Exception('Failed to read directory permissions: ' . $dir, 0, 3);
        }

        $template = self::getTemplateSource($fileTemplate);

        if ($template === false) {
            throw new Exception('Template does not exist or cannot be read: ' . $fileTemplate, 0, 3);
        }

        $template = self::render($template, array(
            'NAMESPACE' => $namespace,
            'NAME' => $class,
            'FULL_NAME' => $namespace . '\\' . $class
        ));

        $result = $this->createFile($dest, fileperms($dir), $template);

        if ($result !== true) {
            throw new Exception($result, 0, 3);
        }

        return $dest;
    }

    public function view($name, $fileTemplate = 'view.ft')
    {
        $dir = INPHINIT_SYSTEM . '/views';

        if (is_dir($dir) === false) {
            throw new Exception('Not found directory: ' . $dir, 0, 3);
        }

        $dest = $dir . '/' . str_replace('.', '/', $name) . '.php';
        $permissions = fileperms($dir);

        if ($permissions === false) {
            throw new Exception('Failed to read directory permissions: ' . $dir, 0, 3);
        }

        $template = self::getTemplateSource($fileTemplate);

        if ($template === false) {
            throw new Exception('Template does not exist or cannot be read: ' . $fileTemplate, 0, 3);
        }

        $template = self::render($template, array());

        $result = $this->createFile($dest, fileperms($dir), $template);

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
        if (is_file($dest)) {
            return 'File already exists: ' . $dest;
        }

        $permissions &= 0777;

        $dir = dirname($dest);

        if (is_dir($dir) === false && mkdir($dir, $permissions, true) === false) {
            return 'Unable to create directory: ' . $dir;
        }

        if (file_put_contents($dest, $data, LOCK_EX) === false) {
            return 'Unable to create file: ' . $dest;
        }

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
    private function render($template, array $vars, $depth = 0)
    {
        // #parse("filename")
        if ($depth < self::PARSE_MAX_DEPTH) {
            $parse_re = '/#parse\s*\(\s*["\']([^"\']+)["\']\s*\)[ t]*\r?\n?/';

            $template = preg_replace_callback($parse_re, function ($match) use ($vars, $depth) {
                $filename = $match[1];

                if (strpbrk($filename, '/\\') !== false || strpos($filename, '..') !== false) {
                    return '';
                }

                $file = $this->includeDir . $filename . '.ft';

                if (is_file($file) === false) {
                    return '';
                }

                $content = file_get_contents($file);

                if ($content === false || trim($content) === '') {
                    return '';
                }

                return self::render($content, $vars, $depth + 1);
            }, $template);
        }

        $fill = $vars + array('DATETIME' => gmdate('Y-m-d h:i:s') . ' GMT');
        $re = '/#if\s*\(\s*\$\{(\w+)\}\s*\)(.*?)(?:#else(.*?))?#end[ t]*\r?\n?/s';

        // #if (${VAR}) ... #else ... #end
        $template = preg_replace_callback($re, function ($match) use ($fill) {
            if (empty($fill[$match[1]])) {
                $body = isset($match[3]) ? $match[3] : '';
            } else {
                $body = $match[2];
            }

            return preg_replace('/^[ t]*\r?\n/', '', $body);
        }, $template);

        // ${VAR}
        $map = array();

        foreach ($fill as $key => $value) {
            var_dump('${' . $key . '}');
            $map['${' . $key . '}'] = $value;
        }

        return strtr($template, $map);
    }
}
