<?php
use App\Helpers;
use App\Lang;

$currentLocale = Lang::getLocale();
?>
<!DOCTYPE html>
<html lang="<?= $currentLocale ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::t('errors.404.page_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans flex flex-col min-h-screen">
    <nav class="bg-blue-600 p-4 text-white shadow-lg">
        <div class="container mx-auto">
            <a href="/" class="text-2xl font-bold tracking-wider"><?= Helpers::t('nav.brand') ?></a>
        </div>
    </nav>
    <main class="flex-grow flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-8xl font-bold text-blue-600">404</h1>
            <p class="text-2xl text-gray-700 mt-4"><?= Helpers::t('errors.404.message') ?></p>
            <a href="/" class="mt-6 inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <?= Helpers::t('errors.404.back') ?>
            </a>
        </div>
    </main>
</body>
</html>
