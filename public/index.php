<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

require __DIR__ . '/../vendor/autoload.php';

session_start();

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

$login = 'admin';

$app->get(
    '/',
    function ($request, $response) use ($router) {
        if (isset($_SESSION['isAdmin'])) {
            return $response->withRedirect($router->urlFor('users'));
        }

        $messages = $this->get('flash')->getMessages();
        $params = [
            'email' => '',
            'flash' => $messages ?? []
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }
)->setName('root');

$app->post(
    '/login',
    function ($request, $response) use ($login, $router) {
        $loginData = $request->getParsedBodyParam('email');

        if ($loginData === $login) {
            $_SESSION['isAdmin'] = true;

            return $response->withRedirect($router->urlFor('users'));
        }

        $this->get('flash')->addMessage('error', 'Access Denied!');

        return $response->withRedirect($router->urlFor('root'));
    }
);

$app->post(
    '/logout',
    function ($request, $response) use ($router) {

        $_SESSION = [];

        return $response->withRedirect($router->urlFor('root'));
    }
);

$app->get(
    '/users',
    function ($request, $response) use ($router) {
        if (!isset($_SESSION['isAdmin'])) {
            $this->get('flash')->addMessage('error', 'Access Denied! Please login!');
            return $response->withRedirect($router->urlFor('root'));
        }

        $users = json_decode($request->getCookieParam('users'), true);
        $term = $request->getQueryParam('term');
        $messages = $this->get('flash')->getMessages();

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
    function ($request, $response) use ($router) {
        $users = json_decode($request->getCookieParam('users'), true);
        $userData = $request->getParsedBodyParam('user');

        $validator = new Validator();
        $errors = $validator->validate($userData);

        if (count($errors) === 0) {
            $id = uniqid();
            $users[$id] = $userData;
            $encodedUsers = json_encode($users);

            $this->get('flash')->addMessage('success', "User with id $id have been added");

            return $response->withHeader('Set-Cookie', "users=$encodedUsers;path=/")
                ->withRedirect($router->urlFor('users'));
        }

        $params = [
            'userData' => $userData,
            'errors' => $errors,
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
    }
);

$app->get(
    '/users/new',
    function ($request, $response) use ($router) {
        if (!isset($_SESSION['isAdmin'])) {
            $this->get('flash')->addMessage('error', 'Access Denied! Please login!');
            return $response->withRedirect($router->urlFor('root'));
        }

        $params = [
            'userData' => [],
            'errors' => []
        ];
        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    }
)->setName('new-user');

$app->get(
    '/users/{id}',
    function ($request, $response, $args) use ($router) {
        if (!isset($_SESSION['isAdmin'])) {
            $this->get('flash')->addMessage('error', 'Access Denied! Please login!');
            return $response->withRedirect($router->urlFor('root'));
        }

        $id = $args['id'];
        $users = json_decode($request->getCookieParam('users'), true);

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
    function ($request, $response, $args) use ($router) {
        if (!isset($_SESSION['isAdmin'])) {
            $this->get('flash')->addMessage('error', 'Access Denied! Please login!');
            return $response->withRedirect($router->urlFor('root'));
        }

        $messages = $this->get('flash')->getMessages();
        $id = $args['id'];
        $users = json_decode($request->getCookieParam('users'), true);
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
    function ($request, $response, $args) use ($router) {
        $id = $args['id'];
        $users = json_decode($request->getCookieParam('users'), true);
        $user = $users[$id];
        $userData = $request->getParsedBodyParam('user');

        $validator = new Validator();
        $errors = $validator->validate($userData);

        if (count($errors) === 0) {
            $user['nickname'] = $userData['nickname'];
            $user['email'] = $userData['email'];
            $users[$id] = $user;
            $encodedUsers = json_encode($users);

            $this->get('flash')->addMessage('success', "Users data with id $id have been updated");

            return $response->withHeader('Set-Cookie', "users=$encodedUsers;path=/")
                ->withRedirect($router->urlFor('users'));
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
    function ($request, $response, $args) use ($router) {
        $id = $args['id'];
        $users = json_decode($request->getCookieParam('users'), true);
        unset($users[$id]);
        $encodedUser = json_encode($users);

        $this->get('flash')->addMessage('success', "User with id $id have been removed");

        return $response->withHeader('Set-Cookie', "users=$encodedUser;path=/")
            ->withRedirect($router->urlFor('users'), 302);
    }
);

$app->run();
