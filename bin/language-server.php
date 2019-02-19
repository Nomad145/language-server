<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

try {
    $loop = React\EventLoop\Factory::create();

    $lexerOptions = ['usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos']];
    $parser = (new PhpParser\ParserFactory())->create(1, new PhpParser\Lexer($lexerOptions));

    $socket = new React\Socket\Server('127.0.0.1:8080', $loop);
    $server = new LanguageServer\RPC\TcpServer($socket);
    $initialize = new LanguageServer\LSP\Command\Initialize($server);
    $signatureHelp = new LanguageServer\LSP\Command\SignatureHelp($server, $parser);

    $loop->run();
} catch (\Exception $t) {
    file_put_contents('output', $t->getMessage());
}
