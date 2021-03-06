<?php

declare(strict_types=1);

namespace LanguageServer\Inference;

use LanguageServer\ParsedDocument;
use phpDocumentor\Reflection\DocBlock\Tags\Property as PropertyTag;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\UseUse;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\Reflector;

use function array_column;
use function array_filter;
use function array_merge;
use function array_pop;
use function array_values;
use function assert;
use function implode;
use function in_array;
use function property_exists;
use function sprintf;
use function usort;

class TypeResolver
{
    private const SIMPLE_TYPES = [
        'int',
        'void',
        'bool',
        'float',
        'array',
        'object',
        'string',
        'iterable',
        'callable',
    ];

    private Reflector $reflector;
    private DocBlockFactory $docblockFactory;

    public function __construct(Reflector $reflector)
    {
        $this->reflector       = $reflector;
        $this->docblockFactory = DocBlockFactory::createInstance();
    }

    public function getType(ParsedDocument $document, ?Node $node): ?string
    {
        if ($node instanceof Variable) {
            return $this->getVariableType($document, $node);
        }

        if (
            $node instanceof StaticPropertyFetch ||
            $node instanceof ClassConstFetch
        ) {
            return $this->getType($document, $node->class);
        }

        if ($node instanceof StaticCall) {
            return $this->getType($document, $node->class);
        }

        if ($node instanceof New_) {
            return $this->getType($document, $node->class);
        }

        if ($node instanceof Name) {
            return $this->getTypeFromClassReference($document, $node);
        }

        if ($node instanceof Identifier) {
            return $this->getTypeForIdentifier($document, $node);
        }

        if ($node instanceof Assign) {
            if ($node->expr instanceof New_) {
                return $this->getNewAssignmentType($document, $node->expr);
            }

            if ($node->expr instanceof MethodCall) {
                return $this->getReturnType($document, $node->expr);
            }

            return $this->getType($document, $node->expr);
        }

        if ($node instanceof Param) {
            return $this->getArgumentType($document, $node);
        }

        if ($node instanceof MethodCall) {
            if ($node->var instanceof MethodCall) {
                return $this->getReturnType($document, $node->var);
            }

            if ($node->var instanceof PropertyFetch) {
                return $this->getPropertyType($document, $node->var);
            }

            return $this->getType($document, $node->var);
        }

        if ($node instanceof PropertyFetch) {
            if ($node->var instanceof MethodCall) {
                return $this->getReturnType($document, $node->var);
            }

            if ($node->var instanceof PropertyFetch) {
                return $this->getPropertyType($document, $node->var);
            }

            return $this->getType($document, $node->var);
        }

        return null;
    }

    private function getReturnType(ParsedDocument $document, MethodCall $methodCall): ?string
    {
        $variableType = $this->getType($document, $methodCall);

        if ($variableType === null) {
            return null;
        }

        $reflectedClass = $this->reflector->reflect($variableType);

        assert($methodCall->name instanceof Identifier);
        assert($reflectedClass instanceof ReflectionClass);

        $reflectedMethod = $reflectedClass->getMethod($methodCall->name->name);

        if ($reflectedMethod->hasReturnType()) {
            $returnType = (string) $reflectedMethod->getReturnType();
        } else {
            $returnType = implode('|', $reflectedMethod->getDocBlockReturnTypes());
        }

        if ($returnType === '') {
            return null;
        }

        if (in_array($returnType, ['self', '$this', 'this', 'static'])) {
            return $reflectedClass->getName();
        }

        if ($returnType === 'parent') {
            $parent = $reflectedClass->getParentClass();

            if ($parent !== null) {
                return $parent->getName();
            }
        }

        if ($this->isScalarType($returnType)) {
            return $returnType;
        }

        return $this->getType($document, new Name($returnType));
    }

    private function isScalarType(string $returnType): bool
    {
        return in_array($returnType, self::SIMPLE_TYPES);
    }

    /**
     * Attempt to find the variable type.
     *
     * If $node is an instance variable, the document classname will be
     * returned.  Otherwise, the closest assignment will be used to resolve the
     * type.
     */
    public function getVariableType(ParsedDocument $document, Variable $variable): ?string
    {
        if ($variable->name === 'this') {
            return $document->getClassName();
        }

        $closestVariable = $this->findClosestVariableReferencesInDocument($variable, $document);

        if ($closestVariable === null) {
            return null;
        }

        return $this->getType($document, $closestVariable);
    }

    /**
     * @return Assign|Param
     */
    private function findClosestVariableReferencesInDocument(Variable $variable, ParsedDocument $document): ?Node
    {
        $expressions = $this->findVariableReferencesInDocument($variable, $document);

        if (empty($expressions)) {
            return null;
        }

        /** @var array<int, Param|Assign> $orderedExpressions */
        $orderedExpressions = $this->sortNodesByEndingLocation($expressions);

        return array_pop($orderedExpressions);
    }

    /**
     * @return array<int, Assign|Param>
     */
    private function findVariableReferencesInDocument(Variable $variable, ParsedDocument $document): array
    {
        /** @var array<int, Assign|Param> $nodes */
        $nodes = $document->searchNodes(
            static function (Node $node) use ($variable): bool {
                if (! $node instanceof Assign && ! $node instanceof Param) {
                    return false;
                }

                if ($node->var instanceof ArrayDimFetch) {
                    return false;
                }

                assert(property_exists($node->var, 'name'));

                return $node->var->name === $variable->name
                    && $node->getEndFilePos() <= $variable->getEndFilePos();
            }
        );

        return $nodes;
    }

    /**
     * @param Node[] $expressions
     *
     * @return array<int, Node>
     */
    private function sortNodesByEndingLocation(array $expressions): array
    {
        usort($expressions, static function (Node $a, Node $b) {
            return $a->getEndFilePos() <=> $b->getEndFilePos();
        });

        return $expressions;
    }

    /**
     * Get the type for the class specified by a new operator.
     */
    private function getNewAssignmentType(ParsedDocument $document, New_ $node): string
    {
        $type = $this->getType($document, $node->class);
        assert($type !== null);

        return $type;
    }

    private function getArgumentType(ParsedDocument $document, Param $param): ?string
    {
        return $this->getType($document, $param->type);
    }

    private function getTypeFromClassReference(ParsedDocument $document, Name $node): ?string
    {
        if ((string) $node === 'self') {
            return $document->getClassName();
        }

        $useStatements = array_merge(...array_column($document->getUseStatements(), 'uses'));

        $matchingUseStatement = array_filter(
            $useStatements,
            static function (UseUse $use) use ($node) {
                if ($use->alias !== null) {
                    return $use->alias->name === $node->getLast();
                }

                return $use->name->getLast() === $node->getLast();
            }
        );

        if (empty($matchingUseStatement)) {
            if ($node->isUnqualified()) {
                return sprintf('%s\%s', $document->getNamespace(), $node->getLast());
            }

            // If the node is qualified, return it.
            return $node->toCodeString();
        }

        return array_pop($matchingUseStatement)->name->toCodeString();
    }

    private function getTypeForIdentifier(ParsedDocument $document, Identifier $identifier): ?string
    {
        if (in_array($identifier->toLowerString(), self::SIMPLE_TYPES) === true) {
            return $identifier->toLowerString();
        }

        return null;
    }

    private function getPropertyType(ParsedDocument $document, PropertyFetch $property): ?string
    {
        assert($property->name instanceof Identifier);
        $propertyDeclaration = $document->getClassProperty((string) $property->name);

        if ($propertyDeclaration !== null && $this->propertyHasResolvableType($propertyDeclaration)) {
            return $this->getType($document, $propertyDeclaration->type);
        }

        $docblockType = $this->getPropertyTypeFromDocblock($document, $property);

        if ($docblockType !== null) {
            return $this->getType($document, new Name($docblockType));
        }

        return $this->getPropertyTypeFromConstructorAssignment($document, $property);
    }

    private function propertyHasResolvableType(Property $property): bool
    {
        return $property->type instanceof Identifier
            || $property->type instanceof Name;
    }

    private function getPropertyTypeFromDocblock(ParsedDocument $document, PropertyFetch $property): ?string
    {
        $propertyName = $property->name;
        $variableType = $this->getType($document, $property);

        if ($variableType === null) {
            return null;
        }

        $reflectedClass = $this->reflector->reflect($variableType);
        assert($reflectedClass instanceof ReflectionClass);
        assert($propertyName instanceof Identifier);

        $reflectedProperty = $reflectedClass->getProperty($propertyName->name);

        if ($reflectedProperty === null) {
            return $this->getPropertyTypeFromClassDocblock($document, $property, $reflectedClass);
        }

        return $this->getPropertyFromPropertyDocblock($document, $reflectedProperty);
    }

    private function getPropertyTypeFromClassDocblock(ParsedDocument $document, PropertyFetch $property, ReflectionClass $class): ?string
    {
        assert($property->name instanceof Identifier);

        /** @var array<int, PropertyTag> $propertyTags */
        $propertyTags = $this->docblockFactory->create($class->getDocComment())->getTagsByName('property');

        if (empty($propertyTags) === true) {
            return null;
        }

        foreach ($propertyTags as $propertyTag) {
            if ($propertyTag->getVariableName() === $property->name->name) {
                return (string) $propertyTag->getType();
            }
        }

        return null;
    }

    private function getPropertyFromPropertyDocblock(ParsedDocument $document, ReflectionProperty $reflectedProperty): ?string
    {
        $docblockTypes = $reflectedProperty->getDocBlockTypeStrings();

        if (empty($docblockTypes) === true) {
            return null;
        }

        // @todo: Figure out what to do with union types
        return $this->getType($document, new Name(array_pop($docblockTypes)));
    }

    private function getPropertyTypeFromConstructorAssignment(ParsedDocument $document, PropertyFetch $property): ?string
    {
        $constructor = $document->getConstructorNode();

        if ($constructor === null) {
            return null;
        }

        $propertyAssignment = array_values(array_filter(
            $constructor->stmts ?? [],
            static function (Node $node) use ($property) {
                if (($node instanceof Expression && $node->expr instanceof Assign) === false) {
                    return false;
                }

                if ($node->expr->var instanceof PropertyFetch === false) {
                    return false;
                }

                assert($property->name instanceof Identifier);
                assert($node->expr->var->name instanceof Identifier);

                return $node->expr->var->name->name === $property->name->name;
            }
        ));

        if (empty($propertyAssignment)) {
            return null;
        }

        assert($propertyAssignment[0] instanceof Expression);
        assert($propertyAssignment[0]->expr instanceof Assign);

        return $this->getType($document, $propertyAssignment[0]->expr->expr);
    }
}
