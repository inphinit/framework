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
    const PARSE_MAX_INCLUDES = 50;

    private $data;
    private $vars;
    private static $reserved = array(
        '__class__', '__compiler_halt_offset__', '__dir__', '__file__', '__function__',
        '__halt_compiler', '__line__', '__method__', '__namespace__', '__trait__',
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die',
        'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false',
        'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof',
        'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match',
        'mixed', 'namespace', 'never', 'new', 'null', 'numeric', 'object', 'or',
        'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'resource', 'return', 'self', 'static', 'string', 'switch',
        'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while',
        'xor', 'yield',
    );
    private static $reservedMap;

    /**
     * Create a FileGenerator instance
     *
     * @throws \Inphinit\Exception
     */
    public function __construct()
    {
        $config = new Config('create');
        $this->data = inphinit_sandbox('boot/namespaces.php');
        $this->vars = array(
            'LICENSE' => $config->license,
            'USER' => $config->user,
        );
    }

    /**
     * Create command class from file template
     *
     * @param string $name
     * @param string $fileTemplate
     * @throws \Inphinit\Exception
     * @return string
     */
    public function command($name, $fileTemplate = 'command')
    {
        return $this->createClass(self::COMMANDS_NAMESPACE, $name, $fileTemplate);
    }

    /**
     * Create controller class from file template
     *
     * @param string $name
     * @param string $fileTemplate
     * @throws \Inphinit\Exception
     * @return string
     */
    public function controller($name, $fileTemplate = 'controller')
    {
        return $this->createClass(self::CONTROLLERS_NAMESPACE, $name, $fileTemplate);
    }

    /**
     * Create view from file template
     *
     * @param string $name
     * @param string $fileTemplate
     * @throws \Inphinit\Exception
     * @return string
     */
    public function view($name, $fileTemplate = 'view')
    {
        if (is_string($name) === false || preg_match('#^[a-z_][a-z0-9_]*(?:/[a-z_][a-z0-9_]*)*$#D', $name) !== 1) {
            throw new Exception('Invalid path: ' . self::printable($name), 0, 2);
        }

        $error = self::templateNameError($fileTemplate);

        if ($error !== null) {
            throw new Exception($error, 0, 2);
        }

        return $this->generate(INPHINIT_SYSTEM . '/views', $name, $fileTemplate, array(), 2);
    }

    private function createClass($namespace, $name, $fileTemplate)
    {
        if (is_string($name) === false || preg_match('#^[A-Za-z_][A-Za-z0-9_]*(?:/[A-Za-z_][A-Za-z0-9_]*)*$#D', $name) !== 1) {
            throw new Exception('Invalid path: ' . self::printable($name), 0, 3);
        }

        $error = self::templateNameError($fileTemplate);

        if ($error !== null) {
            throw new Exception($error, 0, 3);
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

        $namespace = rtrim($namespace, '\\');

        if ($ns) {
            $namespace .= '\\' . implode('\\', $ns);
        }

        return $this->generate(
            INPHINIT_SYSTEM . '/' . $dir,
            implode('/', $parts),
            $fileTemplate,
            array(
                'FULL_NAME' => $namespace . '\\' . $class,
                'NAME' => $class,
                'NAMESPACE' => $namespace,
            ),
            3
        );
    }

    /**
     * Shared pipeline for view() and createClass(): checks the directory,
     * loads and renders the template and writes "<dir>/<relative>.php".
     */
    private function generate($dir, $relative, $fileTemplate, array $vars, $depth)
    {
        if (is_dir($dir) === false) {
            throw new Exception('Directory not found: ' . $dir, 0, $depth);
        }

        $permissions = @fileperms($dir);

        if ($permissions === false) {
            throw new Exception('Failed to read directory permissions: ' . $dir, 0, $depth);
        }

        $template = self::getTemplateSource($fileTemplate);

        if ($template === false) {
            throw new Exception(
                'Template does not exist or cannot be read: ' . $fileTemplate . '.ft',
                0,
                $depth
            );
        }

        $dest = $dir . '/' . $relative . '.php';

        try {
            self::assertNoCaseConflict($dir, $relative . '.php');
            $data = $this->render($template, $vars);
            self::createFile($dest, $permissions, $data);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), 0, $depth, $ex);
        } catch (\Throwable $ex) {
            // PHP 7+: TypeError, Error, etc.
            throw new Exception($ex->getMessage(), 0, $depth, new \RuntimeException($ex->getMessage(), 0, $ex));
        }

        return realpath($dest);
    }

    private static function getTemplateSource($fileTemplate)
    {
        $fileTemplate .= '.ft';

        $entries = array(
            // from project
            INPHINIT_SYSTEM . '/stubs/' . $fileTemplate,

            // fallback
            __DIR__ . '/../../stubs/' . $fileTemplate,
        );

        foreach ($entries as $source) {
            if (is_file($source)) {
                return @file_get_contents($source);
            }
        }

        return false;
    }

    /**
     * Write the file. Directories created by this call are removed again
     * when writing fails, so no empty directories are left behind.
     */
    private static function createFile($dest, $permissions, $data)
    {
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }

        $dir = dirname($dest);

        // Deepest first, which is the order needed by rmdir
        $missing = array();
        $current = $dir;

        while (is_dir($current) === false) {
            $missing[] = $current;
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        // mode is still subject to the umask
        if ($missing && @mkdir($dir, $permissions & 0777, true) === false && is_dir($dir) === false) {
            throw new \RuntimeException('Could not create the directory: ' . $dir . self::lastError());
        }

        $handle = @fopen($dest, 'xb');

        if ($handle === false) {
            $error = self::lastError();
            self::removeDirs($missing);
            throw new \RuntimeException('Cannot create (it may already exist): ' . $dest . $error);
        }

        $written = fwrite($handle, $data);
        $closed = fclose($handle);

        if ($written !== strlen($data) || $closed === false) {
            $error = self::lastError();
            @unlink($dest);
            self::removeDirs($missing);
            throw new \RuntimeException('Saving the file failed or was only partial: ' . $dest . $error);
        }

        // inherit the directory permissions, but never as executable
        @chmod($dest, $permissions & 0666);
    }

    private static function removeDirs(array $dirs)
    {
        foreach ($dirs as $dir) {
            @rmdir($dir);
        }
    }

    private static function lastError()
    {
        $error = error_get_last();

        return $error ? ' (' . $error['message'] . ')' : '';
    }

    /**
     * Avoid "Foo.php" and "foo.php" side by side (distinct on Linux,
     * the same file on Windows/macOS, and the same class in PHP).
     */
    private static function assertNoCaseConflict($base, $relative)
    {
        $current = $base;

        foreach (explode('/', $relative) as $segment) {
            $entries = @scandir($current);

            if ($entries === false) {
                return;
            }

            foreach ($entries as $entry) {
                if ($entry !== $segment && strcasecmp($entry, $segment) === 0) {
                    throw new \RuntimeException(
                        'Name conflicts with existing "' . $entry . '" (case-insensitive) in ' . $current
                    );
                }
            }

            $current .= '/' . $segment;

            if (is_dir($current) === false) {
                return;
            }
        }
    }

    /**
     * Render a template using a subset of the PhpStorm (Velocity) syntax:
     *
     * - ${VAR}                    variable (A-Z, 0-9, _); an unknown variable is an error
     * - #if (${VAR}) ... #end     conditional (optional #else, no nesting); true when the value
     *                             is not an empty string. Unbalanced or nested directives are an error
     * - #parse("File Header.php") include `stubs/includes/<filename>.ft`
     *                             (conditionals are resolved before includes, so a #parse in a
     *                             false branch is never loaded)
     *
     * Values cannot contain "*\/", "?>" or NUL, since they may end up inside a PHP comment.
     *
     * @throws \RuntimeException
     */
    private function render($template, array $vars)
    {
        $fill = self::normalizeVars(
            $vars + $this->vars + array('DATETIME' => gmdate('Y-m-d H:i:s') . ' GMT')
        );

        $count = 0;
        $template = self::expand($template, $fill, 0, $count);

        // Anything left here was nested, unbalanced or malformed
        if (preg_match('/#(?:if|else|end|parse)\b/', $template) === 1) {
            throw new \RuntimeException('Unbalanced, nested or unsupported template directive');
        }

        // ${VAR}: single pass, so substituted values are never re-parsed
        $template = preg_replace_callback('/\$\{([A-Z][A-Z0-9_]*)\}/', function ($match) use ($fill) {
            if (array_key_exists($match[1], $fill) === false) {
                throw new \RuntimeException('Unknown template variable: ' . $match[1]);
            }

            return $fill[$match[1]];
        }, $template);

        if ($template === null) {
            throw new \RuntimeException('The structure caused a failure in the parser');
        }

        return $template;
    }

    private static function normalizeVars(array $vars)
    {
        $normalized = array();

        foreach ($vars as $key => $value) {
            if ($value === null) {
                $value = '';
            } elseif (is_scalar($value) === false) {
                throw new \RuntimeException('Template variable must be a string: ' . $key);
            }

            $value = (string) $value;

            if (strpos($value, '*/') !== false || strpos($value, '?>') !== false || strpos($value, "\0") !== false) {
                throw new \RuntimeException('Template variable contains forbidden sequence: ' . $key);
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Resolve conditionals, then includes. Included files are expanded with
     * the same rules, with a depth limit and a total include limit.
     */
    private static function expand($template, array $fill, $depth, &$count)
    {
        $template = self::conditionals($template, $fill);

        $re = '/(?:^[ \t]*)?#parse\s*\(\s*["\']([^"\']+)["\']\s*\)[ \t]*\r?\n?/m';

        $template = preg_replace_callback($re, function ($match) use ($fill, $depth, &$count) {
            if ($depth >= self::PARSE_MAX_DEPTH) {
                throw new \RuntimeException('Maximum parser depth exceeded');
            }

            if (++$count > self::PARSE_MAX_INCLUDES) {
                throw new \RuntimeException('Maximum number of includes exceeded');
            }

            $include_name = $match[1];

            if (self::templateNameError($include_name) !== null) {
                throw new \RuntimeException('Invalid include filename: ' . $include_name);
            }

            $contents = self::getTemplateSource('includes/' . $include_name);

            if ($contents === false) {
                throw new \RuntimeException('Cannot load the file: ' . $include_name);
            }

            return self::expand($contents, $fill, $depth + 1, $count);
        }, $template);

        if ($template === null) {
            throw new \RuntimeException('The structure caused a failure in the parser');
        }

        return $template;
    }

    // #if (${VAR}) ... #else ... #end
    private static function conditionals($template, array $fill)
    {
        $re = '/(?:^[ \t]*)?#if\b\s*\(\s*\$\{(\w+)\}\s*\)(.*?)'
            . '(?:(?:^[ \t]*)?#else\b(.*?))?(?:^[ \t]*)?#end\b[ \t]*\r?\n?/ms';

        $template = preg_replace_callback($re, function ($match) use ($fill) {
            if (array_key_exists($match[1], $fill) === false) {
                throw new \RuntimeException('Unknown template variable in condition: ' . $match[1]);
            }

            if ($fill[$match[1]] === '') {
                $body = isset($match[3]) ? $match[3] : '';
            } else {
                $body = $match[2];
            }

            return preg_replace('/^[ \t]*\r?\n/', '', $body);
        }, $template);

        if ($template === null) {
            throw new \RuntimeException('The structure caused a failure in the parser');
        }

        return $template;
    }

    /**
     * Template names are a whitelist (letters, digits, "_", "-", ".", space),
     * without the ".ft" extension, which is appended automatically.
     *
     * @return string|null error message, or null when the name is valid
     */
    private static function templateNameError($name)
    {
        if (
            is_string($name) === false ||
            strpos($name, '..') !== false ||
            strlen($name) > 100 ||
            preg_match('/^[A-Za-z0-9_](?:[A-Za-z0-9_ .-]*[A-Za-z0-9_-])?$/D', $name) !== 1
        ) {
            return 'Invalid file template';
        }

        if (preg_match('/\.ft$/Di', $name) === 1) {
            return 'Invalid file template: omit the ".ft" extension (use "command", not "command.ft")';
        }

        return null;
    }

    private static function printable($value)
    {
        return is_string($value) ? $value : gettype($value);
    }

    private static function findReserved(array $parts)
    {
        if (self::$reservedMap === null) {
            self::$reservedMap = array_flip(self::$reserved);
        }

        $reserved = self::$reservedMap;

        foreach ($parts as $part) {
            if (isset($reserved[strtolower($part)])) {
                return $part;
            }
        }

        return null;
    }
}
