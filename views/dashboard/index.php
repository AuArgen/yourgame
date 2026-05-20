<?php
use App\Repositories\GameRepository;
use App\Helpers;

$gameRepo = new GameRepository();
$games = $gameRepo->findByUserId($_SESSION['user_id']);

ob_start();
?>

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800"><?= Helpers::t('dashboard.title') ?></h1>
    <a href="/dashboard/games/create" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">
        <?= Helpers::t('dashboard.create_btn') ?>
    </a>
</div>

<?php if (empty($games)): ?>
    <div class="bg-white p-12 rounded-xl shadow-md text-center border-2 border-dashed border-gray-300">
        <h3 class="text-2xl font-bold text-gray-400 mb-4"><?= Helpers::t('dashboard.no_games_title') ?></h3>
        <p class="text-gray-500 mb-8"><?= Helpers::t('dashboard.no_games_desc') ?></p>
        <a href="/dashboard/games/create" class="bg-blue-100 text-blue-600 px-8 py-3 rounded-full font-bold hover:bg-blue-200 transition">
            <?= Helpers::t('dashboard.create_first_btn') ?>
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($games as $game): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden border-b-4 border-blue-500 hover:shadow-lg transition">
                <div class="p-6">
                    <!-- Title row: static view -->
                    <div id="title-view-<?= $game['id'] ?>" class="flex items-center justify-between mb-2 gap-2">
                        <h3 class="text-xl font-bold text-gray-800 truncate"><?= Helpers::e($game['title']) ?></h3>
                        <button onclick="showRename(<?= $game['id'] ?>, <?= htmlspecialchars(json_encode($game['title']), ENT_QUOTES) ?>)"
                            class="text-gray-400 hover:text-blue-500 transition flex-shrink-0" title="<?= Helpers::t('dashboard.rename_save') ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.364-6.364a2 2 0 012.828 2.828L11.828 13.828a2 2 0 01-1.414.586H8v-2.414a2 2 0 01.586-1.414z"/>
                            </svg>
                        </button>
                    </div>
                    <!-- Title row: rename form (hidden) -->
                    <form id="rename-form-<?= $game['id'] ?>" class="hidden mb-2" action="/dashboard/games/rename" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                        <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                        <div class="flex gap-2">
                            <input type="text" name="title" id="rename-input-<?= $game['id'] ?>" required maxlength="120"
                                class="flex-1 px-3 py-1.5 border-2 border-blue-400 rounded-lg text-sm font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-blue-700 transition">
                                <?= Helpers::t('dashboard.rename_save') ?>
                            </button>
                            <button type="button" onclick="hideRename(<?= $game['id'] ?>)"
                                class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-300 transition">
                                <?= Helpers::t('dashboard.rename_cancel') ?>
                            </button>
                        </div>
                    </form>

                    <div class="flex justify-between text-sm text-gray-500 mb-4">
                        <span><?= Helpers::t('dashboard.rounds') ?> <?= $game['rounds_count'] ?></span>
                        <span><?= Helpers::t('dashboard.questions') ?> <?= $game['questions_count'] ?></span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="/game/start?game_id=<?= $game['id'] ?>" class="flex-1 bg-green-500 text-white text-center py-2 rounded hover:bg-green-600 transition font-bold"><?= Helpers::t('dashboard.play_btn') ?></a>
                        <a href="/dashboard/games/edit?id=<?= $game['id'] ?>" class="flex-1 bg-gray-100 text-gray-700 text-center py-2 rounded hover:bg-gray-200 transition"><?= Helpers::t('dashboard.edit_btn') ?></a>
                        <a href="/dashboard/games/delete?id=<?= $game['id'] ?>" class="p-2 text-red-500 hover:bg-red-50 rounded transition" title="Удалить">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function showRename(id, currentTitle) {
    document.getElementById('title-view-' + id).classList.add('hidden');
    const form = document.getElementById('rename-form-' + id);
    form.classList.remove('hidden');
    const input = document.getElementById('rename-input-' + id);
    input.value = currentTitle;
    input.focus();
    input.select();
}
function hideRename(id) {
    document.getElementById('rename-form-' + id).classList.add('hidden');
    document.getElementById('title-view-' + id).classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
$title = Helpers::t('dashboard.page_title');
include __DIR__ . '/../layout/main.php';
?>
