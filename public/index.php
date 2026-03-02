<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Repositories/UserRepository.php';
require_once __DIR__ . '/../src/Repositories/GameRepository.php';
require_once __DIR__ . '/../src/Repositories/CategoryRepository.php';
require_once __DIR__ . '/../src/Repositories/QuestionRepository.php';
require_once __DIR__ . '/../src/Repositories/SessionRepository.php';
require_once __DIR__ . '/../src/Repositories/LogRepository.php';

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Простой роутинг
switch ($uri) {
    case '/':
        if (isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/dashboard');
        } else {
            include __DIR__ . '/../views/home.php';
        }
        break;

    case '/login':
        include __DIR__ . '/../views/auth/login.php';
        break;

    case '/register':
        include __DIR__ . '/../views/auth/register.php';
        break;

    case '/logout':
        $auth = new \App\Auth(new \App\Repositories\UserRepository());
        $auth->logout();
        \App\Helpers::redirect('/');
        break;

    case '/dashboard':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/dashboard/index.php';
        break;

    case '/dashboard/games/create':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/dashboard/games/create.php';
        break;

    case '/dashboard/games/edit':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/dashboard/games/edit.php';
        break;

    case '/dashboard/games/delete':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/dashboard/games/delete.php';
        break;

    case '/game/start':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/game/start.php';
        break;

    case '/game/play':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/game/play.php';
        break;

    case '/game/summary':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/game/summary.php';
        break;

    case '/dashboard/history':
        if (!isset($_SESSION['user_id'])) {
            \App\Helpers::redirect('/login');
        }
        include __DIR__ . '/../views/dashboard/history.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
