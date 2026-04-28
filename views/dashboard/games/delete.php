<?php
use App\Repositories\GameRepository;
use App\Helpers;

$gameRepo = new GameRepository();
$gameId = $_POST['id'] ?? $_GET['id'] ?? null;

if (!$gameId) {
    Helpers::redirect('/dashboard');
}

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    $_SESSION['error'] = Helpers::t('game.delete.not_found');
    Helpers::redirect('/dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Helpers::verifyCsrfToken($csrfToken)) {
        if ($gameRepo->delete($gameId)) {
            $_SESSION['success'] = Helpers::t('game.delete.success');
        } else {
            $_SESSION['error'] = Helpers::t('game.delete.failed');
        }
    } else {
        $_SESSION['error'] = Helpers::t('game.delete.csrf_error');
    }
    Helpers::redirect('/dashboard');
}

ob_start();
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-md border-t-4 border-red-600">
    <h2 class="text-2xl font-bold mb-4 text-gray-800"><?= Helpers::t('game.delete.heading') ?></h2>
    <p class="text-gray-600 mb-6">
        <?= Helpers::t('game.delete.confirm_text', ['title' => Helpers::e($game['title'])]) ?>
    </p>

    <form action="/dashboard/games/delete" method="POST" class="flex gap-4">
        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
        <input type="hidden" name="id" value="<?= $gameId ?>">
        <a href="/dashboard" class="flex-1 bg-gray-200 text-gray-800 font-bold py-3 rounded-lg hover:bg-gray-300 transition text-center">
            <?= Helpers::t('game.delete.cancel_btn') ?>
        </a>
        <button type="submit" class="flex-1 bg-red-600 text-white font-bold py-3 rounded-lg hover:bg-red-700 transition">
            <?= Helpers::t('game.delete.confirm_btn') ?>
        </button>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = Helpers::t('game.delete.page_title');
include __DIR__ . '/../../layout/main.php';
?>
