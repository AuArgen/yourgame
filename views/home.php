<?php
ob_start();
?>

<div class="flex flex-col items-center justify-center mt-20">
    <h1 class="text-5xl font-extrabold text-blue-700 mb-6">Своя Игра</h1>
    <p class="text-xl text-gray-600 mb-10 text-center max-w-2xl">
        Интеллектуалдык шоу оюнун өзүңүз түзүп, досторуңуз менен ойноңуз. 
        Сүрөттөр, YouTube видеолору жана "Мышык чыгуу" суроолору менен оюнду кызыктуу кылыңыз.
    </p>
    
    <div class="space-x-4">
        <a href="/register" class="bg-blue-600 text-white px-8 py-3 rounded-full text-lg font-bold hover:bg-blue-700 shadow-lg transition">Оюнду баштоо</a>
        <a href="/login" class="bg-white text-blue-600 border border-blue-600 px-8 py-3 rounded-full text-lg font-bold hover:bg-gray-100 transition">Кирүү</a>
    </div>

    <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-blue-500">
            <h3 class="text-xl font-bold mb-3 text-blue-600">Конструктор</h3>
            <p class="text-gray-600">Оюндун темаларын жана суроолорун оңой кошуңуз. Ар бир суроого упай бериңиз.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-green-500">
            <h3 class="text-xl font-bold mb-3 text-green-600">Медиа колдоо</h3>
            <p class="text-gray-600">Суроолорго сүрөт же YouTube видеолорун кошуп, оюнду визуалдуу кылыңыз.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-yellow-500">
            <h3 class="text-xl font-bold mb-3 text-yellow-600">Оюн Мотору</h3>
            <p class="text-gray-600">Оюнчулардын упайларын автоматтык эсептеп, оюндун жүрүшүн көзөмөлдөңүз.</p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Башкы бет - Своя Игра";
include __DIR__ . '/layout/main.php';
?>
