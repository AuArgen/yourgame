<?php
use App\Repositories\SessionRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\LogRepository;
use App\Helpers;

$sessionRepo = new SessionRepository();
$questionRepo = new QuestionRepository();
$logRepo = new LogRepository();

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) {
    Helpers::redirect('/dashboard');
}

$session = $sessionRepo->findById($sessionId);
if (!$session || $session['host_id'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

if ($session['status'] === 'finished') {
    Helpers::redirect("/game/summary?session_id=" . $sessionId);
}

// Обработка действий (логирование ответов)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Helpers::verifyCsrfToken($csrfToken)) {
        $action = $_POST['action'] ?? '';
        $questionId = $_POST['question_id'] ?? null;
        $participantId = $_POST['participant_id'] ?? null;
        
        $q = $questionRepo->findById($questionId);
        $points = $q['points'] ?? 0;

        if ($action === 'correct' && $participantId) {
            $logRepo->logAction($sessionId, $questionId, $participantId, 'answered_correct', $points);
            $sessionRepo->updateParticipantScore($participantId, $points);
        } elseif ($action === 'wrong' && $participantId) {
            $logRepo->logAction($sessionId, $questionId, $participantId, 'answered_wrong', -$points);
            $sessionRepo->updateParticipantScore($participantId, -$points);
        } elseif ($action === 'skip') {
            $logRepo->logAction($sessionId, $questionId, null, 'skipped', 0);
        } elseif ($action === 'finish') {
            $sessionRepo->updateStatus($sessionId, 'finished');
            Helpers::redirect("/game/summary?session_id=" . $sessionId);
        }
    }
    Helpers::redirect("/game/play?session_id=" . $sessionId);
}

$participants = $sessionRepo->getParticipants($sessionId);
$fullData = $questionRepo->getFullGameData($session['game_id']);
$answeredQuestionIds = $logRepo->getAnsweredQuestions($sessionId);

// Группировка по категориям
$categories = [];
foreach ($fullData as $row) {
    $catId = $row['category_id'];
    if (!isset($categories[$catId])) {
        $categories[$catId] = [
            'name' => $row['category_name'],
            'questions' => []
        ];
    }
    if ($row['question_id']) {
        $row['is_answered'] = in_array($row['question_id'], $answeredQuestionIds);
        $categories[$catId]['questions'][] = $row;
    }
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?= Helpers::e($session['game_title']) ?></h1>
        <form action="/game/play?session_id=<?= $sessionId ?>" method="POST" onsubmit="return confirm('Оюнду аяктагыңыз келеби?')">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <input type="hidden" name="action" value="finish">
            <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 font-bold transition">Оюнду аяктоо</button>
        </form>
    </div>

    <!-- Игровая сетка -->
    <div class="bg-blue-900 p-4 rounded-xl shadow-2xl overflow-x-auto">
        <table class="w-full border-collapse">
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr class="border-b border-blue-800">
                        <td class="p-4 text-white font-bold text-lg bg-blue-800 w-1/4 text-center border-r border-blue-700">
                            <?= Helpers::e($cat['name']) ?>
                        </td>
                        <?php 
                        $pointsList = [100, 200, 300, 400, 500];
                        $qByPoints = [];
                        foreach ($cat['questions'] as $q) $qByPoints[$q['points']] = $q;

                        foreach ($pointsList as $p): 
                            $q = $qByPoints[$p] ?? null;
                        ?>
                            <td class="p-0 border-r border-blue-700 last:border-r-0 text-center w-[15%]">
                                <?php if ($q): ?>
                                    <?php if ($q['is_answered']): ?>
                                        <div class="py-6 bg-blue-950 text-blue-800 font-bold text-3xl select-none">
                                            <?= $p ?>
                                        </div>
                                    <?php else: ?>
                                        <button onclick='openQuestion(<?= json_encode($q) ?>)' 
                                            class="w-full py-6 bg-blue-900 hover:bg-blue-700 text-yellow-400 font-bold text-3xl transition duration-200">
                                            <?= $p ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Табло игроков -->
    <div class="mt-8 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($participants as $p): ?>
            <div class="bg-white p-4 rounded-lg shadow border-b-4 border-blue-600 text-center">
                <div class="text-gray-500 text-sm font-medium mb-1"><?= Helpers::e($p['name']) ?></div>
                <div class="text-2xl font-bold text-blue-800"><?= $p['score'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Модальное окно вопроса -->
<div id="qModal" class="fixed inset-0 bg-blue-950 bg-opacity-95 hidden flex-col items-center justify-center z-50 p-4">
    <div class="max-w-4xl w-full text-center">
        <!-- Таймер -->
        <div id="timerDisplay" class="text-6xl font-black text-white mb-8">30</div>
        
        <!-- Контент суроо -->
        <div id="qContent" class="mb-12">
            <h2 id="qText" class="text-4xl md:text-5xl font-bold text-white leading-tight mb-8"></h2>
            <div id="qMedia" class="flex justify-center mb-8"></div>
        </div>

        <div id="qControls" class="space-y-8">
            <button onclick="showAnswer()" id="showBtn" class="bg-yellow-500 text-blue-900 px-12 py-4 rounded-full text-2xl font-black hover:bg-yellow-400 transition shadow-xl">ЖООПТУ КӨРҮҮ</button>
            
            <div id="answerSection" class="hidden animate-bounce-in">
                <div id="aText" class="text-5xl font-black text-green-400 mb-12"></div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-5xl mx-auto">
                    <?php foreach ($participants as $p): ?>
                        <div class="bg-blue-800 p-4 rounded-xl flex items-center justify-between gap-2">
                            <span class="text-white font-bold truncate"><?= Helpers::e($p['name']) ?></span>
                            <div class="flex gap-1">
                                <button onclick="submitAction('correct', <?= $p['id'] ?>)" class="bg-green-500 p-2 rounded text-white font-bold hover:bg-green-600">+<?= $p['score'] >= 0 ? '' : '' ?></button>
                                <button onclick="submitAction('wrong', <?= $p['id'] ?>)" class="bg-red-500 p-2 rounded text-white font-bold hover:bg-red-600">-</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-8">
                    <button onclick="submitAction('skip')" class="text-gray-400 hover:text-white underline">Өткөрүп жиберүү</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Форма для отправки результатов (скрытая) -->
<form id="actionForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="question_id" id="formQId">
    <input type="hidden" name="participant_id" id="formPId">
</form>

<script>
let currentQuestion = null;
let timerInterval = null;

function openQuestion(q) {
    currentQuestion = q;
    document.getElementById('qText').innerText = q.question_text;
    document.getElementById('aText').innerText = q.answer_text;
    
    const media = document.getElementById('qMedia');
    media.innerHTML = '';
    if (q.image_url) {
        media.innerHTML = `<img src="/storage/images/${q.image_url}" class="max-h-80 rounded-lg shadow-lg">`;
    } else if (q.video_url) {
        media.innerHTML = `<iframe width="560" height="315" src="https://www.youtube.com/embed/${q.video_url}?autoplay=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
    }

    document.getElementById('qModal').classList.remove('hidden');
    document.getElementById('qModal').classList.add('flex');
    document.getElementById('showBtn').classList.remove('hidden');
    document.getElementById('answerSection').classList.add('hidden');
    
    startTimer(q.time_limit);
}

function startTimer(seconds) {
    let timeLeft = seconds;
    const display = document.getElementById('timerDisplay');
    display.innerText = timeLeft;
    display.classList.remove('text-red-500');

    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        display.innerText = timeLeft;
        if (timeLeft <= 5) display.classList.add('text-red-500');
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            // Можно добавить звук сирены
        }
    }, 1000);
}

function showAnswer() {
    clearInterval(timerInterval);
    document.getElementById('showBtn').classList.add('hidden');
    document.getElementById('answerSection').classList.remove('hidden');
}

function submitAction(action, pId = null) {
    document.getElementById('formAction').value = action;
    document.getElementById('formQId').value = currentQuestion.question_id;
    document.getElementById('formPId').value = pId;
    document.getElementById('actionForm').submit();
}
</script>

<style>
@keyframes bounce-in {
    0% { transform: scale(0.9); opacity: 0; }
    70% { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-bounce-in { animation: bounce-in 0.4s ease-out forwards; }
</style>

<?php
$content = ob_get_clean();
$title = "Оюн - Своя Игра";
include __DIR__ . '/../layout/main.php';
?>
