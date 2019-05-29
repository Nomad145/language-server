<?php

declare(strict_types=1);

namespace LanguageServer\Method\TextDocument\CompletionProvider;

use LanguageServer\Parser\ParsedDocument;
use LanguageServerProtocol\CompletionItem;
use LanguageServerProtocol\CompletionItemKind;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\NodeAbstract;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionMethod;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
class InstanceMethodProvider extends AbstractProvider
{
    protected function mapCompletionItems(ParsedDocument $document, ReflectionClass $reflection): array
    {
        return array_values(array_map(
            function (ReflectionMethod $method) {
                return new CompletionItem(
                    $method->getName(),
                    CompletionItemKind::METHOD,
                    (string) $method->getReturnType(),
                    $method->getDocComment()
                );
            },
            $reflection->getMethods()
        ));
    }

    public function supports(NodeAbstract $expression): bool
    {
        return $expression instanceof PropertyFetch;
    }
}
