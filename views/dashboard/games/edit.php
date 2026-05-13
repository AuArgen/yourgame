<?php
use App\Repositories\GameRepository;
use App\Repositories\RoundRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\QuestionRepository;
use App\Helpers;

$gameRepo     = new GameRepository();
$roundRepo    = new RoundRepository();
$catRepo      = new CategoryRepository();
$questionRepo = new QuestionRepository();

$specialQuestionTypes = [
    'cat_choose_player' => Helpers::t('game.edit.special_types.cat_choose_player'),
    'cat_gift'          => Helpers::t('game.edit.special_types.cat_gift'),
    'cat_penalty'       => Helpers::t('game.edit.special_types.cat_penalty'),
];

$gameId = $_GET['id'] ?? null;
if (!$gameId) {
    Helpers::redirect('/dashboard');
}

$game = $gameRepo->findById($gameId);
if (!$game || $game['created_by'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

$sanitizeAnchor = static function ($anchor) {
    $anchor = ltrim((string) $anchor, '#');
    return preg_match('/^[A-Za-z0-9_-]+$/', $anchor) ? $anchor : '';
};

$buildEditUrl = static function ($gameId, $anchor = '') {
    $url = "/dashboard/games/edit?id=" . urlencode((string) $gameId);
    if ($anchor !== '') {
        $url .= '#' . $anchor;
    }
    return $url;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $redirectAnchor = $sanitizeAnchor($_POST['redirect_anchor'] ?? '');

    if (!Helpers::verifyCsrfToken($csrfToken)) {
        $_SESSION['error'] = Helpers::t('game.edit.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_round') {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $rounds = $roundRepo->findByGameId($gameId);
                $newRoundId = $roundRepo->create($gameId, $name, count($rounds));
                $redirectAnchor = "round-$newRoundId";
                $_SESSION['success'] = Helpers::t('game.edit.round_added');
            }

        } elseif ($action === 'delete_round') {
            $roundId = $_POST['round_id'] ?? null;
            $round = $roundRepo->findById($roundId);
            if ($round) {
                $roundRepo->delete($roundId);
                $redirectAnchor = 'rounds-list';
                $_SESSION['success'] = Helpers::t('game.edit.round_deleted');
            }

        } elseif ($action === 'add_category') {
            $roundId = $_POST['round_id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            if ($roundId && !empty($name)) {
                $newCategoryId = $catRepo->create($roundId, $name);
                $redirectAnchor = "category-$newCategoryId";
                $_SESSION['success'] = Helpers::t('game.edit.category_added');
            }

        } elseif ($action === 'delete_category') {
            $catId = $_POST['category_id'] ?? null;
            $category = $catRepo->findById($catId);
            $catRepo->delete($catId);
            if ($category) {
                $redirectAnchor = "round-{$category['round_id']}";
            }
            $_SESSION['success'] = Helpers::t('game.edit.category_deleted');

        } elseif ($action === 'add_question') {
            $catId    = $_POST['category_id'] ?? null;
            $points   = $_POST['points'] ?? 100;
            $text     = trim($_POST['question_text'] ?? '');
            $answer   = trim($_POST['answer_text'] ?? '');
            $timeLimit = $_POST['time_limit'] ?? 30;
            $specialType = 'normal';
            if (isset($_POST['is_special_question'])) {
                $specialType = trim((string) ($_POST['special_type'] ?? 'cat_choose_player'));
                if (!array_key_exists($specialType, $specialQuestionTypes)) {
                    $specialType = 'cat_choose_player';
                }
            }
            $isCat = $specialType === 'cat_choose_player';
            $youtube  = trim($_POST['video_url'] ?? '');
            $youtubeId = $youtube ? Helpers::extractYoutubeId($youtube) : null;

            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = Helpers::uploadImage($_FILES['image']);
            }
            $answerImage = null;
            if (isset($_FILES['answer_image']) && $_FILES['answer_image']['error'] === UPLOAD_ERR_OK) {
                $answerImage = Helpers::uploadImage($_FILES['answer_image']);
            }

            if ($catId && !empty($text) && !empty($answer)) {
                $questionRepo->create([
                    'category_id'      => $catId,
                    'question_text'    => $text,
                    'answer_text'      => $answer,
                    'points'           => $points,
                    'time_limit'       => $timeLimit,
                    'is_cat_in_bag'    => $isCat ? 'true' : 'false',
                    'special_type'     => $specialType,
                    'video_url'        => $youtubeId,
                    'image_url'        => $image,
                    'answer_image_url' => $answerImage,
                ]);
                $redirectAnchor = "category-$catId";
                $_SESSION['success'] = Helpers::t('game.edit.question_added');
            }

        } elseif ($action === 'edit_question') {
            $qId      = $_POST['question_id'] ?? null;
            $text     = trim($_POST['question_text'] ?? '');
            $answer   = trim($_POST['answer_text'] ?? '');
            $timeLimit = (int) ($_POST['time_limit'] ?? 30);
            $specialType = 'normal';
            if (isset($_POST['is_special_question'])) {
                $specialType = trim((string) ($_POST['special_type'] ?? 'cat_choose_player'));
                if (!array_key_exists($specialType, $specialQuestionTypes)) {
                    $specialType = 'cat_choose_player';
                }
            }
            $isCat = $specialType === 'cat_choose_player';
            $youtube = trim($_POST['video_url'] ?? '');

            $question = $questionRepo->findById($qId);
            if ($question && !empty($text) && !empty($answer)) {
                $updateData = [
                    'question_text' => $text,
                    'answer_text'   => $answer,
                    'time_limit'    => $timeLimit,
                    'is_cat_in_bag' => $isCat ? 'true' : 'false',
                    'special_type'  => $specialType,
                ];
                if ($youtube !== '') {
                    $updateData['video_url'] = Helpers::extractYoutubeId($youtube);
                }
                if (isset($_POST['clear_image'])) {
                    $updateData['image_url'] = null;
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploaded = Helpers::uploadImage($_FILES['image']);
                    if ($uploaded) {
                        $updateData['image_url'] = $uploaded;
                    }
                }
                if (isset($_POST['clear_answer_image'])) {
                    $updateData['answer_image_url'] = null;
                } elseif (isset($_FILES['answer_image']) && $_FILES['answer_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadedAnswer = Helpers::uploadImage($_FILES['answer_image']);
                    if ($uploadedAnswer) {
                        $updateData['answer_image_url'] = $uploadedAnswer;
                    }
                }
                try {
                    $questionRepo->update($qId, $updateData);
                    $_SESSION['success'] = Helpers::t('game.edit.question_updated');
                } catch (\Exception $e) {
                    $_SESSION['error'] = Helpers::t('game.edit.question_save_error');
                    error_log("[edit_question] update() failed for qId=$qId: " . $e->getMessage() . " data=" . json_encode($updateData));
                }
                $redirectAnchor = "category-{$question['category_id']}";
            }

        } elseif ($action === 'delete_question') {
            $qId = $_POST['question_id'] ?? null;
            $question = $questionRepo->findById($qId);
            $questionRepo->delete($qId);
            if ($question) {
                $redirectAnchor = "category-{$question['category_id']}";
            }
            $_SESSION['success'] = Helpers::t('game.edit.question_deleted');
        }
    }
    Helpers::redirect($buildEditUrl($gameId, $redirectAnchor));
}

// Build rounds → categories → questions structure
$rounds   = $roundRepo->findByGameId($gameId);
$fullData = $questionRepo->getFullGameData($gameId);

$roundsData = [];
foreach ($rounds as $round) {
    $roundsData[$round['id']] = ['name' => $round['name'], 'categories' => []];
}
foreach ($fullData as $row) {
    $rid = $row['round_id'];
    $cid = $row['category_id'];
    if (!isset($roundsData[$rid]['categories'][$cid])) {
        $roundsData[$rid]['categories'][$cid] = ['name' => $row['category_name'], 'questions' => []];
    }
    if ($row['question_id']) {
        $roundsData[$rid]['categories'][$cid]['questions'][] = $row;
    }
}

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= Helpers::e($game['title']) ?></h1>
            <p class="text-gray-600"><?= Helpers::t('game.edit.subtitle') ?></p>
        </div>
        <a href="/dashboard" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition"><?= Helpers::t('game.edit.back') ?></a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Add round -->
    <div class="bg-white p-6 rounded-xl shadow-md mb-8 border-t-4 border-indigo-500">
        <h2 class="text-xl font-bold mb-4 text-gray-700"><?= Helpers::t('game.edit.add_round_heading') ?></h2>
        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" class="flex gap-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add_round">
            <input type="text" name="name" required placeholder="<?= Helpers::e(Helpers::t('game.edit.round_name_placeholder')) ?>"
                class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition font-bold">
                <?= Helpers::t('game.edit.add_round_btn') ?>
            </button>
        </form>
    </div>

    <!-- Rounds list -->
    <?php if (empty($rounds)): ?>
        <div class="bg-yellow-50 border-2 border-dashed border-yellow-300 rounded-xl p-12 text-center text-yellow-700 font-bold text-lg">
            <?= Helpers::t('game.edit.no_rounds') ?>
        </div>
    <?php endif; ?>

    <div id="rounds-list" class="space-y-10 scroll-mt-6">
        <?php foreach ($roundsData as $roundId => $round): ?>
            <div id="round-<?= $roundId ?>" class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 scroll-mt-6">

                <!-- Round header -->
                <div class="bg-indigo-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-black text-white tracking-wide uppercase">
                        <?= Helpers::e($round['name']) ?>
                    </h2>
                    <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST"
                          onsubmit="return confirm('<?= addslashes(Helpers::t('game.edit.delete_round_confirm')) ?>')">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete_round">
                        <input type="hidden" name="round_id" value="<?= $roundId ?>">
                        <input type="hidden" name="redirect_anchor" value="rounds-list">
                        <button type="submit" class="text-indigo-200 hover:text-white text-sm underline">
                            <?= Helpers::t('game.edit.delete_round_btn') ?>
                        </button>
                    </form>
                </div>

                <div class="p-6">
                    <!-- Add category to this round -->
                    <div class="mb-6 bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-bold text-gray-600 mb-3"><?= Helpers::t('game.edit.add_category_heading') ?></h3>
                        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" class="flex gap-3">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="add_category">
                            <input type="hidden" name="round_id" value="<?= $roundId ?>">
                            <input type="hidden" name="redirect_anchor" value="round-<?= $roundId ?>">
                            <input type="text" name="name" required placeholder="<?= Helpers::e(Helpers::t('game.edit.category_name_placeholder')) ?>"
                                class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition font-bold text-sm">
                                <?= Helpers::t('game.edit.add_category_btn') ?>
                            </button>
                        </form>
                    </div>

                    <!-- Categories -->
                    <?php if (empty($round['categories'])): ?>
                        <p class="text-gray-400 text-sm text-center py-4"><?= Helpers::t('game.edit.no_categories') ?></p>
                    <?php endif; ?>

                    <div class="space-y-6">
                        <?php foreach ($round['categories'] as $catId => $cat): ?>
                            <div id="category-<?= $catId ?>" class="border border-gray-200 rounded-lg overflow-hidden scroll-mt-6">
                                <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800"><?= Helpers::e($cat['name']) ?></h3>
                                    <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST"
                                          onsubmit="return confirm('<?= addslashes(Helpers::t('game.edit.delete_category_confirm')) ?>')">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $catId ?>">
                                        <input type="hidden" name="redirect_anchor" value="round-<?= $roundId ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs"><?= Helpers::t('game.edit.delete_category_btn') ?></button>
                                    </form>
                                </div>

                                <div class="p-4">
                                    <div class="grid grid-cols-5 gap-3">
                                        <?php
                                        $pointsList = [100, 200, 300, 400, 500];
                                        $qByPoints = [];
                                        foreach ($cat['questions'] as $q) {
                                            $q['special_type'] = $q['special_type'] ?? 'normal';
                                            $qByPoints[$q['points']] = $q;
                                        }
                                        foreach ($pointsList as $p):
                                            $q = $qByPoints[$p] ?? null;
                                        ?>
                                            <div class="border rounded-lg p-3 text-center <?= $q ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-dashed border-gray-300' ?>">
                                                <div class="font-bold text-base mb-1 <?= $q ? 'text-blue-700' : 'text-gray-400' ?>"><?= $p ?></div>
                                                <?php if ($q): ?>
                                                    <div class="text-xs text-gray-600 truncate mb-1"><?= Helpers::e($q['question_text']) ?></div>
                                                    <?php if (!empty($q['image_url']) || !empty($q['answer_image_url'])): ?>
                                                        <div class="flex justify-center gap-1 mb-1">
                                                            <?php if (!empty($q['image_url'])): ?>
                                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded" title="Есть фото вопроса">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>Q
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($q['answer_image_url'])): ?>
                                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold bg-green-100 text-green-600 px-1.5 py-0.5 rounded" title="Есть фото ответа">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>A
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (($q['special_type'] ?? 'normal') !== 'normal'): ?>
                                                        <div class="text-[10px] font-bold uppercase tracking-wide text-orange-500 mb-2">
                                                            <?= Helpers::e($specialQuestionTypes[$q['special_type']] ?? $q['special_type']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex justify-center gap-3 mt-1">
                                                        <button type="button"
                                                            onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                                                'id'               => (int) $q['question_id'],
                                                                'question_text'    => $q['question_text'],
                                                                'answer_text'      => $q['answer_text'],
                                                                'time_limit'       => (int) ($q['time_limit'] ?? 30),
                                                                'special_type'     => $q['special_type'] ?? 'normal',
                                                                'image_url'        => $q['image_url'] ?? null,
                                                                'answer_image_url' => $q['answer_image_url'] ?? null,
                                                                'video_url'        => $q['video_url'] ?? null,
                                                                'catId'            => $catId,
                                                                'points'           => $p,
                                                            ]), ENT_QUOTES) ?>)"
                                                            class="text-blue-500 hover:underline text-xs"><?= Helpers::t('game.edit.edit_question_btn') ?></button>
                                                        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST"
                                                              onsubmit="return confirm('<?= addslashes(Helpers::t('game.edit.delete_question_confirm')) ?>')">
                                                            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                                                            <input type="hidden" name="action" value="delete_question">
                                                            <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                                            <input type="hidden" name="redirect_anchor" value="category-<?= $catId ?>">
                                                            <button type="submit" class="text-red-500 hover:underline text-xs"><?= Helpers::t('game.edit.delete_question_btn') ?></button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <button onclick="openQuestionModal(<?= $catId ?>, <?= $p ?>)"
                                                        class="text-blue-500 hover:underline text-sm font-medium"><?= Helpers::t('game.edit.add_question_btn') ?></button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Question modal -->
<div id="questionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-2xl font-bold text-gray-800"><span id="modalTitleText"><?= Helpers::t('game.edit.modal_add_title') ?></span> (<span id="modalPoints"></span> <?= Helpers::t('game.edit.points_suffix') ?>)</h3>
            <button onclick="closeQuestionModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form action="/dashboard/games/edit?id=<?= $gameId ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
            <input type="hidden" name="action" id="modalFormAction" value="add_question">
            <input type="hidden" name="question_id" id="modalQuestionId" value="">
            <input type="hidden" name="category_id" id="modalCatId">
            <input type="hidden" name="points" id="modalPointsInput">
            <input type="hidden" name="redirect_anchor" id="modalRedirectAnchor">

            <!-- Question text + question image -->
            <div>
                <label class="block text-gray-700 font-bold mb-1"><?= Helpers::t('game.edit.question_text_label') ?></label>
                <textarea name="question_text" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
            </div>

            <div class="border rounded-lg p-3 bg-gray-50">
                <label class="block text-sm font-bold text-gray-600 mb-2"><?= Helpers::t('game.edit.image_upload_label') ?></label>

                <div id="modalCurrentImage" class="hidden mb-3 border rounded-lg p-3 bg-white">
                    <p class="text-xs font-bold text-gray-500 mb-2"><?= Helpers::t('game.edit.current_image_label') ?></p>
                    <div class="flex justify-center mb-2">
                        <img id="modalCurrentImagePreview" src="" alt=""
                             class="max-h-48 rounded shadow object-contain"
                             onerror="this.style.display='none';document.getElementById('modalImgBroken').classList.remove('hidden')">
                    </div>
                    <p id="modalImgBroken" class="hidden text-xs text-red-400 text-center mb-2"><?= Helpers::t('game.edit.image_broken') ?></p>
                    <label class="flex items-center gap-2 cursor-pointer text-red-500 text-sm">
                        <input type="checkbox" name="clear_image" id="modalClearImage" class="w-4 h-4">
                        <span><?= Helpers::t('game.edit.clear_image') ?></span>
                    </label>
                </div>

                <div id="newImagePreviewContainer" class="hidden mb-3 border border-blue-200 rounded-lg p-3 bg-blue-50">
                    <div class="flex justify-center">
                        <img id="newImagePreview" src="" alt="" class="max-h-48 rounded shadow object-contain">
                    </div>
                </div>

                <input type="file" name="image" accept="image/*" class="text-sm w-full">
            </div>

            <!-- Answer text + answer image -->
            <div>
                <label class="block text-gray-700 font-bold mb-1"><?= Helpers::t('game.edit.answer_text_label') ?></label>
                <input type="text" name="answer_text" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="border rounded-lg p-3 bg-green-50">
                <label class="block text-sm font-bold text-gray-600 mb-2"><?= Helpers::t('game.edit.answer_image_upload_label') ?></label>

                <div id="modalCurrentAnswerImage" class="hidden mb-3 border rounded-lg p-3 bg-white">
                    <p class="text-xs font-bold text-gray-500 mb-2"><?= Helpers::t('game.edit.current_answer_image_label') ?></p>
                    <div class="flex justify-center mb-2">
                        <img id="modalCurrentAnswerImagePreview" src="" alt=""
                             class="max-h-48 rounded shadow object-contain"
                             onerror="this.style.display='none';document.getElementById('modalAnswerImgBroken').classList.remove('hidden')">
                    </div>
                    <p id="modalAnswerImgBroken" class="hidden text-xs text-red-400 text-center mb-2"><?= Helpers::t('game.edit.answer_image_broken') ?></p>
                    <label class="flex items-center gap-2 cursor-pointer text-red-500 text-sm">
                        <input type="checkbox" name="clear_answer_image" id="modalClearAnswerImage" class="w-4 h-4">
                        <span><?= Helpers::t('game.edit.clear_answer_image') ?></span>
                    </label>
                </div>

                <div id="newAnswerImagePreviewContainer" class="hidden mb-3 border border-green-200 rounded-lg p-3 bg-white">
                    <div class="flex justify-center">
                        <img id="newAnswerImagePreview" src="" alt="" class="max-h-48 rounded shadow object-contain">
                    </div>
                </div>

                <input type="file" name="answer_image" accept="image/*" class="text-sm w-full" id="modalAnswerImageInput">
            </div>

            <!-- Timer, special, youtube -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-1"><?= Helpers::t('game.edit.timer_label') ?></label>
                    <input type="number" name="time_limit" id="modalTimeLimit" value="30" min="5" max="300"
                        class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="flex items-center pt-6">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="is_special_question" id="modalSpecialToggle" class="w-5 h-5 text-blue-600 rounded" onchange="toggleSpecialTypeFields()">
                        <span class="text-gray-700 font-bold"><?= Helpers::t('game.edit.special_label') ?></span>
                    </label>
                </div>
            </div>

            <div id="specialTypeFields" class="hidden border border-orange-200 bg-orange-50 rounded-lg p-4">
                <label class="block text-gray-700 font-bold mb-2"><?= Helpers::t('game.edit.special_type_label') ?></label>
                <select name="special_type" id="modalSpecialType"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                    <?php foreach ($specialQuestionTypes as $typeCode => $typeLabel): ?>
                        <option value="<?= Helpers::e($typeCode) ?>"><?= Helpers::e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-sm text-gray-600 mt-2"><?= Helpers::t('game.edit.special_hint') ?></p>
            </div>

            <div>
                <label class="block text-sm text-gray-600 mb-1"><?= Helpers::t('game.edit.youtube_label') ?></label>
                <div id="modalCurrentVideo" class="hidden mb-2 border rounded-lg p-3 bg-gray-50">
                    <p class="text-xs font-bold text-gray-500 mb-1"><?= Helpers::t('game.edit.current_video_label') ?></p>
                    <p id="modalCurrentVideoId" class="text-sm text-gray-700 font-mono"></p>
                    <p class="text-xs text-gray-400 mt-1"><?= Helpers::t('game.edit.video_replace_hint') ?></p>
                </div>
                <input type="url" name="video_url" placeholder="https://youtube.com/..."
                    class="w-full px-4 py-2 border rounded-lg text-sm">
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition">
                    <?= Helpers::t('game.edit.save_btn') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const JS_MODAL_ADD_TITLE  = <?= json_encode(Helpers::t('game.edit.modal_add_title')) ?>;
const JS_MODAL_EDIT_TITLE = <?= json_encode(Helpers::t('game.edit.modal_edit_title')) ?>;

function openQuestionModal(catId, points) {
    document.getElementById('modalFormAction').value = 'add_question';
    document.getElementById('modalQuestionId').value = '';
    document.getElementById('modalCatId').value = catId;
    document.getElementById('modalPoints').innerText = points;
    document.getElementById('modalPointsInput').value = points;
    document.getElementById('modalRedirectAnchor').value = `category-${catId}`;
    document.querySelector('#questionModal textarea[name="question_text"]').value = '';
    document.querySelector('#questionModal input[name="answer_text"]').value = '';
    document.getElementById('modalTimeLimit').value = '30';
    document.querySelector('#questionModal input[name="video_url"]').value = '';
    document.getElementById('modalSpecialToggle').checked = false;
    document.getElementById('modalSpecialType').value = 'cat_choose_player';
    toggleSpecialTypeFields();
    document.getElementById('modalCurrentImage').classList.add('hidden');
    document.getElementById('modalCurrentVideo').classList.add('hidden');
    document.getElementById('newImagePreviewContainer').classList.add('hidden');
    document.getElementById('newImagePreview').src = '';
    document.querySelector('#questionModal input[name="image"]').value = '';
    document.getElementById('modalCurrentAnswerImage').classList.add('hidden');
    document.getElementById('newAnswerImagePreviewContainer').classList.add('hidden');
    document.getElementById('newAnswerImagePreview').src = '';
    document.getElementById('modalAnswerImageInput').value = '';
    document.getElementById('modalTitleText').innerText = JS_MODAL_ADD_TITLE;
    document.getElementById('questionModal').classList.remove('hidden');
    document.getElementById('questionModal').classList.add('flex');
}

function openEditModal(q) {
    document.getElementById('modalFormAction').value = 'edit_question';
    document.getElementById('modalQuestionId').value = q.id;
    document.getElementById('modalCatId').value = q.catId;
    document.getElementById('modalPoints').innerText = q.points;
    document.getElementById('modalPointsInput').value = q.points;
    document.getElementById('modalRedirectAnchor').value = `category-${q.catId}`;
    document.querySelector('#questionModal textarea[name="question_text"]').value = q.question_text;
    document.querySelector('#questionModal input[name="answer_text"]').value = q.answer_text;
    document.getElementById('modalTimeLimit').value = q.time_limit;
    document.querySelector('#questionModal input[name="video_url"]').value = '';

    const imgBlock = document.getElementById('modalCurrentImage');
    const imgPreview = document.getElementById('modalCurrentImagePreview');
    const imgBroken = document.getElementById('modalImgBroken');
    if (q.image_url) {
        imgPreview.style.display = '';
        imgPreview.src = '/storage/images/' + q.image_url;
        imgBroken.classList.add('hidden');
        document.getElementById('modalClearImage').checked = false;
        imgBlock.classList.remove('hidden');
    } else {
        imgBlock.classList.add('hidden');
    }

    const vidBlock = document.getElementById('modalCurrentVideo');
    if (q.video_url) {
        document.getElementById('modalCurrentVideoId').innerText = q.video_url;
        vidBlock.classList.remove('hidden');
    } else {
        vidBlock.classList.add('hidden');
    }

    const isSpecial = q.special_type !== 'normal';
    document.getElementById('modalSpecialToggle').checked = isSpecial;
    if (isSpecial) document.getElementById('modalSpecialType').value = q.special_type;
    toggleSpecialTypeFields();
    document.getElementById('newImagePreviewContainer').classList.add('hidden');
    document.getElementById('newImagePreview').src = '';
    document.querySelector('#questionModal input[name="image"]').value = '';

    const answerImgBlock = document.getElementById('modalCurrentAnswerImage');
    const answerImgPreview = document.getElementById('modalCurrentAnswerImagePreview');
    const answerImgBroken = document.getElementById('modalAnswerImgBroken');
    if (q.answer_image_url) {
        answerImgPreview.style.display = '';
        answerImgPreview.src = '/storage/images/' + q.answer_image_url;
        answerImgBroken.classList.add('hidden');
        document.getElementById('modalClearAnswerImage').checked = false;
        answerImgBlock.classList.remove('hidden');
    } else {
        answerImgBlock.classList.add('hidden');
    }
    document.getElementById('newAnswerImagePreviewContainer').classList.add('hidden');
    document.getElementById('newAnswerImagePreview').src = '';
    document.getElementById('modalAnswerImageInput').value = '';
    document.getElementById('modalTitleText').innerText = JS_MODAL_EDIT_TITLE;
    document.getElementById('questionModal').classList.remove('hidden');
    document.getElementById('questionModal').classList.add('flex');
}

function closeQuestionModal() {
    document.getElementById('questionModal').classList.add('hidden');
    document.getElementById('questionModal').classList.remove('flex');
}

function toggleSpecialTypeFields() {
    const isEnabled = document.getElementById('modalSpecialToggle').checked;
    const container = document.getElementById('specialTypeFields');
    container.classList.toggle('hidden', !isEnabled);
}

document.querySelector('#questionModal input[name="image"]').addEventListener('change', function () {
    const file = this.files[0];
    const container = document.getElementById('newImagePreviewContainer');
    const preview = document.getElementById('newImagePreview');
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            container.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        container.classList.add('hidden');
        preview.src = '';
    }
});

document.getElementById('modalAnswerImageInput').addEventListener('change', function () {
    const file = this.files[0];
    const container = document.getElementById('newAnswerImagePreviewContainer');
    const preview = document.getElementById('newAnswerImagePreview');
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            container.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        container.classList.add('hidden');
        preview.src = '';
    }
});
</script>

<?php
$content = ob_get_clean();
$title = Helpers::t('game.edit.page_title');
include __DIR__ . '/../../layout/main.php';
?>
