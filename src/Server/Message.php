<?php

declare(strict_types=1);

namespace LanguageServer\Server;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
abstract class Message
{
    public $jsonrpc = '2.0';
}