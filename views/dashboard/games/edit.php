<?php
use App\Repositories\GameRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\QuestionRepository;
use App\Helpers;

$gameRepo = new GameRepository();
$catRepo = new CategoryRepository();
$questionRepo = new QuestionRepository();

$gameId = $_GET['id'] ?? null;
if (!$gameId) {
    Helpers::redirect('/dashboard');
}

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

// Обработка POST запросов (Добавление категории, добавление вопроса)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = "CSRF катасы.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_category') {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $catRepo->create($gameId, $name);
                $_SESSION['success'] = "Категория кошулду.";
            }
        } elseif ($action === 'add_question') {
            $catId = $_POST['category_id'] ?? null;
            $points = $_POST['points'] ?? 100;
            $text = trim($_POST['question_text'] ?? '');
            $answer = trim($_POST['answer_text'] ?? '');
            $timeLimit = $_POST['time_limit'] ?? 30;
            $isCat = isset($_POST['is_cat_in_bag']) ? 'true' : 'false';
            $youtube = trim($_POST['video_url'] ?? '');
            $youtubeId = $youtube ? Helpers::extractYoutubeId($youtube) : null;

            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = Helpers::uploadImage($_FILES['image']);
            }

            if ($catId && !empty($text) && !empty($answer)) {
                $questionRepo->create([
                    'category_id' => $catId,
                    'question_text' => $text,
                    'answer_text' => $answer,
                    'points' => $points,
                    'time_limit' => $timeLimit,
                    'is_cat_in_bag' => $isCat,
                    'video_url' => $youtubeId,
                    'image_url' => $image
                ]);
                $_SESSION['success'] = "Суроо кошулду.";
            }
        } elseif ($action === 'delete_category') {
            $catId = $_POST['category_id'] ?? null;
            $catRepo->delete($catId);
            $_SESSION['success'] = "Категория өчүрүлдү.";
        } elseif ($action === 'delete_question') {
            $qId = $_POST['question_id'] ?? null;
            $questionRepo->delete($qId);
            $_SESSION['success'] = "Суроо өчүрүлдү.";
        }
    }
    Helpers::redirect("/dashboard/games/edit?id=" . $gameId);
}

$categories = $catRepo->findByGameId($gameId);
$fullData = $questionRepo->getFullGameData($gameId);

// Группировка данных для удобного вывода
$gameData = [];
foreach ($categories as $cat) {
    $gameData[$cat['id']] = [
        'name' => $cat['name'],
        'questions' => []
    ];
}
foreach ($fullData as $row) {
    if ($row['question_id']) {
        $gameData[$row['category_id']]['questions'][] = $row;
    }
}

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= Helpers::e($game['title']) ?></h1>
            <p class="text-gray-600">Оюнду редакторлоо</p>
        </div>
        <a href="/dashboard" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition">Артка</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Добавление категории -->
    <div class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-xl font-bold mb-4 text-gray-700">Жаңы категория кошуу</h2>
        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" class="flex gap-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="name" required placeholder="Категориянын аталышы" 
                class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-bold">
                Кошуу
            </button>
        </form>
    </div>

    <!-- Список категорий и вопросов -->
    <div class="space-y-8">
        <?php foreach ($gameData as $catId => $cat): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800"><?= Helpers::e($cat['name']) ?></h3>
                    <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" onsubmit="return confirm('Бул категорияны бардык суроолору менен өчүрөсүзбү?')">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?= $catId ?>">
                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Категорияны өчүрүү</button>
                    </form>
                </div>
                
                <div class="p-6">
                    <!-- Существующие вопросы -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                        <?php 
                        $pointsList = [100, 200, 300, 400, 500];
                        $questionsByPoints = [];
                        foreach ($cat['questions'] as $q) {
                            $questionsByPoints[$q['points']] = $q;
                        }
                        
                        foreach ($pointsList as $p): 
                            $q = $questionsByPoints[$p] ?? null;
                        ?>
                            <div class="border rounded-lg p-4 text-center <?= $q ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-dashed border-gray-300' ?>">
                                <div class="font-bold text-lg mb-1 <?= $q ? 'text-blue-700' : 'text-gray-400' ?>"><?= $p ?></div>
                                <?php if ($q): ?>
                                    <div class="text-xs text-gray-600 truncate mb-2"><?= Helpers::e($q['question_text']) ?></div>
                                    <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" onsubmit="return confirm('Суроону өчүрөсүзбү?')">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                        <button type="submit" class="text-red-500 hover:underline text-xs">Өчүрүү</button>
                                    </form>
                                <?php else: ?>
                                    <button onclick="openQuestionModal(<?= $catId ?>, <?= $p ?>)" class="text-blue-500 hover:underline text-sm font-medium">+ Кошуу</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Модальное окно для добавления вопроса -->
<div id="questionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-2xl font-bold text-gray-800">Жаңы суроо кошуу (<span id="modalPoints"></span> упай)</h3>
            <button onclick="closeQuestionModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_question">
            <input type="hidden" name="category_id" id="modalCatId">
            <input type="hidden" name="points" id="modalPointsInput">

            <div>
                <label class="block text-gray-700 font-bold mb-1">Суроо тексти</label>
                <textarea name="question_text" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-1">Туура жооп</label>
                <input type="text" name="answer_text" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-1">Таймер (секунд)</label>
                    <input type="number" name="time_limit" value="30" min="5" max="300" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="flex items-center pt-6">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="is_cat_in_bag" class="w-5 h-5 text-blue-600 rounded">
                        <span class="text-gray-700 font-bold">Мышык чыгуу (Кот в мешке)</span>
                    </label>
                </div>
            </div>

            <div class="border-t pt-4">
                <h4 class="font-bold text-gray-700 mb-2">Медиа (милдеттүү эмес)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Сүрөт жүктөө</label>
                        <input type="file" name="image" accept="image/*" class="text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">YouTube шилтемеси</label>
                        <input type="url" name="video_url" placeholder="https://youtube.com/..." class="w-full px-4 py-2 border rounded-lg text-sm">
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition">
                    Сактоо
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuestionModal(catId, points) {
    document.getElementById('modalCatId').value = catId;
    document.getElementById('modalPoints').innerText = points;
    document.getElementById('modalPointsInput').value = points;
    document.getElementById('questionModal').classList.remove('hidden');
    document.getElementById('questionModal').classList.add('flex');
}

function closeQuestionModal() {
    document.getElementById('questionModal').classList.add('hidden');
    document.getElementById('questionModal').classList.remove('flex');
}
</script>

<?php
$content = ob_get_clean();
$title = "Оюнду редакторлоо - Своя Игра";
include __DIR__ . '/../../layout/main.php';
?>
