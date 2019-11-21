<?php

declare(strict_types=1);

namespace LanguageServer\Server\Protocol;

use Throwable;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
class ResponseMessage extends Message
{
    public $id;
    public $result;
    public $error;

    public function __construct(RequestMessage $request, $result)
    {
        $this->id = $request->id;

        if ($result instanceof Throwable) {
            $this->error = new ResponseError($result);

            return;
        }

        $this->result = $result;
    }
}
