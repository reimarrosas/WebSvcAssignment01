<?php

use Slim\Exception\HttpSpecializedException;

class HttpUnprocessableEntityException extends HttpSpecializedException
{
    protected $code = 422;
    protected $message = 'Unprocessable Entity';
    protected $title = '422 Unprocessable Entity';
    protected $description = 'Unable to process contained instructions';
}
