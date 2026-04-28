<?php
use App\Repositories\SessionRepository;
use App\Repositories\RoundRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\LogRepository;
use App\Helpers;

$sessionRepo = new SessionRepository();
$roundRepo = new RoundRepository();
$questionRepo = new QuestionRepository();
$logRepo = new LogRepository();

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) {
    Helpers::redirect('/dashboard');
}

$session = $sessionRepo->findById($sessionId);
if (!$session || $session['host_id'] != $_SESSION['user_id']) {
    Helpers::redirect('/dashboard');
}

if ($session['status'] === 'finished') {
    Helpers::redirect("/game/summary?session_id=" . $sessionId);
}

if (!$session['current_round_id']) {
    $_SESSION['error'] = Helpers::t('game.play.no_active_round');
    Helpers::redirect('/dashboard');
}

$normalizeBool = static function ($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'true', 't', 'yes', 'y', 'on'], true);
};

$resolveSpecialType = static function (array $question) use ($normalizeBool) {
    $specialType = trim((string) ($question['special_type'] ?? ''));
    if ($specialType !== '') {
        return $specialType;
    }

    return $normalizeBool($question['is_cat_in_bag'] ?? false) ? 'cat_choose_player' : 'normal';
};

$participants = $sessionRepo->getParticipants($sessionId);
$participantsById = [];
foreach ($participants as $participant) {
    $participantsById[(int) $participant['id']] = $participant;
}

$activeQuestion = null;
if (!empty($session['active_question_id'])) {
    $activeQuestion = $questionRepo->findById($session['active_question_id']);
    if (!$activeQuestion) {
        $sessionRepo->clearActiveQuestion($sessionId);
        unset($_SESSION['q_started'][$sessionId]);
        $session['active_question_id'] = null;
        $session['cat_target_participant_id'] = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (Helpers::verifyCsrfToken($csrfToken)) {
        $action = $_POST['action'] ?? '';
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $participantId = (int) ($_POST['participant_id'] ?? 0);

        if ($action === 'open_question' && empty($session['active_question_id'])) {
            $answeredIds = array_map('intval', $logRepo->getAnsweredQuestions($sessionId));
            $question = $questionRepo->findById($questionId);

            if ($question && !in_array($questionId, $answeredIds, true)) {
                $sessionRepo->setActiveQuestion($sessionId, $questionId);
                $_SESSION['q_started'][$sessionId] = time();
            }

        } elseif ($action === 'assign_cat_player' && !empty($session['active_question_id'])) {
            $question = $questionRepo->findById($session['active_question_id']);
            $specialType = $question ? $resolveSpecialType($question) : 'normal';

            $catTypes = ['cat_choose_player', 'cat_gift', 'cat_penalty'];
            if ($question && in_array($specialType, $catTypes, true) && isset($participantsById[$participantId])) {
                $sessionRepo->assignCatTargetParticipant($sessionId, $participantId);
            }

        } elseif (in_array($action, ['correct', 'wrong', 'skip'], true) && !empty($session['active_question_id'])) {
            $question = $questionRepo->findById($session['active_question_id']);

            if ($question) {
                $specialType = $resolveSpecialType($question);
                $requiresTargetPlayer = $specialType === 'cat_choose_player';
                $points = (int) ($question['points'] ?? 0);
                $resolvedQuestionId = (int) $question['id'];
                $catTargetId = (int) ($session['cat_target_participant_id'] ?? 0);

                if ($action === 'correct' && isset($participantsById[$participantId])) {
                    if (!$requiresTargetPlayer || $participantId === $catTargetId) {
                        $logRepo->logAction($sessionId, $resolvedQuestionId, $participantId, 'answered_correct', $points);
                        $sessionRepo->updateParticipantScore($participantId, $points);
                        $sessionRepo->clearActiveQuestion($sessionId);
                        unset($_SESSION['q_started'][$sessionId]);
                    }

                } elseif ($action === 'wrong' && isset($participantsById[$participantId])) {
                    if (!$requiresTargetPlayer || $participantId === $catTargetId) {
                        $logRepo->logAction($sessionId, $resolvedQuestionId, $participantId, 'answered_wrong', -$points);
                        $sessionRepo->updateParticipantScore($participantId, -$points);
                        $sessionRepo->clearActiveQuestion($sessionId);
                        unset($_SESSION['q_started'][$sessionId]);
                    }

                } elseif ($action === 'skip' && !$requiresTargetPlayer) {
                    $logRepo->logAction($sessionId, $resolvedQuestionId, null, 'skipped', 0);
                    $sessionRepo->clearActiveQuestion($sessionId);
                    unset($_SESSION['q_started'][$sessionId]);
                }
            }

        } elseif ($action === 'apply_cat_effect' && !empty($session['active_question_id'])) {
            $question = $questionRepo->findById($session['active_question_id']);
            if ($question && !empty($session['cat_target_participant_id'])) {
                $specialType = $resolveSpecialType($question);
                $targetId = (int) $session['cat_target_participant_id'];
                $points   = (int) $question['points'];
                $qId      = (int) $question['id'];

                if ($specialType === 'cat_gift' && isset($participantsById[$targetId])) {
                    $logRepo->logAction($sessionId, $qId, $targetId, 'answered_correct', $points);
                    $sessionRepo->updateParticipantScore($targetId, $points);
                    $sessionRepo->clearActiveQuestion($sessionId);
                    unset($_SESSION['q_started'][$sessionId]);
                } elseif ($specialType === 'cat_penalty' && isset($participantsById[$targetId])) {
                    $logRepo->logAction($sessionId, $qId, $targetId, 'answered_wrong', -$points);
                    $sessionRepo->updateParticipantScore($targetId, -$points);
                    $sessionRepo->clearActiveQuestion($sessionId);
                    unset($_SESSION['q_started'][$sessionId]);
                }
            }

        } elseif ($action === 'cancel_question' && !empty($session['active_question_id'])) {
            $sessionRepo->clearActiveQuestion($sessionId);
            unset($_SESSION['q_started'][$sessionId]);

        } elseif ($action === 'next_round' && empty($session['active_question_id'])) {
            $nextRound = $roundRepo->getNextRound($session['game_id'], $session['current_round_id']);
            if ($nextRound) {
                $sessionRepo->updateCurrentRound($sessionId, $nextRound['id']);
            } else {
                $sessionRepo->updateStatus($sessionId, 'finished');
                Helpers::redirect("/game/summary?session_id=" . $sessionId);
            }

        } elseif ($action === 'finish') {
            $sessionRepo->clearActiveQuestion($sessionId);
            unset($_SESSION['q_started'][$sessionId]);
            $sessionRepo->updateStatus($sessionId, 'finished');
            Helpers::redirect("/game/summary?session_id=" . $sessionId);
        }
    }

    Helpers::redirect("/game/play?session_id=" . $sessionId);
}

$session = $sessionRepo->findById($sessionId);
$participants = $sessionRepo->getParticipants($sessionId);
$participantsById = [];
foreach ($participants as $participant) {
    $participantsById[(int) $participant['id']] = $participant;
}

$activeQuestion = null;
if (!empty($session['active_question_id'])) {
    $activeQuestion = $questionRepo->findById($session['active_question_id']);
    if (!$activeQuestion) {
        $sessionRepo->clearActiveQuestion($sessionId);
        unset($_SESSION['q_started'][$sessionId]);
        $session['active_question_id'] = null;
        $session['cat_target_participant_id'] = null;
    }
}

// Load round data
$allRounds = $roundRepo->findByGameId($session['game_id']);
$currentRound = $roundRepo->findById($session['current_round_id']);
$nextRound = $roundRepo->getNextRound($session['game_id'], $session['current_round_id']);

$roundNumber = 1;
foreach ($allRounds as $i => $round) {
    if ($round['id'] == $session['current_round_id']) {
        $roundNumber = $i + 1;
        break;
    }
}
$totalRounds = count($allRounds);

$roundData = $questionRepo->getFullRoundData($session['current_round_id']);
$answeredIds = array_map('intval', $logRepo->getAnsweredQuestions($sessionId));

// Group by category
$categories = [];
foreach ($roundData as $row) {
    $catId = $row['category_id'];
    if (!isset($categories[$catId])) {
        $categories[$catId] = ['name' => $row['category_name'], 'questions' => []];
    }
    if ($row['question_id']) {
        $row['is_answered'] = in_array((int) $row['question_id'], $answeredIds, true);
        $row['special_type'] = $resolveSpecialType($row);
        $categories[$catId]['questions'][] = $row;
    }
}

$activeQuestionSpecialType = $activeQuestion ? $resolveSpecialType($activeQuestion) : 'normal';
$catTypes = ['cat_choose_player', 'cat_gift', 'cat_penalty'];
$activeQuestionIsCat = in_array($activeQuestionSpecialType, $catTypes, true);
$catTargetParticipant = null;
if (!empty($session['cat_target_participant_id']) && isset($participantsById[(int) $session['cat_target_participant_id']])) {
    $catTargetParticipant = $participantsById[(int) $session['cat_target_participant_id']];
}

$timerSeconds = $activeQuestion ? (int) ($activeQuestion['time_limit'] ?? 30) : 30;
if ($activeQuestion && !empty($_SESSION['q_started'][$sessionId])) {
    $elapsed = time() - (int) $_SESSION['q_started'][$sessionId];
    $timerSeconds = max(0, $timerSeconds - $elapsed);
}

$showCatSelection = $activeQuestion && $activeQuestionIsCat && !$catTargetParticipant;
$showGiftModal    = $activeQuestion && $activeQuestionSpecialType === 'cat_gift'    && $catTargetParticipant !== null;
$showPenaltyModal = $activeQuestion && $activeQuestionSpecialType === 'cat_penalty' && $catTargetParticipant !== null;
$showQuestionModal = $activeQuestion && !$showCatSelection && !$showGiftModal && !$showPenaltyModal;
$answerParticipants = $participants;
if ($activeQuestionSpecialType === 'cat_choose_player' && $catTargetParticipant) {
    $answerParticipants = [$catTargetParticipant];
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4">

    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= Helpers::e($session['game_title']) ?></h1>
            <div class="flex items-center gap-3 mt-1">
                <?php foreach ($allRounds as $round): ?>
                    <span class="text-sm font-bold px-3 py-1 rounded-full <?= $round['id'] == $session['current_round_id'] ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' ?>">
                        <?= Helpers::e($round['name']) ?>
                    </span>
                <?php endforeach; ?>
                <span class="text-gray-400 text-sm"><?= $roundNumber ?> / <?= $totalRounds ?></span>
            </div>
        </div>

        <div class="flex gap-2">
            <?php if ($nextRound): ?>
                <form id="nextRoundForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="next_round">
                    <button type="button"
                        <?= $activeQuestion ? 'disabled' : '' ?>
                        onclick="showConfirmModal('nextRound')"
                        class="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold transition shadow <?= $activeQuestion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700' ?>">
                        <?= Helpers::e($nextRound['name']) ?> →
                    </button>
                </form>
            <?php endif; ?>
            <form id="finishForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                <input type="hidden" name="action" value="finish">
                <button type="button"
                    <?= $activeQuestion ? 'disabled' : '' ?>
                    onclick="showConfirmModal('finish')"
                    class="bg-red-500 text-white px-4 py-2 rounded-lg font-bold transition <?= $activeQuestion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-red-600' ?>">
                    <?= Helpers::t('game.play.finish_btn') ?>
                </button>
            </form>
        </div>
    </div>

    <div class="bg-blue-800 text-white text-center py-2 rounded-lg mb-4 font-black text-xl tracking-widest uppercase">
        <?= Helpers::e($currentRound['name'] ?? '') ?>
    </div>

    <div class="bg-blue-900 p-4 rounded-xl shadow-2xl overflow-x-auto">
        <table class="w-full border-collapse">
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr class="border-b border-blue-800">
                        <td class="p-4 text-white font-bold text-2xl bg-blue-800 w-1/4 text-center border-r border-blue-700">
                            <?= Helpers::e($cat['name']) ?>
                        </td>
                        <?php
                        $pointsList = [100, 200, 300, 400, 500];
                        $qByPoints = [];
                        foreach ($cat['questions'] as $question) {
                            $qByPoints[$question['points']] = $question;
                        }

                        foreach ($pointsList as $points):
                            $question = $qByPoints[$points] ?? null;
                        ?>
                            <td class="p-0 border-r border-blue-700 last:border-r-0 text-center w-[15%] relative">
                                <?php if ($question): ?>
                                    <?php if ($question['is_answered']): ?>
                                        <div class="absolute inset-0 bg-blue-950"></div>
                                    <?php else: ?>
                                        <button type="button"
                                            onclick="submitOpenQuestion(<?= (int) $question['question_id'] ?>)"
                                            <?= $activeQuestion ? 'disabled' : '' ?>
                                            class="w-full py-6 transition duration-200 <?= $activeQuestion ? 'bg-blue-950 text-blue-700 cursor-not-allowed' : 'bg-blue-900 hover:bg-blue-700 text-yellow-400' ?> font-bold text-3xl">
                                            <span class="block"><?= $points ?></span>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="py-6"></div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($participants as $participant): ?>
            <?php
            $isCatTarget = $catTargetParticipant && (int) $participant['id'] === (int) $catTargetParticipant['id'];
            ?>
            <div class="bg-white p-4 rounded-lg shadow border-b-4 text-center <?= $isCatTarget ? 'border-orange-500 ring-2 ring-orange-300' : 'border-blue-600' ?>">
                <div class="text-gray-500 text-sm font-medium mb-1"><?= Helpers::e($participant['name']) ?></div>
                <div class="text-2xl font-bold text-blue-800"><?= $participant['score'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="catModal" class="fixed inset-0 bg-blue-950 bg-opacity-95 <?= $showCatSelection ? 'flex' : 'hidden' ?> items-center justify-center z-50 p-4">
    <?php if ($showCatSelection):
        $catCfg = [
            'cat_choose_player' => ['emoji' => '🐱', 'title' => Helpers::t('game.play.cat_title'),     'desc' => Helpers::t('game.play.cat_desc'),     'btnClass' => 'bg-orange-500 hover:bg-orange-600'],
            'cat_gift'          => ['emoji' => '🎁', 'title' => Helpers::t('game.play.gift_title'),    'desc' => Helpers::t('game.play.gift_desc'),    'btnClass' => 'bg-green-500 hover:bg-green-600'],
            'cat_penalty'       => ['emoji' => '💣', 'title' => Helpers::t('game.play.penalty_title'), 'desc' => Helpers::t('game.play.penalty_desc'), 'btnClass' => 'bg-red-500 hover:bg-red-600'],
        ];
        $cfg = $catCfg[$activeQuestionSpecialType] ?? $catCfg['cat_choose_player'];
    ?>
        <div class="max-w-3xl w-full text-center bg-white rounded-3xl p-8 shadow-2xl">
            <div class="text-5xl mb-4"><?= $cfg['emoji'] ?></div>
            <h2 class="text-4xl font-black text-gray-800 mb-3"><?= $cfg['title'] ?></h2>
            <p class="text-lg text-gray-600 mb-2">Упай: <?= (int) $activeQuestion['points'] ?></p>
            <p class="text-gray-500 mb-8"><?= $cfg['desc'] ?></p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($participants as $participant): ?>
                    <button type="button"
                        onclick="submitCatTarget(<?= (int) $participant['id'] ?>)"
                        class="<?= $cfg['btnClass'] ?> text-white rounded-2xl p-5 font-bold text-lg transition shadow-lg">
                        <?= Helpers::e($participant['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="mt-6">
                <button type="button" onclick="cancelQuestion()" class="text-gray-400 hover:text-gray-700 underline text-sm">
                    <?= Helpers::t('game.play.close_question') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="giftModal" class="fixed inset-0 bg-green-950 bg-opacity-95 <?= $showGiftModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 p-4">
    <?php if ($showGiftModal): ?>
        <div class="max-w-2xl w-full text-center bg-white rounded-3xl p-10 shadow-2xl">
            <div class="text-6xl mb-4">🎁</div>
            <h2 class="text-5xl font-black text-green-600 mb-4"><?= Helpers::t('game.play.gift_title') ?></h2>
            <p class="text-2xl font-bold text-gray-800 mb-2"><?= Helpers::e($catTargetParticipant['name']) ?></p>
            <?php if (!empty($activeQuestion['question_text'])): ?>
                <p class="text-gray-500 text-lg mb-4"><?= Helpers::e($activeQuestion['question_text']) ?></p>
            <?php endif; ?>
            <p class="text-6xl font-black text-green-500 mb-10">+<?= (int) $activeQuestion['points'] ?></p>
            <button type="button" onclick="submitApplyCatEffect()"
                class="bg-green-500 text-white px-14 py-4 rounded-full text-2xl font-black hover:bg-green-600 transition shadow-xl">
                <?= Helpers::t('game.play.gift_apply') ?>
            </button>
            <div class="mt-6">
                <button type="button" onclick="cancelQuestion()" class="text-gray-400 hover:text-gray-600 underline text-sm">
                    <?= Helpers::t('game.play.close_question') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="penaltyModal" class="fixed inset-0 bg-red-950 bg-opacity-95 <?= $showPenaltyModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 p-4">
    <?php if ($showPenaltyModal): ?>
        <div class="max-w-2xl w-full text-center bg-white rounded-3xl p-10 shadow-2xl">
            <div class="text-6xl mb-4">💣</div>
            <h2 class="text-5xl font-black text-red-600 mb-4"><?= Helpers::t('game.play.penalty_title') ?></h2>
            <p class="text-2xl font-bold text-gray-800 mb-2"><?= Helpers::e($catTargetParticipant['name']) ?></p>
            <?php if (!empty($activeQuestion['question_text'])): ?>
                <p class="text-gray-500 text-lg mb-4"><?= Helpers::e($activeQuestion['question_text']) ?></p>
            <?php endif; ?>
            <p class="text-6xl font-black text-red-500 mb-10">-<?= (int) $activeQuestion['points'] ?></p>
            <button type="button" onclick="submitApplyCatEffect()"
                class="bg-red-500 text-white px-14 py-4 rounded-full text-2xl font-black hover:bg-red-600 transition shadow-xl">
                <?= Helpers::t('game.play.penalty_apply') ?>
            </button>
            <div class="mt-6">
                <button type="button" onclick="cancelQuestion()" class="text-gray-400 hover:text-gray-600 underline text-sm">
                    <?= Helpers::t('game.play.close_question') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="qModal" class="fixed inset-0 bg-blue-950 bg-opacity-95 <?= $showQuestionModal ? 'flex' : 'hidden' ?> flex-col items-center justify-center z-50 p-4">
    <?php if ($showQuestionModal): ?>
        <div class="max-w-4xl w-full text-center">
            <div id="timerDisplay" class="text-6xl font-black text-white mb-8"><?= $timerSeconds ?></div>

            <?php if ($activeQuestionIsCat && $catTargetParticipant): ?>
                <div class="inline-flex items-center gap-2 bg-orange-500 text-white px-5 py-2 rounded-full font-bold mb-6">
                    <span><?= Helpers::t('game.play.cat_title') ?></span>
                    <span>•</span>
                    <span><?= Helpers::e($catTargetParticipant['name']) ?> <?= Helpers::t('game.play.cat_answers') ?></span>
                </div>
            <?php endif; ?>

            <div id="qContent" class="mb-12">
                <h2 id="qText" class="text-4xl md:text-5xl font-bold text-white leading-tight mb-8">
                    <?= Helpers::e($activeQuestion['question_text']) ?>
                </h2>
                <div id="qMedia" class="flex justify-center mb-8">
                    <?php if (!empty($activeQuestion['image_url'])): ?>
                        <img src="/storage/images/<?= Helpers::e($activeQuestion['image_url']) ?>" class="max-h-80 rounded-lg shadow-lg">
                    <?php elseif (!empty($activeQuestion['video_url'])): ?>
                        <iframe width="560" height="315"
                            src="https://www.youtube.com/embed/<?= Helpers::e($activeQuestion['video_url']) ?>?autoplay=1"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen></iframe>
                    <?php endif; ?>
                </div>
            </div>

            <div id="qControls" class="space-y-8">
                <button type="button" onclick="showAnswer()" id="showBtn"
                    class="bg-yellow-500 text-blue-900 px-12 py-4 rounded-full text-2xl font-black hover:bg-yellow-400 transition shadow-xl">
                    <?= Helpers::t('game.play.show_answer_btn') ?>
                </button>

                <div id="answerSection" class="hidden animate-bounce-in">
                    <div id="aText" class="text-5xl font-black mb-12" style="color: #FACC15;">
                        <?= Helpers::e($activeQuestion['answer_text']) ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-5xl mx-auto">
                        <?php foreach ($answerParticipants as $participant): ?>
                            <div class="bg-blue-800 p-4 rounded-xl flex items-center justify-between gap-2">
                                <span class="text-white font-bold truncate"><?= Helpers::e($participant['name']) ?></span>
                                <div class="flex gap-1">
                                    <button type="button" onclick="submitAction('correct', <?= (int) $participant['id'] ?>)"
                                        class="bg-green-500 p-2 rounded text-white font-bold hover:bg-green-600">+</button>
                                    <button type="button" onclick="submitAction('wrong', <?= (int) $participant['id'] ?>)"
                                        class="bg-red-500 p-2 rounded text-white font-bold hover:bg-red-600">-</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-8 flex justify-center gap-8">
                        <?php if (!$activeQuestionIsCat): ?>
                            <button type="button" onclick="submitAction('skip')" class="text-gray-400 hover:text-white underline">
                                <?= Helpers::t('game.play.skip_btn') ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="cancelQuestion()" class="text-gray-400 hover:text-white underline">
                            <?= Helpers::t('game.play.close_question') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
        <p id="confirmModalText" class="text-xl font-bold text-gray-800 mb-8"></p>
        <div class="flex justify-center gap-4">
            <button type="button" onclick="confirmModalYes()"
                class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold text-lg hover:bg-blue-700 transition">
                <?= Helpers::t('game.play.confirm_yes') ?>
            </button>
            <button type="button" onclick="closeConfirmModal()"
                class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold text-lg hover:bg-gray-300 transition">
                <?= Helpers::t('game.play.confirm_no') ?>
            </button>
        </div>
    </div>
</div>

<form id="openQuestionForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="open_question">
    <input type="hidden" name="question_id" id="openQuestionId">
</form>

<form id="catTargetForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="assign_cat_player">
    <input type="hidden" name="participant_id" id="catTargetParticipantId">
</form>

<form id="actionForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="question_id" value="<?= $activeQuestion ? (int) $activeQuestion['id'] : '' ?>">
    <input type="hidden" name="participant_id" id="formPId">
</form>

<form id="cancelQuestionForm" action="/game/play?session_id=<?= $sessionId ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="cancel_question">
</form>

<script>
let timerInterval = null;
let confirmModalTarget = null;

const confirmMessages = {
    nextRound: <?= json_encode(Helpers::t('game.play.next_round_confirm', ['name' => $nextRound['name'] ?? ''])) ?>,
    finish: <?= json_encode(Helpers::t('game.play.finish_confirm')) ?>,
};

function showConfirmModal(target) {
    confirmModalTarget = target;
    document.getElementById('confirmModalText').textContent = confirmMessages[target] || '';
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeConfirmModal() {
    confirmModalTarget = null;
    const modal = document.getElementById('confirmModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function confirmModalYes() {
    if (confirmModalTarget === 'nextRound') {
        document.getElementById('nextRoundForm').submit();
    } else if (confirmModalTarget === 'finish') {
        document.getElementById('finishForm').submit();
    }
    closeConfirmModal();
}

function submitOpenQuestion(questionId) {
    document.getElementById('openQuestionId').value = questionId;
    document.getElementById('openQuestionForm').submit();
}

function submitCatTarget(participantId) {
    document.getElementById('catTargetParticipantId').value = participantId;
    document.getElementById('catTargetForm').submit();
}

function startTimer(seconds) {
    let timeLeft = seconds;
    const display = document.getElementById('timerDisplay');

    if (!display) {
        return;
    }

    display.innerText = timeLeft;
    display.classList.remove('text-red-500');

    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        display.innerText = timeLeft;
        if (timeLeft <= 5) display.classList.add('text-red-500');
        if (timeLeft <= 0) clearInterval(timerInterval);
    }, 1000);
}

function showAnswer() {
    clearInterval(timerInterval);
    document.getElementById('showBtn').classList.add('hidden');
    document.getElementById('answerSection').classList.remove('hidden');
}

function submitAction(action, participantId = '') {
    document.getElementById('formAction').value = action;
    document.getElementById('formPId').value = participantId;
    document.getElementById('actionForm').submit();
}

function submitApplyCatEffect() {
    document.getElementById('formAction').value = 'apply_cat_effect';
    document.getElementById('formPId').value = '';
    document.getElementById('actionForm').submit();
}

function cancelQuestion() {
    document.getElementById('cancelQuestionForm').submit();
}

<?php if ($showQuestionModal): ?>
document.addEventListener('DOMContentLoaded', function() {
    startTimer(<?= $timerSeconds ?>);
});
<?php endif; ?>
</script>

<style>
@keyframes bounce-in {
    0%   { transform: scale(0.9); opacity: 0; }
    70%  { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-bounce-in { animation: bounce-in 0.4s ease-out forwards; }
</style>

<?php
$content = ob_get_clean();
$title = Helpers::t('game.play.page_title');
include __DIR__ . '/../layout/main.php';
?>
