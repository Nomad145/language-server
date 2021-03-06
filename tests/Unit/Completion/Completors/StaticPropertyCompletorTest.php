<?php

declare(strict_types=1);

namespace LanguageServer\Test\Unit\Completion\Providers;

use LanguageServer\Completion\Completors\StaticPropertyCompletor;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use stdClass;

class StaticPropertyCompletorTest extends TestCase
{
    public function testSupports(): void
    {
        $subject = new StaticPropertyCompletor();

        $this->assertTrue($subject->supports(new ClassConstFetch(new Name('Foo'), new Identifier('bar'))));
    }

    public function testComplete(): void
    {
        $subject = new StaticPropertyCompletor();

        $expression = new ClassConstFetch(new Name('Foo'), new Identifier('foo'));
        $reflection = $this->createMock(ReflectionClass::class);
        $property   = $this->createMock(ReflectionProperty::class);

        $property
            ->method('getName')
            ->willReturn('testProperty');

        $property
            ->method('getDocblockTypeStrings')
            ->willReturn(['string', 'null']);

        $property
            ->method('getDocComment')
            ->willReturn('testDocumentation');

        $property
            ->method('isPublic')
            ->willReturn(true);

        $reflection
            ->method('getProperties')
            ->with(ReflectionMethod::IS_STATIC)
            ->willReturn([$property]);

        $completionItems = $subject->complete($expression, $reflection);

        $this->assertCount(1, $completionItems);
        $this->assertEquals(10, $completionItems[0]->kind);
        $this->assertEquals('testProperty', $completionItems[0]->label);
        $this->assertEquals('string|null', $completionItems[0]->detail);
        $this->assertEquals('testDocumentation', $completionItems[0]->documentation);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testCompleteReturnsPropertiesInScope(string $class, stdClass $visibility, bool $expectation): void
    {
        $subject = new StaticPropertyCompletor();

        $expression = new ClassConstFetch(new Name($class), new Identifier('foo'));
        $reflection = $this->createMock(ReflectionClass::class);
        $property   = $this->createMock(ReflectionProperty::class);

        $property
            ->method('isPublic')
            ->willReturn($visibility->public);

        $property
            ->method('isProtected')
            ->willReturn($visibility->protected);

        $property
            ->method('isPrivate')
            ->willReturn($visibility->private);

        $property
            ->method('getDeclaringClass')
            ->willReturn($reflection);

        $reflection
            ->method('getProperties')
            ->willReturn([$property]);

        $completionItems = $subject->complete($expression, $reflection);

        $this->assertEquals($expectation, empty($completionItems) === false);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function methodProvider(): array
    {
        return [
            [
                'self',
                (object) [
                    'public' => true,
                    'protected' => false,
                    'private' => false,
                ],
                true,
            ],
            [
                'self',
                (object) [
                    'public' => false,
                    'protected' => false,
                    'private' => true,
                ],
                true,
            ],
            [
                'parent',
                (object) [
                    'public' => true,
                    'protected' => false,
                    'private' => false,
                ],
                true,
            ],
            [
                'parent',
                (object) [
                    'public' => false,
                    'protected' => true,
                    'private' => false,
                ],
                true,
            ],
            [
                'parent',
                (object) [
                    'public' => false,
                    'protected' => false,
                    'private' => true,
                ],
                false,
            ],
            [
                'Foo',
                (object) [
                    'public' => true,
                    'protected' => false,
                    'private' => false,
                ],
                true,
            ],
            [
                'Foo',
                (object) [
                    'public' => false,
                    'protected' => true,
                    'private' => false,
                ],
                false,
            ],
            [
                'Foo',
                (object) [
                    'public' => false,
                    'protected' => false,
                    'private' => true,
                ],
                false,
            ],
        ];
    }
}
