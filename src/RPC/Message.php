<?php

declare(strict_types=1);

namespace LanguageServer\RPC;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
abstract class Message
{
    public $jsonrpc = '2.0';
}
