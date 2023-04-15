<?php

namespace wgirhad\Cache;

class Validator
{
    /**
     * Validates the Key parameter as defined by PSR-16
     *
     * A string of at least one character that uniquely identifies a cached
     * item. Implementing libraries MUST support keys consisting of the
     * characters "A-Z, a-z, 0-9, _, and ." in any order in UTF-8 encoding and a
     * length of up to 64 characters.
     * Implementing libraries MAY support additional characters and encodings or
     * longer lengths, but MUST support at least that minimum.
     * Libraries are responsible for their own escaping of key strings as
     * appropriate, but MUST be able to return the original unmodified key string.
     * The following characters are reserved for future extensions and MUST NOT
     * be supported by implementing libraries: "{}()/\@:"
     *
     * @param string $key
     *
     * @throws wgirhad\Cache\InvalidArgumentException if the $key string is not a legal value.
     */
    public function validateKey($key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException();
        }

        if (mb_strlen($key) > 64) {
            throw new InvalidArgumentException();
        }

        if ($key === '') {
            throw new InvalidArgumentException();
        }

        if (!preg_match('/^[a-z0-9\\_\\.]+$/i', $key)) {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Validates the Data parameter as defined by PSR-16
     *
     * Implementing libraries MUST support all serializable PHP data types, including:
     *
     * Strings - Character strings of arbitrary size in any PHP-compatible encoding.
     * Integers - All integers of any size supported by PHP, up to 64-bit signed.
     * Floats - All signed floating point values.
     * Booleans - True and False.
     * Null - The null value
     * Arrays - Indexed, associative and multidimensional arrays of arbitrary depth.
     * Objects - Any object that supports lossless serialization and deserialization
     *           such that $o == unserialize(serialize($o)). Objects MAY leverage
     *           PHP's Serializable interface, __sleep() or __wakeup() magic
     *           methods, or similar language functionality if appropriate.
     * All data passed into the Implementing Library MUST be returned exactly as passed.
     * Implementing Libraries MAY use PHP's serialize()/unserialize() functions
     * internally but are not required to do so. Compatibility with them is
     * simply used as a baseline for acceptable object values.
     *
     * If it is not possible to return the exact saved value for any reason,
     * implementing libraries MUST respond with a cache miss rather than
     * corrupted data.
     *
     * Since PSR-16 does not state that an exception must be throw on invalid
     * data, we'll just return a boolean false
     *
     * @param mixed $data Data to be stored
     *
     * @return bool
     */
    public function validateData($value): bool
    {
        if ($value instanceof \Serializable) {
            return true;
        }

        try {
            return $value == unserialize(serialize($value));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Validates the TTL parameter as defined by PSR-16
     *
     * @param int|\DateInterval $ttl Cache key TTL in seconds
     *
     * @return bool
     */
    public function validateTTL($ttl): bool
    {
        if ($ttl instanceof \DateInterval) {
            return true;
        }

        if (is_int($ttl)) {
            return true;
        }

        return false;
    }

    public function validateKeyList($keys): void
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentException();
        }

        array_map([$this, 'validateKey'], $keys);
    }

    public function validateSetMultiple($values): bool
    {
        if (!is_iterable($values)) {
            throw new InvalidArgumentException();
        }

        $this->validateKeyList(array_keys($values));

        foreach ($values as $value) {
            if (!$this->validateData($value)) {
                return false;
            }
        }

        return true;
    }
}
