<?php
use App\Repositories\GameRepository;

$gameRepo = new GameRepository();
$games = $gameRepo->findByUserId($_SESSION['user_id']);

ob_start();
?>

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Менин оюндарым</h1>
    <a href="/dashboard/games/create" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">
        + Жаңы оюн түзүү
    </a>
</div>

<?php if (empty($games)): ?>
    <div class="bg-white p-12 rounded-xl shadow-md text-center border-2 border-dashed border-gray-300">
        <h3 class="text-2xl font-bold text-gray-400 mb-4">Сизде азырынча оюн жок</h3>
        <p class="text-gray-500 mb-8">Биринчи оюнуңузду түзүп, досторуңуз менен ойноп баштаңыз!</p>
        <a href="/dashboard/games/create" class="bg-blue-100 text-blue-600 px-8 py-3 rounded-full font-bold hover:bg-blue-200 transition">
            Оюн түзүү
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($games as $game): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden border-b-4 border-blue-500 hover:shadow-lg transition">
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($game['title']) ?></h3>
                    <div class="flex justify-between text-sm text-gray-500 mb-4">
                        <span>Категориялар: <?= $game['categories_count'] ?></span>
                        <span>Суроолор: <?= $game['questions_count'] ?></span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="/game/start?game_id=<?= $game['id'] ?>" class="flex-1 bg-green-500 text-white text-center py-2 rounded hover:bg-green-600 transition font-bold">Ойнотуу</a>
                        <a href="/dashboard/games/edit?id=<?= $game['id'] ?>" class="flex-1 bg-gray-100 text-gray-700 text-center py-2 rounded hover:bg-gray-200 transition">Оңдоо</a>
                        <a href="/dashboard/games/delete?id=<?= $game['id'] ?>" class="p-2 text-red-500 hover:bg-red-50 rounded transition">
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

<?php
$content = ob_get_clean();
$title = "Dashboard - Своя Игра";
include __DIR__ . '/../layout/main.php';
?>
