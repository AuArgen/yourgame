<?php
use App\Repositories\SessionRepository;
use App\Repositories\LogRepository;
use App\Helpers;

$sessionRepo = new SessionRepository();
$logRepo = new LogRepository();

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) {
    Helpers::redirect('/dashboard');
}

$session = $sessionRepo->findById($sessionId);
if (!$session || $session['host_id'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

$summary = $sessionRepo->getSessionSummary($sessionId);
$logs = $logRepo->getLogsBySession($sessionId);

// Сортировка участников по баллам
usort($summary['participants'], function($a, $b) {
    return $b['score'] <=> $a['score'];
});

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-black text-gray-800 mb-2">ОЮН АЯКТАДЫ!</h1>
        <p class="text-xl text-gray-600"><?= Helpers::e($session['game_title']) ?></p>
    </div>

    <!-- Победители -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <?php foreach ($summary['participants'] as $index => $p): ?>
            <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-b-8 <?= $index === 0 ? 'border-yellow-400 scale-110 z-10' : 'border-blue-400' ?>">
                <?php if ($index === 0): ?>
                    <div class="text-5xl mb-4">👑</div>
                <?php endif; ?>
                <div class="text-2xl font-bold text-gray-800 mb-2"><?= Helpers::e($p['name']) ?></div>
                <div class="text-4xl font-black text-blue-600"><?= $p['score'] ?></div>
                <div class="text-gray-400 text-sm mt-2 font-bold uppercase"><?= ($index + 1) ?>-орун</div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Статистика -->
    <div class="bg-white rounded-xl shadow-md p-8 mb-8">
        <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-4">Жалпы статистика</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <?php 
            $stats = [];
            foreach ($summary['stats'] as $s) $stats[$s['action']] = $s['count'];
            ?>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-500"><?= $stats['answered_correct'] ?? 0 ?></div>
                <div class="text-gray-500 text-sm">Туура жооптор</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-red-500"><?= $stats['answered_wrong'] ?? 0 ?></div>
                <div class="text-gray-500 text-sm">Ката жооптор</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-400"><?= $stats['skipped'] ?? 0 ?></div>
                <div class="text-gray-500 text-sm">Өткөрүлгөн</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-500"><?= count($logs) ?></div>
                <div class="text-gray-500 text-sm">Жалпы суроолор</div>
            </div>
        </div>
    </div>

    <div class="flex justify-center gap-4">
        <a href="/dashboard" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
            Dashboard'го кайтуу
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Оюн жыйынтыгы - Своя Игра";
include __DIR__ . '/../layout/main.php';
?>
