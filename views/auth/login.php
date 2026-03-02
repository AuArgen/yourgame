<?php
use App\Auth;
use App\Repositories\UserRepository;
use App\Helpers;

$userRepo = new UserRepository();
$auth = new Auth($userRepo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = "CSRF катасы.";
    } else {
        if ($auth->login($email, $password)) {
            Helpers::redirect('/dashboard');
        } else {
            $_SESSION['error'] = "Email же пароль ката.";
        }
    }
}

ob_start();
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-md border-t-4 border-blue-600">
    <h2 class="text-3xl font-bold mb-6 text-center text-gray-800">Кирүү</h2>
    <form action="/login" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
        
        <div>
            <label class="block text-gray-700 font-medium mb-2">Email</label>
            <input type="email" name="email" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Мисалы: example@mail.com">
        </div>
        
        <div>
            <label class="block text-gray-700 font-medium mb-2">Пароль</label>
            <input type="password" name="password" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="********">
        </div>

        <button type="submit" 
            class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg">
            Кирүү
        </button>
    </form>
    
    <p class="mt-6 text-center text-gray-600">
        Аккаунтуңуз жокпу? <a href="/register" class="text-blue-600 hover:underline">Катталуу</a>
    </p>
</div>

<?php
$content = ob_get_clean();
$title = "Кирүү - Своя Игра";
include __DIR__ . '/../layout/main.php';
?>
