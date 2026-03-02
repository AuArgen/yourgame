<?php
use App\Repositories\GameRepository;
use App\Repositories\SessionRepository;
use App\Helpers;

$gameRepo = new GameRepository();
$sessionRepo = new SessionRepository();

$gameId = $_GET['game_id'] ?? null;
if (!$gameId) {
    Helpers::redirect('/dashboard');
}

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Helpers::verifyCsrfToken($csrfToken)) {
        $participants = $_POST['participants'] ?? [];
        $names = array_filter(array_map('trim', $participants));

        if (count($names) >= 1) {
            // Создаем сессию
            $sessionId = $sessionRepo->create($gameId, $_SESSION['user_id']);
            
            // Добавляем участников
            foreach ($names as $name) {
                $sessionRepo->addParticipant($sessionId, $name);
            }
            
            // Обновляем статус сессии на active
            $sessionRepo->updateStatus($sessionId, 'active');
            
            Helpers::redirect("/game/play?session_id=" . $sessionId);
        } else {
            $_SESSION['error'] = "Кеминде бир катышуучунун атын жазыңыз.";
        }
    }
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Оюнду баштоо</h1>
        <p class="text-gray-600"><?= Helpers::e($game['title']) ?></p>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-md border-t-4 border-green-500">
        <h2 class="text-xl font-bold mb-6 text-gray-700">Катышуучуларды кошуу</h2>
        
        <form action="/game/start?game_id=<?= $gameId ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            
            <div id="participants-container" class="space-y-3">
                <div class="flex gap-2">
                    <input type="text" name="participants[]" required placeholder="Катышуучунун аты" 
                        class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex gap-2">
                    <input type="text" name="participants[]" placeholder="Катышуучунун аты" 
                        class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <button type="button" onclick="addParticipantInput()" class="text-green-600 hover:underline font-medium text-sm">
                + Дагы катышуучу кошуу
            </button>

            <div class="pt-6 border-t">
                <button type="submit" class="w-full bg-green-500 text-white font-bold py-4 rounded-lg hover:bg-green-600 transition shadow-lg text-lg">
                    Оюнду баштоо!
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addParticipantInput() {
    const container = document.getElementById('participants-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.innerHTML = `
        <input type="text" name="participants[]" placeholder="Катышуучунун аты" 
            class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
        <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 px-2">&times;</button>
    `;
    container.appendChild(div);
}
</script>

<?php
$content = ob_get_clean();
$title = "Оюнду баштоо - Своя Игра";
include __DIR__ . '/../layout/main.php';
?>
