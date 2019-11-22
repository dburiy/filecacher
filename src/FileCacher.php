<?php

namespace Dburiy;

class FileCacher
{
    private $dir = '';
    private $mode;

    /**
     * FileCacher constructor.
     *
     * @param string $path
     * @param int $mode
     *
     * @throws \Exception
     */
    public function __construct(string $path, int $mode = 0777)
    {
        $this->dir = $path;
        $this->mode = $mode;
        if (!$this->mkdir($this->dir, $this->mode)) {
            throw new \Exception("Can't create cache director: {$this->dir}");
        }
    }

    /**
     * Delete cache file by key
     *
     * @param string $key
     *
     * @return bool
     * @throws \Exception
     * @deprecated use delete method
     */
    public function remove(string $key)
    {
        $filename = $this->getFilename($key);
        if (file_exists($filename) && !unlink($filename)) {
            throw new \Exception("Can't remove cache file {$filename}");
        }

        return true;
    }

    /**
     * Delete cache file by key
     *
     * @param string $key
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(string $key)
    {
        $filename = $this->getFilename($key);
        if (file_exists($filename) && !unlink($filename)) {
            throw new \Exception("Can't remove cache file {$filename}");
        }

        return true;
    }

    /**
     * Get value
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        $result   = '';
        $filename = $this->getFilename($key);
        try {
            if (!file_exists($filename)) {
                throw new \Exception("file not found {$filename}");
            }
            $meta = $this->getMeta($key);
            if ($meta && $meta['expire'] != 0 && ($meta['expire'] < microtime(true))) {
                unlink($filename);
                throw new \Exception("file expire {$filename}");
            }
            $h = fopen($filename, "r");
            if ($h === false) {
                throw new \Exception("file not readable {$filename}");
            }
            fgets($h); // read first line with meta
            while (!feof($h)) {
                $str    = fgets($h);
                $result .= $str;
            }
            fclose($h);
            if ($meta && $meta['serialized']) {
                $result = unserialize($result);
            }
        } catch (\Exception $e) {
            // can't get cache from file. return default value
        }

        return $result ?: (is_callable($default) ? call_user_func($default) : $default);
    }

    /**
     * Set value
     *
     * @param string $key
     * @param mixed $value
     * @param int $lifetime
     *
     * @return $this
     * @throws \Exception
     */
    public function set(string $key, $value, int $lifetime = 0)
    {
        $filename = $this->getFilename($key);
        $expire = $lifetime ? microtime(true) + (int)$lifetime : 0;
        if (!file_exists($filename)) {
            $dir = dirname($filename);
            if (!$this->mkdir($dir, $this->mode)) {
                throw new \Exception("Can't create cache director: {$dir}");
            }
        }
        $is_serialize = !is_string($value);
        if ($is_serialize) {
            $value = serialize($value);
        }
        $meta = json_encode(['expire' => $expire, 'time' => time(), 'serialized' => $is_serialize], 1);
        $h = fopen($filename, 'w');
        if (!$h) {
            throw new \Exception("Can't create cache file: {$filename}");
        }
        fwrite($h, $meta . PHP_EOL . $value);
        fclose($h);
        chmod($filename, $this->mode);

        return $this;
    }

    /**
     * @param string $key
     * @param int $lifetime
     * @param mixed $default
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function cache(string $key, int $lifetime, $default = null)
    {
        $data = $this->get($key);
        if (is_null($data)) {
            $data = is_callable($default) ? call_user_func($default) : $default;
            if (!is_null($data)) {
                $this->set($key, $data, $lifetime);
            }
        }

        return $data;
    }

    /**
     * Get meta by cache key
     *
     * @param string $key
     *
     * @return mixed
     */
    private function getMeta(string $key)
    {
        $filename = $this->getFilename($key);

        return $this->getMetaFromFile($filename);
    }

    /**
     * Get filename by key
     *
     * @param string $key
     *
     * @return mixed
     */
    private function getFilename(string $key)
    {
        return str_replace('//', '/', $this->dir . '/' . str_replace('_', '/', $key));
    }

    /**
     * Get meta from file
     *
     * @param string $filename
     *
     * @return array
     */
    private function getMetaFromFile(string $filename)
    {
        try {
            if (!file_exists($filename)) {
                throw new \Exception("cache file not exist {$filename}");
            }
            $fh = fopen($filename, 'r');
            if (!$fh) {
                throw new \Exception("can't open file {$filename}");
            }
            $line = fgets($fh);
            $result = $line ? json_decode($line, true) : '';
            fclose($fh);
        } catch (\Exception $e) {
            $result = [];
        }

        return $result;
    }

    /**
     * Delete old cache file and empty cache subfolder
     *
     * @param string $folder
     *
     * @return bool
     * @throws \Exception
     */
    public function clean(string $folder = '')
    {
        $folder = $folder ? $folder : $this->dir;
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
                $meta = $this->getMetaFromFile($filename = $folder . '/' . $name);
                if ($meta && ($meta['expire'] != 0)
                    && ($meta['expire'] < microtime(true))) {

                    if (file_exists($filename) && !unlink($filename)) {
                        throw new \Exception("Can't delete old cache file {$filename}");
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
     * @return bool
     */
    private function mkdir($dir, $perm)
    {
        if (!is_dir($dir)) {
            if (mkdir($dir, $perm, true)) {
                chmod($dir, $perm);
            }
        }
        return is_dir($dir);
    }
}
