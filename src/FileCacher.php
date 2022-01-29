<?php

namespace Dburiy;

class FileCacher
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var int
     */
    private $mode;

    /**
     * FileCacher constructor.
     *
     * @param string $path
     * @param int $mode
     */
    public function __construct(string $path, int $mode = 0777)
    {
        $this->dir = $path;
        $this->mode = $mode;
        if (!$this->mkdir($this->dir, $this->mode)) {
            trigger_error("can't create cache director {$this->dir}", E_USER_WARNING);
        }
    }

    /**
     * Delete cache file by key
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $filename = $this->getFilename($key);
        if (file_exists($filename) && !unlink($filename)) {
            trigger_error("can't remove cache file {$filename}", E_USER_WARNING);
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @param bool $return_if_isset
     * @return false|mixed|string|null
     */
    public function get(string $key, $default = null, bool $return_if_isset = false)
    {
        $result = null;
        $filename = $this->getFilename($key);
        try {
            if (!file_exists($filename)) {
                throw new \Exception("file not found {$filename}");
            }
            $meta = $this->getMeta($filename);
            if (
                $meta
                && $meta['ex'] != 0
                && ($meta['ex'] < microtime(true))
                && ($return_if_isset === false)
            ) {
                unlink($filename);
                throw new \Exception("file expire {$filename}");
            }
            $h = fopen($filename, "r");
            if ($h === false) {
                throw new \Exception("file not readable {$filename}");
            }
            $result = '';
            fgets($h); // read meta from first line
            while (!feof($h)) {
                $result .= fgets($h);
            }
            fclose($h);
            if ($meta && $meta['sr']) {
                $result = unserialize($result);
            }
        } catch (\Exception $e) {
            // can't get cache from file. return default value
        }

        return !is_null($result) ? $result : (is_callable($default) ? call_user_func($default) : $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasItem(string $key): bool
    {
        return file_exists($this->getFilename($key));
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $lifetime
     *
     * @return bool
     */
    public function set(string $key, $value, int $lifetime = 0): bool
    {
        $filename = $this->getFilename($key);
        $expire = $lifetime ? (time() + $lifetime) : 0;
        if (!file_exists($filename)) {
            $dir = dirname($filename);
            if (!$this->mkdir($dir, $this->mode)) {
                trigger_error("can't create cache director: {$dir}", E_USER_WARNING);
                return false;
            }
        }
        $is_serialize = !is_string($value);
        if ($is_serialize) {
            $value = serialize($value);
        }
        $meta = json_encode(['ex' => $expire, 'cr' => time(), 'sr' => $is_serialize], 1);
        $h = fopen($filename, 'w');
        if (!$h) {
            trigger_error("can't create cache file: {$filename}", E_USER_WARNING);
            return false;
        }
        fwrite($h, $meta . PHP_EOL . $value);
        fclose($h);
        chmod($filename, $this->mode);

        return true;
    }

    /**
     * Get filename by key
     *
     * @param string $key
     * @return string
     */
    private function getFilename(string $key): string
    {
        return str_replace('//', '/', $this->dir . '/' . str_replace('_', '/', $key)) . '.cache';
    }

    /**
     * Get meta from file
     *
     * @param string $filename
     * @return array|mixed
     */
    public function getMeta(string $filename)
    {
        if (!file_exists($filename)) {
            return [];
        }
        $fh = fopen($filename, 'r');
        if (!$fh) {
            trigger_error("can't open file {$filename}", E_USER_WARNING);
            return [];
        }
        $line = fgets($fh);
        $result = $line ? json_decode($line, true) : [];
        fclose($fh);

        return is_array($result) ? $result : [];
    }

    /**
     * Get meta from file
     *
     * @param string $key
     * @return array|mixed
     */
    public function getMetaByKey(string $key)
    {
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) {
            return null;
        }
        $fh = fopen($filename, 'r');
        if (!$fh) {
            trigger_error("can't open file {$filename}", E_USER_WARNING);
            return [];
        }
        $line = fgets($fh);
        $result = $line ? json_decode($line, true) : [];
        fclose($fh);

        return is_array($result) ? $result : [];
    }

    /**
     * Delete old cache and empty cache subfolder
     *
     * @param string $folder
     * @return bool
     */
    public function clean(string $folder = ''): bool
    {
        $folder = $folder ?: $this->dir;
        $dirs = scandir($folder, 1);
        $files = 0;
        if ($dirs) {
            $files = count($dirs) - 2;
            foreach ($dirs as $name) {
                if (in_array($name, ['.', '..'])) {
                    continue;
                }
                if (is_dir($folder . '/' . $name)) {
                    if ($this->clean($folder . '/' . $name)) {
                        --$files;
                    }
                    continue;
                }
                $filename = $folder . '/' . $name;
                $meta = $this->getMeta($filename);
                if ($meta && ($meta['ex'] != 0)
                    && ($meta['ex'] < microtime(true))) {
                    if (file_exists($filename) && !unlink($filename)) {
                        trigger_error("can't delete cache file {$filename}", E_USER_WARNING);
                    }
                }
            }
            if (!$files && ($this->dir != $folder)) {
                rmdir($folder);
            }
        }

        return !$files;
    }

    /**
     * Create dir with change permission
     *
     * @param string $dir
     * @param int $perm
     *
     * @return bool
     */
    private function mkdir(string $dir, int $perm): bool
    {
        if (!is_dir($dir) && mkdir($dir, $perm, true)) {
            chmod($dir, $perm);
        }
        return is_dir($dir);
    }
}
