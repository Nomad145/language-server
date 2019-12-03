<?php

declare(strict_types=1);

namespace LanguageServer\Method;

use DI\Container;
use LanguageServer\Server\Protocol\Message;
use LanguageServer\Server\Protocol\ResponseMessage;
use LanguageServerProtocol\CompletionOptions;
use LanguageServerProtocol\InitializeResult;
use LanguageServerProtocol\SaveOptions;
use LanguageServerProtocol\ServerCapabilities;
use LanguageServerProtocol\SignatureHelpOptions;
use LanguageServerProtocol\TextDocumentSyncKind;
use LanguageServerProtocol\TextDocumentSyncOptions;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
class Initialize implements RequestHandlerInterface
{
    private ContainerInterface $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke(Message $request)
    {
        $this->setProjectRoot($request->params);

        $capabilities = new ServerCapabilities();

        $saveOptions = new SaveOptions();
        $textDocumentSync = new TextDocumentSyncOptions();

        $saveOptions->includeText = false;
        $textDocumentSync->openClose = true;
        $textDocumentSync->willSave = false;
        $textDocumentSync->save = $saveOptions;
        $textDocumentSync->willSaveWaitUntil = false;
        $textDocumentSync->change = TextDocumentSyncKind::FULL;

        $capabilities->hoverProvider = false;
        $capabilities->renameProvider = false;
        $capabilities->codeLensProvider = false;
        $capabilities->definitionProvider = false;
        $capabilities->referencesProvider = false;
        $capabilities->referencesProvider = false;
        $capabilities->codeActionProvider = false;
        $capabilities->xdefinitionProvider = false;
        $capabilities->dependenciesProvider = false;
        $capabilities->documentSymbolProvider = false;
        $capabilities->workspaceSymbolProvider = false;
        $capabilities->documentHighlightProvider = false;
        $capabilities->documentFormattingProvider = false;
        $capabilities->xworkspaceReferencesProvider = false;
        $capabilities->documentRangeFormattingProvider = false;
        $capabilities->documentOnTypeFormattingProvider = false;

        $capabilities->textDocumentSync = $textDocumentSync;
        $capabilities->completionProvider = new CompletionOptions(true, [':', '>']);
        $capabilities->signatureHelpProvider = new SignatureHelpOptions(['(', ',']);

        return new ResponseMessage($request, new InitializeResult($capabilities));
    }

    private function setProjectRoot(array $params): void
    {
        if (null === $params['rootUri']) {
            throw new RuntimeException('The project root was not specified');
        }

        $this->container->set('project_root', str_replace('file://', '', $params['rootUri']));
    }
}
