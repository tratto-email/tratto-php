<?php

declare(strict_types=1);

// Router for `php -S` used by CurlTransportTest. Echoes the request back as JSON.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/no-content':
        http_response_code(204);
        return true;
    case '/not-json':
        http_response_code(502);
        header('Content-Type: text/html');
        echo '<html>Bad Gateway</html>';
        return true;
    case '/redirect':
        http_response_code(302);
        header('Location: /target');
        echo '{"redirected":false}';
        return true;
    case '/target':
        header('Content-Type: application/json');
        echo '{"reached":"target"}';
        return true;
    case '/slow':
        sleep(1);
        echo '{}';
        return true;
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}
header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
]);
return true;
