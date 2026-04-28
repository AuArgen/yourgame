<?php
use App\Repositories\SessionRepository;
use App\Helpers;

$sessionRepo = new SessionRepository();
$history = $sessionRepo->getSessionHistory($_SESSION['user_id']);

ob_start();
?>

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800"><?= Helpers::t('history.title') ?></h1>
        <a href="/dashboard" class="text-blue-600 hover:underline"><?= Helpers::t('history.back') ?></a>
    </div>

    <?php if (empty($history)): ?>
        <div class="bg-white p-12 rounded-xl shadow-md text-center">
            <p class="text-gray-500"><?= Helpers::t('history.empty') ?></p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold text-gray-700"><?= Helpers::t('history.game_col') ?></th>
                        <th class="px-6 py-4 font-bold text-gray-700"><?= Helpers::t('history.date_col') ?></th>
                        <th class="px-6 py-4 font-bold text-gray-700"><?= Helpers::t('history.players_col') ?></th>
                        <th class="px-6 py-4 font-bold text-gray-700"><?= Helpers::t('history.winner_col') ?></th>
                        <th class="px-6 py-4 font-bold text-gray-700"><?= Helpers::t('history.action_col') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($history as $session): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= Helpers::e($session['game_title']) ?></div>
                                <div class="text-xs text-gray-400">ID: #<?= $session['id'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 text-sm">
                                <?= date('d.m.Y H:i', strtotime($session['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?= $session['players_count'] ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">
                                    🏆 <?= Helpers::e($session['winner_name'] ?? Helpers::t('history.unknown_winner')) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="/game/summary?session_id=<?= $session['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= Helpers::t('history.results_link') ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = Helpers::t('history.page_title');
include __DIR__ . '/../layout/main.php';
?>
