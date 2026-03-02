<!DOCTYPE html>
<html lang="ky">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Своя Игра' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <!-- Navbar -->
    <nav class="bg-blue-600 p-4 text-white shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/" class="text-2xl font-bold tracking-wider">Своя Игра</a>
            <div class="space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/dashboard" class="hover:underline">Менин оюндарым</a>
                    <a href="/dashboard/history" class="hover:underline">Тарых</a>
                    <a href="/logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded transition">Чыгуу</a>
                <?php else: ?>
                    <a href="/login" class="hover:underline">Кирүү</a>
                    <a href="/register" class="bg-white text-blue-600 px-4 py-2 rounded font-bold hover:bg-gray-100 transition">Катталуу</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
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
        &copy; <?= date('Y') ?> Своя Игра Проекти. Бардык укуктар корголгон.
    </footer>

</body>
</html>
