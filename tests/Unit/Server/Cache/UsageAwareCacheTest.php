<?php

declare(strict_types=1);

namespace LanguageServer\Test\Unit\Server\Cache;

use LanguageServer\Server\Cache\UsageAwareCache;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

use function time;

class UsageAwareCacheTest extends TestCase
{
    public function testGet(): void
    {
        $subject = new UsageAwareCache();

        $object = new stdClass();
        $subject->set('test', $object);

        $reflection = new ReflectionClass(UsageAwareCache::class);
        $property   = $reflection->getProperty('values');
        $property->setAccessible(true);

        $values                    = $property->getValue($subject);
        $values['test']['expiry'] -= 100;
        $originalExpiration        = $values['test']['expiry'];

        $property->setValue($subject, $values);

        $result        = $subject->get('test');
        $newExpiration = $property->getValue($subject)['test']['expiry'];

        $this->assertSame($object, $result);
        $this->assertEquals(100, $newExpiration - $originalExpiration);
    }

    public function testGetWithDefaultObject(): void
    {
        $subject = new UsageAwareCache();

        $object = new stdClass();

        $this->assertSame($object, $subject->get('test', $object));
    }

    public function testHas(): void
    {
        $subject = new UsageAwareCache();

        $object = new stdClass();
        $subject->set('test', $object);

        $this->assertTrue($subject->has('test'));
        $this->assertFalse($subject->has('nonexistentObject'));
    }

    public function testDelete(): void
    {
        $subject = new UsageAwareCache();

        $object = new stdClass();
        $subject->set('test', $object);
        $subject->delete('test');

        $this->assertFalse($subject->has('test'));
    }

    public function testGetSetAndDeleteMultiple(): void
    {
        $subject = new UsageAwareCache();

        $objectA = new stdClass();
        $objectB = new stdClass();

        $subject->setMultiple([
            'objectA' => $objectA,
            'objectB' => $objectB,
        ]);

        $objects = $subject->getMultiple(['objectA', 'objectB']);

        $this->assertCount(2, $objects);
        $this->assertContains($objectA, $objects);
        $this->assertContains($objectB, $objects);

        $subject->deleteMultiple(['objectA', 'objectB']);

        $this->assertFalse($subject->has('objectA'));
        $this->assertFalse($subject->has('objectB'));
    }

    public function testClear(): void
    {
        $subject = new UsageAwareCache();

        $object = new stdClass();
        $subject->set('test', $object);

        $this->assertTrue($subject->clear());
        $this->assertFalse($subject->has('test'));
    }

    public function testGetExtendsTheTtl(): void
    {
        $originalTime = time();

        $subject = new UsageAwareCache();

        $reflection = new ReflectionClass(UsageAwareCache::class);
        $property   = $reflection->getProperty('values');
        $property->setAccessible(true);

        $subject->set('test', new stdClass());
        $subject->get('test');

        $expiry = $property->getValue($subject)['test']['expiry'];

        $this->assertEquals($originalTime + 180, $expiry);
    }

    public function testCleanClearsExpiredCacheEntries(): void
    {
        $subject = new UsageAwareCache();

        $reflection = new ReflectionClass(UsageAwareCache::class);
        $property   = $reflection->getProperty('values');
        $property->setAccessible(true);

        $property->setValue($subject, [
            'validObject' => [
                'value' => new stdClass(),
                'expiry' => 9999999999,
            ],
            'expiredObject' => [
                'value' => new stdClass(),
                'expiry' => 1584770777,
            ],
        ]);

        $subject->clean();

        $cache = $property->getValue($subject);

        $this->assertArrayHasKey('validObject', $cache);
        $this->assertArrayNotHasKey('expiredObject', $cache);
    }
}
