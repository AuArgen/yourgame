<?php

namespace App;

use App\Repositories\UserRepository;
use App\Repositories\GameRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\QuestionRepository;

class Auth {
    private $userRepository;
    private $gameRepository;
    private $db;

    public function __construct() {
        $this->userRepository = new UserRepository();
        $this->gameRepository = new GameRepository();
        $this->db = Database::getInstance()->getConnection();

        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'use_strict_mode' => true,
            ]);
        }
    }

    public function register($username, $email, $password) {
        if ($this->userRepository->findByUsername($username)) {
            return ['success' => false, 'message' => Helpers::t('auth.register.username_exists')];
        }
        if ($this->userRepository->findByEmail($email)) {
            return ['success' => false, 'message' => Helpers::t('auth.register.email_exists')];
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $userId = $this->userRepository->create($username, $email, $passwordHash);

        if ($userId) {
            $this->gameRepository->createDemoGame($userId);
            $this->loginById($userId);
            return ['success' => true];
        }

        return ['success' => false, 'message' => Helpers::t('auth.register.failed')];
    }

    public function login($email, $password) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($this->isBlocked($ip)) {
            return ['success' => false, 'message' => Helpers::t('auth.login.too_many_attempts')];
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->clearAttempts($ip);
            $this->loginById($user['id']);
            return ['success' => true];
        }

        $this->logAttempt($ip);
        return ['success' => false, 'message' => Helpers::t('auth.login.invalid')];
    }

    private function loginById($userId) {
        $_SESSION['user_id'] = $userId;
        session_regenerate_id(true);
    }

    public function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function check() {
        return isset($_SESSION['user_id']);
    }

    public function user() {
        if (!$this->check()) return null;
        return $this->userRepository->findById($_SESSION['user_id']);
    }

    private function logAttempt($ip) {
        $stmt = $this->db->prepare("INSERT INTO auth_attempts (ip_address) VALUES (:ip)");
        $stmt->execute(['ip' => $ip]);
    }

    private function isBlocked($ip) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM auth_attempts
            WHERE ip_address = :ip
            AND attempt_time > NOW() - INTERVAL '1 minute'
        ");
        $stmt->execute(['ip' => $ip]);
        $attempts = $stmt->fetchColumn();

        return $attempts >= 5;
    }

    private function clearAttempts($ip) {
        $stmt = $this->db->prepare("DELETE FROM auth_attempts WHERE ip_address = :ip");
        $stmt->execute(['ip' => $ip]);
    }
}
