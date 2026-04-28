<?php
use App\Helpers;

ob_start();
?>

<div class="flex flex-col items-center justify-center mt-20">
    <h1 class="text-5xl font-extrabold text-blue-700 mb-6"><?= Helpers::t('home.heading') ?></h1>
    <p class="text-xl text-gray-600 mb-10 text-center max-w-2xl">
        <?= Helpers::t('home.subtitle') ?>
    </p>

    <div class="space-x-4">
        <a href="/register" class="bg-blue-600 text-white px-8 py-3 rounded-full text-lg font-bold hover:bg-blue-700 shadow-lg transition"><?= Helpers::t('home.start_btn') ?></a>
        <a href="/login" class="bg-white text-blue-600 border border-blue-600 px-8 py-3 rounded-full text-lg font-bold hover:bg-gray-100 transition"><?= Helpers::t('home.login_btn') ?></a>
    </div>

    <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-blue-500">
            <h3 class="text-xl font-bold mb-3 text-blue-600"><?= Helpers::t('home.feature1_title') ?></h3>
            <p class="text-gray-600"><?= Helpers::t('home.feature1_desc') ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-green-500">
            <h3 class="text-xl font-bold mb-3 text-green-600"><?= Helpers::t('home.feature2_title') ?></h3>
            <p class="text-gray-600"><?= Helpers::t('home.feature2_desc') ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-yellow-500">
            <h3 class="text-xl font-bold mb-3 text-yellow-600"><?= Helpers::t('home.feature3_title') ?></h3>
            <p class="text-gray-600"><?= Helpers::t('home.feature3_desc') ?></p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = Helpers::t('home.page_title');
include __DIR__ . '/layout/main.php';
?>
