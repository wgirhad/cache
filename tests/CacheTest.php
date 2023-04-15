<?php

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use wgirhad\Cache\Cache;

final class CacheTest extends TestCase
{
    private $maximum_key_length = 64;
    private $key_regex = '/^[a-z0-9_\.]$/i';

    public function testMustImplementCacheInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, Cache::create());
    }

    /**
     * @dataProvider ttlProvider
     */
    public function testMustSupportMinimumTTL(string $key, $value, $ttl, $expected): void
    {
        $cache = new Cache();
        $this->assertTrue($cache->set($key, $value, $ttl));
        $this->assertSame($expected, $cache->get($key));
    }

    public function testKeyTypeMustBeString(): void
    {
        $this->testHasInvalid();
    }

    public function testKeyValidCharset(): void
    {
        $key = 'abc_ABC.123';
        $value = true;
        $cache = Cache::create();

        $this->assertTrue($cache->set($key, $value));
        $this->assertTrue($cache->get($key));
    }

    public function testKeyInvalidCharset(): void
    {
        $keys = '{}()/\@:!Áã#%$`´[]';
        $cache = Cache::create();

        foreach (mb_str_split($keys) as $key) {
            $key = 'abc' . $key;

            $this->expectException(InvalidArgumentException::class);
            $cache->has($key);
        }
    }

    /**
     * @dataProvider stringDataProvider
     **/
    public function testStringData(string $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider integerDataProvider
     **/
    public function testIntegerData(int $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider floatDataProvider
     **/
    public function testFloatData(float $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider booleanDataProvider
     **/
    public function testBooleanData(bool $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider nullDataProvider
     **/
    public function testNullData($value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider arrayDataProvider
     **/
    public function testArrayData(array $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    /**
     * @dataProvider objectDataProvider
     **/
    public function testObjectData(object $value, int $ttl): void
    {
        $this->dataTest($value, $ttl);
    }

    public function testObjectMustBeSerializable(): void
    {
        $key = 'test';
        $cache = Cache::create();
        $value = function(){};

        $this->assertIsObject($value);
        $this->assertFalse($cache->set($key, $value));
    }

    public function testItemsCount(): void
    {
        $cache = Cache::create();

        $this->assertTrue($cache->set('a', 1));
        $this->assertTrue($cache->set('b', 2, 1));
        $this->assertTrue($cache->set('c', 3, 2));
        $this->assertSame(3, $cache->itemsCount());
        sleep(1);
        $this->assertNull($cache->get('b'));
        $this->assertSame(2, $cache->itemsCount());
        sleep(1);
        $this->assertSame(2, $cache->itemsCount());
        $this->assertNull($cache->gc());
        $this->assertSame(1, $cache->itemsCount());
    }

    public function testClear(): void
    {
        $cache = Cache::create();

        $this->assertTrue($cache->set('a', 1));
        $this->assertTrue($cache->set('b', 2));
        $this->assertTrue($cache->set('c', 3));
        $this->assertSame(3, $cache->itemsCount());
        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
        $this->assertNull($cache->get('c'));
        $this->assertSame(0, $cache->itemsCount());
    }

    public function testHasInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cache::create()->has(null);
    }

    public function testDeleteInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cache::create()->delete(null);
    }

    public function testGetMultipleInvalid(): void
    {
        $cache = new Cache();
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple([1, 2, 3]);
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple(1);
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple('a');
    }

    public function testSetMultipleInvalid(): void
    {
        $cache = new Cache();
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple([1, 2, 3]);
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple(1);
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple('a');
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple([1 => 1, 2 => 2, 3 => 3]);
        $this->assertFalse($cache->setMultiple([
            'a' => 1,
            'b' => function() {
                return true;
            }
        ]));
    }

    public function testDeleteMultipleInvalid(): void
    {
        $cache = new Cache();
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple([1, 2, 3]);
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple(1);
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple('a');
    }

    public function testHasValid(): void
    {
        $cache = new Cache();
        $this->assertTrue($cache->set('1', 1));
        $this->assertTrue($cache->has('1'));
        $this->assertFalse($cache->has('0'));
    }

    public function testDeleteValid(): void
    {
        $cache = new Cache();
        $this->assertTrue($cache->set('1', 1));
        $this->assertTrue($cache->delete('1'));
        $this->assertTrue($cache->delete('0'));
    }
    // testGetMultipleValid
    // testSetMultipleValid
    // testDeleteMultipleValid

    // testGc

    public function dataTest($value, $ttl)
    {
        $key = 'test';
        $cache = Cache::create();

        $this->assertTrue($cache->set($key, $value, $ttl));

        if ($ttl <= 0) {
            $this->assertNull($cache->get($key));
        } else {
            $this->assertSame($value, $cache->get($key));
        }
    }

    public function ttlProvider(): array
    {
        $key = 'test_key';
        $value = 123;
        $interval = new DateInterval('PT5S');

        return [
            [$key, $value, $interval, $value],
            [$key, $value, 2, $value],
            [$key, $value, -1, null],
        ];
    }

    public function stringDataProvider(): array
    {
        return $this->randomSetProvide(function() {
            $count = mt_rand(1024, 102400);
            $res = '';
            while ($count--) {
                $res .= chr(mt_rand(1, 255));
            }

            return $res;
        });
    }

    public function integerDataProvider(): array
    {
        return $this->randomSetProvide(function() {
            return mt_rand(PHP_INT_MIN, PHP_INT_MAX);
        });
    }

    public function floatDataProvider(): array
    {
        return $this->randomSetProvide(function() {
            return unpack('d', pack('q', mt_rand(PHP_INT_MIN, PHP_INT_MAX)))[1];
        });
    }

    public function booleanDataProvider(): array
    {
        return $this->permuteTTL([false, true]);
    }

    public function nullDataProvider(): array
    {
        return $this->permuteTTL([null]);
    }

    public function arrayDataProvider(): array
    {
        $example = [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ];

        return $this->permuteTTL([
            $example,
            array_values($example),
            array_flip($example),
            array_combine(array_keys($example), [
                $example,
                array_values($example),
                array_flip($example),
            ]),
        ]);
    }

    public function objectDataProvider(): array
    {
        return $this->permuteTTL([
            new DateTime(),
            new Cache(),
            (object) ['a' => 1, 'b' => 2],
            new Exception(),
        ]);
    }

    public function randomSetProvide(callable $callback): array
    {
        $array = str_split(str_repeat(' ', 20));
        return $this->permuteTTL(array_map($callback, $array));
    }

    public function permuteTTL($array): array
    {
        return array_reduce($array, function ($carry, $current) {
            $carry[] = [$current, -1];
            $carry[] = [$current,  0];
            $carry[] = [$current,  1];
            return $carry;
        }, []);
    }
}
