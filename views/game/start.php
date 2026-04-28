<?php
use App\Repositories\GameRepository;
use App\Repositories\RoundRepository;
use App\Repositories\SessionRepository;
use App\Helpers;

$gameRepo = new GameRepository();
$roundRepo = new RoundRepository();
$sessionRepo = new SessionRepository();

$gameId = $_GET['game_id'] ?? null;
if (!$gameId) {
    Helpers::redirect('/dashboard');
}

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

$rounds = $roundRepo->findByGameId($gameId);
if (empty($rounds)) {
    $_SESSION['error'] = Helpers::t('game.start.error_no_rounds');
    Helpers::redirect('/dashboard/games/edit?id=' . $gameId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Helpers::verifyCsrfToken($csrfToken)) {
        $participants = $_POST['participants'] ?? [];
        $names = array_filter(array_map('trim', $participants));

        if (count($names) >= 1) {
            $firstRound = $rounds[0];
            $sessionId = $sessionRepo->create($gameId, $_SESSION['user_id'], $firstRound['id']);

            foreach ($names as $name) {
                $sessionRepo->addParticipant($sessionId, $name);
            }

            $sessionRepo->updateStatus($sessionId, 'active');
            Helpers::redirect("/game/play?session_id=" . $sessionId);
        } else {
            $_SESSION['error'] = Helpers::t('game.start.error_min_participant');
        }
    }
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800"><?= Helpers::t('game.start.title') ?></h1>
        <p class="text-gray-600"><?= Helpers::e($game['title']) ?></p>
    </div>

    <!-- Round info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <div class="text-blue-800 font-bold mb-2"><?= Helpers::t('game.start.rounds_label', ['count' => count($rounds)]) ?></div>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($rounds as $i => $round): ?>
                <span class="bg-blue-600 text-white text-sm font-bold px-3 py-1 rounded-full">
                    <?= ($i + 1) ?>. <?= Helpers::e($round['name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-md border-t-4 border-green-500">
        <h2 class="text-xl font-bold mb-6 text-gray-700"><?= Helpers::t('game.start.add_participants') ?></h2>

        <form action="/game/start?game_id=<?= $gameId ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">

            <div id="participants-container" class="space-y-3">
                <div class="flex gap-2">
                    <input type="text" name="participants[]" required
                        placeholder="<?= Helpers::e(Helpers::t('game.start.participant_placeholder')) ?>"
                        class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex gap-2">
                    <input type="text" name="participants[]"
                        placeholder="<?= Helpers::e(Helpers::t('game.start.participant_placeholder')) ?>"
                        class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <button type="button" onclick="addParticipantInput()" class="text-green-600 hover:underline font-medium text-sm">
                <?= Helpers::t('game.start.add_more') ?>
            </button>

            <div class="pt-6 border-t">
                <button type="submit" class="w-full bg-green-500 text-white font-bold py-4 rounded-lg hover:bg-green-600 transition shadow-lg text-lg">
                    <?= Helpers::t('game.start.submit') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addParticipantInput() {
    const container = document.getElementById('participants-container');
    const placeholder = <?= json_encode(Helpers::t('game.start.participant_placeholder')) ?>;
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.innerHTML = `
        <input type="text" name="participants[]" placeholder="${placeholder}"
            class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
        <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 px-2">&times;</button>
    `;
    container.appendChild(div);
}
</script>

<?php
$content = ob_get_clean();
$title = Helpers::t('game.start.page_title');
include __DIR__ . '/../layout/main.php';
?>
