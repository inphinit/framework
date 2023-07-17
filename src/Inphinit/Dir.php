<?php
/*
 * Inphinit
 *
 * Copyright (c) 2023 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Dir implements \Iterator, \Countable
{
    private $position = 0;
    private $item = false;
    private $path = '';
    private $handle;
    private $size = -1;

    /**
     * Return items from a folder
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return void
     */
    public function __construct($path)
    {
        $this->handle = opendir($path);

        if ($this->handle === false) {
            throw new Exception('Failed to open folder', 2);
        }

        $path = strtr(realpath($path), '\\', '/');

        $this->path = rtrim($path, '/') . '/';

        $this->find(0);
    }

    /**
     * Return items from root project folder (probably, will depend on the setting
     * of the `INPHINIT_ROOT` constant)
     *
     * @return \Inphinit\Dir
     */
    public static function root()
    {
        return new static(INPHINIT_ROOT);
    }

    /**
     * Return items from storage folder
     *
     * @return \Inphinit\Dir
     */
    public static function storage()
    {
        return new static(INPHINIT_PATH . 'storage');
    }

    /**
     * Return items from application folder
     *
     * @return \Inphinit\Dir
     */
    public static function application()
    {
        return new static(INPHINIT_PATH . 'application');
    }

    /**
     *  Resets the directory stream to the beginning of the directory
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = $this->find(0);
    }

    /**
     * Get current file with type, path and filename
     * The entries are returned in the order in which they are stored by the filesystem. 
     *
     * @return stdClass|null
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        if ($this->item !== false) {
            $current = $this->path . $this->item;

            return (object) array(
                'type' => filetype($current),
                'path' => $current,
                'name' => $this->item,
                'position' => $this->position
            );
        }
    }

    /**
     *  Get current position in handle
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->position;
    }

    /**
     * Move forward to next file
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->item = readdir($this->handle);

        if ($this->item !== false) {
            ++$this->position;
        }
    }

    /**
     *  Check if pointer is valid
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->item !== false;
    }

    /**
     * Count files in folder, can br used by `count($instance)` funciton
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        if ($this->size === -1) {
            $this->size = $this->find(-1);

            //Restore position
            if ($this->position > 0) {
                $this->find($this->position);
            }
        }

        return $this->size;
    }

    private function find($pos)
    {
        rewinddir($this->handle);

        $current = 0;
        $break = $pos !== -1;

        while (false !== ($item = readdir($this->handle))) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if ($current === $pos) {
                $this->item = $item;
                
                if ($break) {
                    break;
                }
            }

            ++$current;
        }

        return $current !== $pos ? $current : 0;
    }

    public function __destruct()
    {
        if ($this->handle) {
            closedir($this->handle);
            $this->handle = null;
        }
    }
}
