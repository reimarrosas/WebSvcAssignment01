<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;

/**
 * Get all the customers
 */
$app->get('/customers', function (Request $req, Response $res, $args) use ($db) {
    $query_params = $req->getQueryParams();
    $country = array_key_exists('country', $query_params) ? $query_params['country'] : false;

    $query = 'SELECT * FROM customer';
    $result = [];
    if ($country) {
        $stmt = $db->prepare($query . ' WHERE Country = ?');
        $stmt->bind_param('s', $country);
        if (!$stmt->execute()) {
            throw new HttpInternalServerErrorException($req, 'Something broke!');
        }
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $db->query('SELECT * FROM customer')->fetch_all(MYSQLI_ASSOC);
    }

    $res->getBody()->write(json_encode($result));
    return $res;
});

/**
 * Get the invoices of a specific customer
 */
$app->get('/customers/{customer_id}/invoices', function (Request $req, Response $res, $args) use ($db) {
    $id = intval($args['customer_id']);

    if ($id < 1) {
        throw new HttpBadRequestException($req, 'Invalid customer_id parameter');
    }

    $stmt = $db->prepare(
        'SELECT t.TrackId, t.name, m.Name as MediaType, g.Name as Genre, Composer, Milliseconds, Bytes, t.UnitPrice, il.Quantity ' .
            'FROM customer as c JOIN invoice as i ON c.CustomerId = i.CustomerId JOIN invoiceline as il ON i.InvoiceId = il.InvoiceId ' .
            'JOIN track as t ON il.TrackId = t.TrackId JOIN mediatype as m ON t.MediaTypeId = m.MediaTypeId JOIN genre as g ON t.GenreId = g.GenreId ' .
            'WHERE c.CustomerId = ?'
    );
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->getBody()->write(json_encode($result));

    return $res;
});

// FIXME: Will not actually work since the FK constraint does not cascade on
// DELETE
/**
 * Delete a specific customer using the customer id
 */
$app->delete('/customers/{customer_id}', function (Request $req, Response $res, $args) use ($db) {
    $id = intval($args['customer_id']);

    if ($id < 1) {
        throw new HttpBadRequestException($req, 'Invalid customer_id parameter!');
    }

    $stmt = $db->prepare('DELETE FROM customer WHERE CustomerId = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $res->getBody()->write(json_encode([
        'message' => "Customer $id deleted successfully!"
    ]));

    return $res;
});
