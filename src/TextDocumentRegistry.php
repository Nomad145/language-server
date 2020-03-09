<?php

declare(strict_types=1);

namespace LanguageServer;

use function array_key_exists;

class TextDocumentRegistry
{
    /** @var array<string, TextDocument> */
    private array $documents = [];

    public function has(string $fileName) : bool
    {
        return array_key_exists($this->documents, $fileName);
    }

    public function get(string $fileName) : TextDocument
    {
        return $this->documents[$fileName];
    }

    public function add(TextDocument $document) : void
    {
        $this->documents[$document->getUri()] = $document;
    }

    /**
     * @return array<string, TextDocument>
     */
    public function getAll() : array
    {
        return $this->documents;
    }

    public function clear() : void
    {
        $this->documents = [];
    }
}
