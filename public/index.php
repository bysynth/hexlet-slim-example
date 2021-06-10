<?php

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

require __DIR__ . '/../vendor/autoload.php';

session_start();

$users = json_decode(file_get_contents('files/users.json'), true);

$container = new Container();
$container->set(
    'renderer',
    function () {
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    }
);
$container->set(
    'flash',
    function () {
        return new \Slim\Flash\Messages();
    }
);

AppFactory::setContainer($container);
$app = AppFactory::create();

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
        $messages = $this->get('flash')->getMessages();
        $term = $request->getQueryParam('term');

        if (!empty($term) && !empty($users)) {
            $names = array_filter(
                $users,
                fn($user) => strpos(strtolower($user['nickname']), strtolower($term)) !== false
            );
        }

        $params = [
            'flash' => $messages ?? [],
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

        $validator = new Validator();
        $errors = $validator->validate($userData);

        if (count($errors) === 0) {
            $id = uniqid();
            $users[$id] = $userData;
            file_put_contents('files/users.json', json_encode($users, JSON_PRETTY_PRINT));
            $this->get('flash')->addMessage('success', "Пользователь c id $id успешно добавлен");

            return $response->withRedirect($router->urlFor('users'), 302);
        }

        $params = [
            'userData' => $userData,
            'errors' => $errors
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
    }
);

$app->get(
    '/users/new',
    function ($request, $response) {
        $params = [
            'userData' => [],
            'errors' => []
        ];
        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    }
)->setName('new-user');

$app->get(
    '/users/{id}',
    function ($request, $response, $args) use ($users) {
        $id = $args['id'];

        if (!array_key_exists($id, $users)) {
            return $response->write('Page not found')->withStatus(404);
        }

        $params = [
            'id' => $id,
            'nickname' => $users[$id]['nickname'],
        ];

        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
)->setName('user');

$app->run();
