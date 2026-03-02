<?php
use App\Repositories\GameRepository;
use App\Helpers;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = "CSRF катасы.";
    } elseif (empty($title)) {
        $_SESSION['error'] = "Оюндун аталышын жазыңыз.";
    } else {
        $gameRepo = new GameRepository();
        $gameId = $gameRepo->create($_SESSION['user_id'], $title);
        if ($gameId) {
            Helpers::redirect("/dashboard/games/edit?id=" . $gameId);
        } else {
            $_SESSION['error'] = "Оюн түзүүдө ката кетти.";
        }
    }
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800">Жаңы оюн түзүү</h1>
        <a href="/dashboard" class="text-blue-600 hover:underline">← Артка</a>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-md border-t-4 border-blue-600">
        <form action="/dashboard/games/create" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            
            <div>
                <label class="block text-gray-700 font-bold mb-2">Оюндун аталышы</label>
                <input type="text" name="title" required 
                    class="w-full px-4 py-3 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-lg"
                    placeholder="Мисалы: Тарых боюнча викторина"
                    maxlength="255">
                <p class="mt-2 text-sm text-gray-500">Бул аталыш оюндун тизмесинде көрүнөт.</p>
            </div>

            <div class="pt-4">
                <button type="submit" 
                    class="w-full bg-blue-600 text-white font-bold py-4 rounded-lg hover:bg-blue-700 transition shadow-lg text-lg text-center">
                    Оюнду түзүү жана суроолорду кошуу
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Жаңы оюн түзүү - Своя Игра";
include __DIR__ . '/../../layout/main.php';
?>
