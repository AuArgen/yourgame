<?php

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Error handling
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Repositories/UserRepository.php';
require_once __DIR__ . '/../src/Repositories/RoundRepository.php';
require_once __DIR__ . '/../src/Repositories/GameRepository.php';
require_once __DIR__ . '/../src/Repositories/CategoryRepository.php';
require_once __DIR__ . '/../src/Repositories/QuestionRepository.php';
require_once __DIR__ . '/../src/Repositories/SessionRepository.php';
require_once __DIR__ . '/../src/Repositories/LogRepository.php';

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Serve uploaded images (needed when document root is public/)
if (preg_match('#^/storage/images/([A-Za-z0-9_.-]+)$#', $uri, $m)) {
    $path = __DIR__ . '/../storage/images/' . $m[1];
    if (is_file($path)) {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            header('Content-Type: ' . $mime);
            readfile($path);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

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
        include __DIR__ . '/../views/errors/404.php';
        break;
}
