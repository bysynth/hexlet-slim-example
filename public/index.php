<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
$container->set(
    'renderer',
    function () {
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    }
);

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$users = json_decode(file_get_contents('files/users.json'), true);

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
);

$app->get(
    '/users/{id:[0-9]+}',
    function ($request, $response, $args) use ($users) {
        $id = $args['id'];
        $params = [
            'id' => $id,
            'nickname' => $users[$id]['nickname'],
        ];

        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
);

$app->get(
    '/users/new',
    function ($request, $response) {
        return $this->get('renderer')->render($response, 'users/new.phtml');
    }
);

$app->post(
    '/users',
    function ($request, $response) use ($users) {
        $userData = $request->getParsedBodyParam('user');
        $id = mt_rand(1, 100);
        $users[$id] = $userData;
        file_put_contents('files/users.json', json_encode($users, JSON_PRETTY_PRINT));

        return $response->withRedirect('/users', 302);
    }
);

$app->run();
