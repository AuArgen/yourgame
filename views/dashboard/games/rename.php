<?php
use App\Repositories\GameRepository;
use App\Helpers;

$gameRepo = new GameRepository();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::redirect('/dashboard');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!Helpers::verifyCsrfToken($csrfToken)) {
    $_SESSION['error'] = Helpers::t('game.edit.csrf_error');
    Helpers::redirect('/dashboard');
}

$gameId = $_POST['game_id'] ?? null;
$title  = trim($_POST['title'] ?? '');

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

if ($title !== '') {
    if ($gameRepo->update($gameId, $title)) {
        $_SESSION['success'] = Helpers::t('dashboard.rename_success');
    } else {
        $_SESSION['error'] = Helpers::t('dashboard.rename_error');
    }
}

Helpers::redirect('/dashboard');
