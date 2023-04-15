<?php

namespace wgirhad\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CacheException;
use DateTime;
use DateInterval;
use Throwable;

class Cache implements CacheInterface
{
    private $storage;
    private $lindex;
    private $validator;

    public function __construct()
    {
        $this->validator = new Validator();
        $this->clear();
    }

    /**
     * Counts the inner storage length
     *
     * It is recommended to call Cache::gc() before to get more precise results
     */
    public function itemsCount(): int
    {
        return count($this->storage);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $this->validator->validateKey($key);
        return $this->fetch($key, $default);
    }

    private function fetch($key, $default)
    {
        if (!$this->exists($key)) {
            return $default;
        }

        return $this->storage[$key]['data'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $this->validator->validateKey($key);

        if (!$this->validator->validateData($value)) {
            return false;
        }

        $ttl = $this->convertTTL($ttl);
        $this->put($key, $value, $ttl);

        return true;
    }

    private function put($key, $value, $ttl): void
    {
        if ($ttl !== null) {
            if ($ttl <= time()) {
                $this->remove($key);
                return;
            }

            $this->lindex[$ttl][] = $key;
        }

        $this->storage[$key] = [
            'ttl' => $ttl,
            'data' => $value
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $this->validator->validateKey($key);
        return $this->remove($key);
    }

    private function remove($key): bool
    {
        try {
            if (!$this->exists($key)) {
                return true;
            }

            $ttl = $this->storage[$key]['ttl'];
            unset($this->storage[$key]);

            if ($ttl !== null) {
                $this->lindex[$ttl] = array_diff($this->lindex[$ttl], [$key]);
                if (empty($this->lindex[$ttl])) {
                    unset($this->lindex[$ttl]);
                }
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->storage = [];
        $this->lindex = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $this->validator->validateKeyList($keys);

        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->fetch($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!$this->validator->validateSetMultiple($values)) {
            return false;
        }

        $ttl = $this->convertTTL($ttl);

        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        $this->validator->validateKeyList($keys);

        $result = true;

        foreach ($keys as $key) {
            $result = $result && $this->remove($key);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        $this->validator->validateKey($key);
        return $this->exists($key);
    }

    private function exists(string $key): bool
    {
        if (!array_key_exists($key, $this->storage)) {
            return false;
        }

        if ($this->expired($key)) {
            return false;
        }

        return true;
    }

    public function gc(): void
    {
        $time = time();
        foreach (array_keys($this->lindex) as $key_ttl) {
            if ($key_ttl <= $time) {
                $this->expire($key_ttl);
            }
        }
    }

    private function expired(string $key): bool
    {
        if (empty($this->storage[$key]['ttl'])) {
            return false;
        }

        $key_ttl = $this->storage[$key]['ttl'];

        if ($key_ttl <= time()) {
            $this->expire($key_ttl);
            return true;
        }

        return false;
    }

    private function expire(int $key_ttl): void
    {
        $keys = $this->lindex[$key_ttl] ?? [];
        foreach ($keys as $key) {
            unset($this->storage[$key]);
        }

        unset($this->lindex[$key_ttl]);
    }

    private function convertTTL($ttl): ?int
    {
        if ($this->validator->validateTTL($ttl)) {
            if ($ttl instanceof DateInterval) {
                $ttl = (int) (new DateTime())->add($ttl)->getTimestamp();
            } else {
                $ttl += time();
            }
        } else {
            $ttl = null;
        }

        return $ttl;
    }

    public static function create(): Cache
    {
        return new static();
    }
}
