<?php

use Slim\Exception\HttpSpecializedException;

class HttpNotAcceptableException extends HttpSpecializedException
{
    protected $code = 406;
    protected $message = 'Not Acceptable';
    protected $title = '406 Not Acceptable';
    protected $description = 'Cannot produce matching response for the request\'s proactive content negotiation headers.';
}
