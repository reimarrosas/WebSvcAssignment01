<?php

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

$app->add(function (Request $req, RequestHandler $handler) {
    $res = $handler->handle($req);

    return $res->withHeader('content-type', 'application/json');
});
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->setBasePath("/music-api");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'chinook_db');

$app->get('/', function (Request $req, Response $res, $args) {
    $paths = [
        'artists' => 'http://localhost/music-api/artists',
        'customers' => 'http://localhost/music-api/customers',
        'tracks' => 'http://localhost/music-api/artists/{artist_id}/albums/{album_id}/tracks',
        'invoices' => 'http://localhost/music-api/customers/{customer_id}/invoices'
    ];

    $res->getBody()->write(json_encode($paths, JSON_UNESCAPED_SLASHES));
    return $res;
});

require_once './routes/artist_routes.php';
require_once './routes/customer_routes.php';

$app->run();
