<?php

/**
 * Class pocketlistsForbiddenException
 */
class pocketlistsForbiddenException extends waException
{
    /**
     * pocketlistsForbiddenException constructor.
     *
     * @param string $message
     * @param int    $code
     * @param null   $previous
     */
    public function __construct($message = '', $code = 500, $previous = null)
    {
        parent::__construct(_w('Access denied'), 403, $previous);
    }
}
