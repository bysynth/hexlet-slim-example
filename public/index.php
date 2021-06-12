<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
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
$app->add(MethodOverrideMiddleware::class);

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
            $this->get('flash')->addMessage('success', "User with id $id have been added");

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
            'user' => $users[$id]
        ];

        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
)->setName('user');

$app->get(
    '/users/{id}/edit',
    function ($request, $response, $args) use ($users) {
        $messages = $this->get('flash')->getMessages();
        $id = $args['id'];
        $userData = $users[$id];
        $params = [
            'id' => $id,
            'userData' => $userData,
            'errors' => [],
            'flash' => $messages
        ];

        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }
)->setName('edit-user');

$app->patch(
    '/users/{id}',
    function ($request, $response, $args) use ($users, $router) {
        $id = $args['id'];
        $user = $users[$id];
        $userData = $request->getParsedBodyParam('user');

        $validator = new Validator();
        $errors = $validator->validate($userData);

        if (count($errors) === 0) {
            $user['nickname'] = $userData['nickname'];
            $user['email'] = $userData['email'];
            $users[$id] = $user;
            file_put_contents('files/users.json', json_encode($users, JSON_PRETTY_PRINT));

            $this->get('flash')->addMessage('success', "Users data with id $id have been updated");

            return $response->withRedirect($router->urlFor('users'));
        }

        $params = [
            'id' => $id,
            'userData' => $userData,
            'errors' => $errors
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'users/edit.phtml', $params);
    }
);

$app->delete(
    '/users/{id}',
    function ($request, $response, $args) use ($users, $router) {
        $id = $args['id'];
        unset($users[$id]);
        file_put_contents('files/users.json', json_encode($users, JSON_PRETTY_PRINT));

        $this->get('flash')->addMessage('success', "User with id $id have been removed");

        return $response->withRedirect($router->urlFor('users'));
    }
);

$app->run();
