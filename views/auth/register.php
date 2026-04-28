<?php
use App\Auth;
use App\Repositories\UserRepository;
use App\Helpers;

$userRepo = new UserRepository();
$auth = new App\Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = Helpers::t('auth.csrf_error');
    } elseif ($password !== $confirm) {
        $_SESSION['error'] = Helpers::t('auth.register.passwords_mismatch');
    } else {
        $result = $auth->register($username, $email, $password);
        if ($result['success']) {
            $_SESSION['success'] = Helpers::t('auth.register.success');
            Helpers::redirect('/dashboard');
        } else {
            $_SESSION['error'] = $result['message'] ?? Helpers::t('auth.register.error');
        }
    }
}

ob_start();
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-md border-t-4 border-green-600">
    <h2 class="text-3xl font-bold mb-6 text-center text-gray-800"><?= Helpers::t('auth.register.heading') ?></h2>
    <form action="/register" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.register.username') ?></label>
            <input type="text" name="username" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                placeholder="user123">
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.register.email') ?></label>
            <input type="email" name="email" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                placeholder="example@mail.com">
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.register.password') ?></label>
            <input type="password" name="password" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                placeholder="********">
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.register.confirm_password') ?></label>
            <input type="password" name="confirm_password" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                placeholder="********">
        </div>

        <button type="submit"
            class="w-full bg-green-600 text-white font-bold py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
            <?= Helpers::t('auth.register.submit') ?>
        </button>
    </form>

    <p class="mt-6 text-center text-gray-600">
        <?= Helpers::t('auth.register.has_account') ?>
        <a href="/login" class="text-green-600 hover:underline"><?= Helpers::t('auth.register.login_link') ?></a>
    </p>
</div>

<?php
$content = ob_get_clean();
$title = Helpers::t('auth.register.page_title');
include __DIR__ . '/../layout/main.php';
?>
