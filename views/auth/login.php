<?php
use App\Auth;
use App\Repositories\UserRepository;
use App\Helpers;

$userRepo = new UserRepository();
$auth = new Auth($userRepo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = Helpers::t('auth.csrf_error');
    } else {
        $result = $auth->login($email, $password);
        if ($result['success']) {
            Helpers::redirect('/dashboard');
        } else {
            $_SESSION['error'] = $result['message'];
        }
    }
}

ob_start();
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-md border-t-4 border-blue-600">
    <h2 class="text-3xl font-bold mb-6 text-center text-gray-800"><?= Helpers::t('auth.login.heading') ?></h2>
    <form action="/login" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.login.email') ?></label>
            <input type="email" name="email" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="<?= Helpers::e(Helpers::t('auth.login.email_placeholder')) ?>">
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-2"><?= Helpers::t('auth.login.password') ?></label>
            <input type="password" name="password" required
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="********">
        </div>

        <button type="submit"
            class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg">
            <?= Helpers::t('auth.login.submit') ?>
        </button>
    </form>

    <p class="mt-6 text-center text-gray-600">
        <?= Helpers::t('auth.login.no_account') ?>
        <a href="/register" class="text-blue-600 hover:underline"><?= Helpers::t('auth.login.register_link') ?></a>
    </p>
</div>

<?php
$content = ob_get_clean();
$title = Helpers::t('auth.login.page_title');
include __DIR__ . '/../layout/main.php';
?>
