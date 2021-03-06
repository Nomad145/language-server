<?php

declare(strict_types=1);

namespace LanguageServer\Diagnostics;

use LanguageServer\ParsedDocument;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use RuntimeException;

use function assert;

use const SIGTERM;

abstract class Command
{
    private LoopInterface $loop;

    private string $cwd;

    private ?Process $process = null;

    public function __construct(LoopInterface $loop, string $cwd)
    {
        $this->loop = $loop;
        $this->cwd  = $cwd;
    }

    public function execute(ParsedDocument $document): PromiseInterface
    {
        $input  = $this->input($document);
        $output = null;

        $deferred      = new Deferred();
        $this->process = new Process($this->getCommand($document), $this->cwd);

        $this->process->start($this->loop);

        assert($this->process->stdin instanceof WritableStreamInterface);
        assert($this->process->stdout instanceof ReadableStreamInterface);

        if ($input !== null) {
            $this->process->stdin->write($input);
            $this->process->stdin->end();
        }

        $this->process->stdout->on(
            'data',
            static function (string $data) use (&$output): void {
                $output .= $data;
            }
        );

        assert($this->process !== null);

        $this->process->on(
            'exit',
            function (?int $code, ?int $term) use (&$output, &$deferred): void {
                $this->cleanup();

                if ($term !== null) {
                    $deferred->reject(new RuntimeException('The command was terminated'));

                    return;
                }

                $deferred->resolve($output);
            }
        );

        return $deferred->promise();
    }

    public function isRunning(): bool
    {
        return $this->process !== null;
    }

    public function terminate(): void
    {
        if ($this->isRunning() === false) {
            return;
        }

        assert($this->process !== null);

        $this->process->terminate(SIGTERM);
    }

    private function cleanup(): void
    {
        $this->process = null;
    }

    protected function input(ParsedDocument $document): ?string
    {
        return null;
    }

    abstract public function getCommand(ParsedDocument $document): string;
}
