<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;

/**
 * GET all the artists 
 */
$app->get('/artists', function (Request $req, Response $res, $args) use ($db) {
    $rows = $db->query('SELECT * from artist')->fetch_all(MYSQLI_ASSOC);
    $data = json_encode($rows);
    $res->getBody()->write($data);
    return $res;
});

/**
 * Get a specific artist
 */
$app->get('/artists/{artist_id}', function (Request $req, Response $res, $args) use ($db) {
    $id = intval($args['artist_id']);

    if ($id < 1) {
        throw new HttpBadRequestException($req, 'Invalid artist_id parameter!');
    }

    $stmt = $db->prepare('SELECT * FROM artist WHERE ArtistId = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $result = $stmt->get_result()->fetch_assoc();

    if (empty($result)) {
        throw new HttpNotFoundException($req, "Artist $id not found!");
    }

    $res->getBody()->write(json_encode($result));
    return $res;
});

/**
 * Get the albums of a specific artist
 */
$app->get('/artists/{artist_id}/albums', function (Request $req, Response $res, $args) use ($db) {
    $id = intval($args['artist_id']);

    if ($id < 1) {
        throw new HttpBadRequestException($req, 'Invalid artist_id parameter');
    }

    $stmt = $db->prepare('SELECT AlbumId, Title FROM artist as r JOIN album as l ON r.ArtistId = l.ArtistId WHERE r.ArtistId = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $res->getBody()->write(json_encode($result));
    return $res;
});

/**
 * Get the tracks of a specific album from a specific artist
 */
$app->get('/artists/{artist_id}/albums/{album_id}/tracks', function (Request $req, Response $res, $args) use ($db) {
    $artist_id = intval($args['artist_id']);
    $album_id = intval($args['album_id']);
    $query_params = $req->getQueryParams();

    if ($artist_id < 1) {
        throw new HttpBadRequestException($req, 'Invalid artist_id parameter!');
    } else if ($album_id < 1) {
        throw new HttpBadRequestException($req, 'Invalid album_id parameter!');
    }

    // Check if genre or mediaType query param exists
    $genre = array_key_exists('genre', $query_params) ? $query_params['genre'] : false;
    $media_type = array_key_exists('mediaType', $query_params) ? $query_params['mediaType'] : false;

    $select = 'SELECT TrackId, t.Name, m.Name as MediaType, g.Name as Genre, Composer, Milliseconds, Bytes, UnitPrice';
    $from = 'FROM artist as r JOIN album as l ON r.ArtistId = l.ArtistId JOIN track as t ON l.AlbumId = t.AlbumId JOIN genre as g ON t.GenreId = g.GenreId JOIN mediatype as m ON t.MediaTypeId = m.MediaTypeId';
    $where = 'WHERE r.ArtistId = ? AND l.AlbumId = ?';

    $types = 'ii';
    $params = [$artist_id, $album_id];

    // If genre or mediaType query param exists, add filter to the WHERE
    // clause of the query
    if ($genre) {
        $where .= ' AND g.Name = ?';
        $types .= 's';
        $params[] = $genre;
    }
    if ($media_type) {
        $where .= ' AND m.Name = ?';
        $types .= 's';
        $params[] = $media_type;
    }

    $stmt = $db->prepare("$select $from $where");
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->getBody()->write(json_encode($result));
    return $res;
});

/**
 * Utility function for checking if a POST body is a valid new artist
 */
$isNewArtistValid = function (array|null $artist) {
    return !empty($artist) && !empty($artist['name']) &&
        is_string($artist['name']) && count($artist) == 1;
};

/**
 * Utility function if an associative array is a valid artist
 */
$isArtistValid = function (array|null $artist) {
    return !empty($artist) && !empty($artist['id']) &&
        is_int($artist['id']) && !empty($artist['name']) &&
        is_string($artist['name']) && count($artist) == 2;
};

/**
 * Utility function for checking if an array is associative or not
 */
function isAssoc(array|null $arr)
{
    return !empty($arr) && array_keys($arr) == range(0, count($arr) - 1);
}

/**
 * Utility function for checking if the request body is valid based on the
 * passed validator
 */
function parseArtistBody(array|null $body, callable $validator)
{
    $ret = [];
    if (isAssoc($body)) {
        foreach ($body as $artist) {
            if ($validator($artist)) {
                $ret[] = $artist;
            }
        }
    } else if ($validator($body)) {
        $ret[] = $body;
    }

    return empty($ret) ? false : $ret;
}

/**
 * Creates a new artist from the given POST body
 */
$app->post('/artists', function (Request $req, Response $res, $args) use ($db, $isNewArtistValid) {
    $body = $req->getParsedBody();
    $parsedBody = parseArtistBody($body, $isNewArtistValid);

    if (!$parsedBody) {
        throw new HttpBadRequestException($req, 'Invalid New Artist(s)!');
    }

    // Since DB doesn't use autoincrement, need to get the last ArtistId
    $finalId = intval($db->query('SELECT MAX(ArtistId) as FinalId FROM artist')->fetch_column());
    $types = '';
    $params = '';

    // add a SQL query type and param per artist POSTed in the body
    foreach ($parsedBody as $key => $_) {
        $id = $finalId + $key + 1;
        $types .= 's';
        if ($key == count($parsedBody) - 1) {
            $params .= "($id, ?)";
        } else {
            $params .= "($id, ?), ";
        }
    }

    $stmt = $db->prepare("INSERT INTO artist (ArtistId, Name) VALUES $params");
    // Unwrapping the associative array to just be an array of strings
    $stmt->bind_param($types, ...(array_map(fn ($item) => $item['name'], $parsedBody)));
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $res->getBody()->write(json_encode([
        'message' => 'Artist(s) creation successful!'
    ]));

    return $res->withStatus(201);
});

/**
 * Update an artist from the given PUT body
 */
$app->put('/artists', function (Request $req, Response $res, $args) use ($db, $isArtistValid) {
    $body = $req->getParsedBody();
    $parsedBody = parseArtistBody($body, $isArtistValid);

    if (!$parsedBody) {
        throw new HttpBadRequestException($req, 'Invalid Artist(s)!');
    }

    $stmt = $db->prepare('UPDATE artist SET Name = ? WHERE ArtistId = ?');
    $stmt->bind_param('si', $name, $id);

    // Uses a prepared statement to update multiple artists based on the body of
    // the request
    $db->begin_transaction();
    foreach ($parsedBody as $artist) {
        $id = $artist['id'];
        $name = $artist['name'];
        $stmt->execute();
    }
    $stmt->close();
    if (!$db->commit()) {
        throw new HttpNotFoundException($req, 'Artist(s) does not exist!');
    }

    $res->getBody()->write(json_encode([
        'message' => 'Artist(s) updated successfully!'
    ]));

    return $res;
});

/**
 * Delete an artist based on the artist id
 */
$app->delete('/artists/{artist_id}', function (Request $req, Response $res, $args) use ($db) {
    $id = intval($args['artist_id']);

    if ($id < 1) {
        throw new HttpBadRequestException($req, 'Invalid artist_id parameter!');
    }

    $stmt = $db->prepare('DELETE FROM artist WHERE ArtistId = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new HttpInternalServerErrorException($req, 'Something broke!');
    }

    $res->getBody()->write(json_encode([
        'message' => "Artist {$id} successfully deleted!"
    ]));

    return $res;
});
