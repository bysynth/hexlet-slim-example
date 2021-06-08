<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

$users = json_decode(file_get_contents('files/users.json'), true);

$container = new Container();
$container->set(
    'renderer',
    function () {
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    }
);

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get(
    '/',
    function ($request, $response) use ($router) {
        return $response->withRedirect($router->urlFor('users'), 302);
    }
);

$app->get(
    '/users',
    function ($request, $response) use ($users) {
        $term = $request->getQueryParam('term');

        if (!empty($term) && !empty($users)) {
            $names = array_filter(
                $users,
                fn($user) => strpos(strtolower($user['nickname']), strtolower($term)) !== false
            );
        }

        $params = [
            'users' => $users ?? [],
            'names' => $names ?? [],
            'term' => $term,
        ];

        return $this->get('renderer')->render($response, 'users/index.phtml', $params);
    }
)->setName('users');

$app->post(
    '/users',
    function ($request, $response) use ($users, $router) {
        $userData = $request->getParsedBodyParam('user');
        $id = uniqid();
        $users[$id] = $userData;
        file_put_contents('files/users.json', json_encode($users, JSON_PRETTY_PRINT));

        return $response->withRedirect($router->urlFor('user', ['id' => $id]), 302);
    }
);

$app->get(
    '/users/new',
    function ($request, $response) {
        return $this->get('renderer')->render($response, 'users/new.phtml');
    }
)->setName('new-user');

$app->get(
    '/users/{id}',
    function ($request, $response, $args) use ($users) {
        $id = $args['id'];
        if (!array_key_exists($id, $users)) {
            return $response->withStatus(404);
        }
        $params = [
            'id' => $id,
            'nickname' => $users[$id]['nickname'],
        ];

        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
)->setName('user');

$app->run();
