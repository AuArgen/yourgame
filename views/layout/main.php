<?php
use App\Helpers;
use App\Lang;

$currentLocale = Lang::getLocale();
$switchLocale  = $currentLocale === 'ru' ? 'ky' : 'ru';
$switchLabel   = Helpers::t('nav.lang_label');
$currentPath   = strtok($_SERVER['REQUEST_URI'], '?');
$switchParams  = array_merge($_GET ?? [], ['setlang' => $switchLocale]);
$switchUrl     = $currentPath . 'vj?' . http_build_query($switchParams);
?>
<!DOCTYPE html>
<html lang="<?= $currentLocale ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Своя Игра' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <nav class="bg-blue-600 p-4 text-white shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/" class="text-2xl font-bold tracking-wider"><?= Helpers::t('nav.brand') ?></a>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/dashboard" class="hover:underline"><?= Helpers::t('nav.my_games') ?></a>
                    <a href="/dashboard/history" class="hover:underline"><?= Helpers::t('nav.history') ?></a>
                    <a href="/logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded transition"><?= Helpers::t('nav.logout') ?></a>
                <?php else: ?>
                    <a href="/login" class="hover:underline"><?= Helpers::t('nav.login') ?></a>
                    <a href="/register" class="bg-white text-blue-600 px-4 py-2 rounded font-bold hover:bg-gray-100 transition"><?= Helpers::t('nav.register') ?></a>
                <?php endif; ?>
                <a href="<?= Helpers::e($switchUrl) ?>"
                   class="border border-white text-white text-xs px-2 py-1 rounded hover:bg-white hover:text-blue-600 transition font-bold">
                    <?= $switchLabel ?>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto mt-8 px-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="container mx-auto text-center mt-12 py-6 text-gray-500 text-sm">
        <p>&copy; <?= date('Y') ?> <?= Helpers::t('footer.text') ?></p>
        <p class="mt-1"><?= Helpers::t('footer.sponsor') ?></p>
    </footer>

</body>
</html>
