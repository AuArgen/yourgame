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

        } elseif ($action === 'select_final_category' && empty($session['active_question_id'])) {
            $catId = (int) ($_POST['category_id'] ?? 0);
            if ($catId) {
                $sessionRepo->setFinalCategory($sessionId, $catId);
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

$isLastRound   = ($roundNumber === $totalRounds && $totalRounds >= 1);
$finalCategoryId = $isLastRound ? (int) ($session['final_category_id'] ?? 0) : 0;

// Filter categories to the selected final category if one has been chosen
if ($finalCategoryId && isset($categories[$finalCategoryId])) {
    $categories = [$finalCategoryId => $categories[$finalCategoryId]];
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

$showFinalCatPick = $isLastRound && !$finalCategoryId && !$activeQuestion;
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

<!-- Final round: category picker -->
<div id="finalCatModal" class="fixed inset-0 bg-blue-950 bg-opacity-97 <?= $showFinalCatPick ? 'flex' : 'hidden' ?> flex-col items-center justify-center z-50 p-8">
    <div class="max-w-4xl w-full text-center">
        <div class="text-yellow-400 font-black text-4xl md:text-5xl tracking-widest mb-3">
            ⭐ <?= Helpers::t('game.play.final_round_title') ?> ⭐
        </div>
        <p class="text-blue-200 text-lg mb-10"><?= Helpers::t('game.play.final_round_desc') ?></p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php
            $allCatsForFinal = [];
            foreach ($questionRepo->getFullRoundData($session['current_round_id']) as $row) {
                $cid = $row['category_id'];
                if (!isset($allCatsForFinal[$cid])) {
                    $allCatsForFinal[$cid] = $row['category_name'];
                }
            }
            foreach ($allCatsForFinal as $cid => $cname):
            ?>
                <form action="/game/play?session_id=<?= $sessionId ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="select_final_category">
                    <input type="hidden" name="category_id" value="<?= (int) $cid ?>">
                    <button type="submit"
                        class="w-full bg-blue-800 hover:bg-yellow-500 hover:text-blue-900 text-white font-black text-xl py-8 px-6 rounded-2xl shadow-xl transition-all duration-200 border-2 border-blue-600 hover:border-yellow-400 hover:scale-105">
                        <?= Helpers::e($cname) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
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

<div id="qModal" class="fixed inset-0 bg-blue-950 bg-opacity-95 <?= $showQuestionModal ? 'flex' : 'hidden' ?> flex-col items-center justify-start z-50 overflow-y-auto">
    <?php if ($showQuestionModal): ?>
        <?php $hasMedia = !empty($activeQuestion['image_url']) || !empty($activeQuestion['video_url']); ?>
        <div class="max-w-4xl w-full mx-auto text-center px-3 py-4">

            <div id="timerDisplay" class="text-3xl sm:text-5xl font-black text-white mb-2 sm:mb-4"><?= $timerSeconds ?></div>

            <?php if ($activeQuestionIsCat && $catTargetParticipant): ?>
                <div class="inline-flex items-center gap-2 bg-orange-500 text-white px-4 py-1.5 rounded-full font-bold mb-2 sm:mb-4 text-sm">
                    <span><?= Helpers::t('game.play.cat_title') ?></span>
                    <span>•</span>
                    <span><?= Helpers::e($catTargetParticipant['name']) ?> <?= Helpers::t('game.play.cat_answers') ?></span>
                </div>
            <?php endif; ?>

            <div id="qContent" class="mb-4 sm:mb-6">
                <h2 id="qText" class="font-bold text-white leading-tight mb-3 <?= $hasMedia ? 'text-lg sm:text-2xl' : 'text-2xl sm:text-4xl md:text-5xl' ?>">
                    <?= Helpers::e($activeQuestion['question_text']) ?>
                </h2>

                <?php if (!empty($activeQuestion['image_url'])): ?>
                <div class="flex justify-center mb-3">
                    <img src="/storage/images/<?= Helpers::e($activeQuestion['image_url']) ?>"
                         class="rounded-xl shadow-2xl object-contain w-full"
                         style="max-height: <?= $hasMedia && !empty($activeQuestion['video_url']) ? '30vh' : '50vh' ?>">
                </div>
                <?php endif; ?>

                <?php if (!empty($activeQuestion['video_url'])): ?>
                <div class="mb-3" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;">
                    <iframe style="position:absolute;top:0;left:0;width:100%;height:100%;"
                        src="https://www.youtube.com/embed/<?= Helpers::e($activeQuestion['video_url']) ?>?autoplay=1"
                        frameborder="0"
                        allow="autoplay; encrypted-media"
                        allowfullscreen></iframe>
                </div>
                <?php endif; ?>
            </div>

            <div id="qControls">
                <button type="button" onclick="showAnswer()" id="showBtn"
                    class="bg-yellow-500 text-blue-900 px-8 sm:px-12 py-3 sm:py-4 rounded-full text-lg sm:text-2xl font-black hover:bg-yellow-400 transition shadow-xl mb-4">
                    <?= Helpers::t('game.play.show_answer_btn') ?>
                </button>

                <div id="answerSection" class="hidden animate-bounce-in">
                    <div id="aText" class="text-2xl sm:text-4xl font-black mb-3 sm:mb-4" style="color: #FACC15;">
                        <?= Helpers::e($activeQuestion['answer_text']) ?>
                    </div>

                    <?php if (!empty($activeQuestion['answer_image_url'])): ?>
                        <div class="flex justify-center mb-3">
                            <img src="/storage/images/<?= Helpers::e($activeQuestion['answer_image_url']) ?>"
                                 class="rounded-lg shadow-lg object-contain w-full" style="max-height:35vh">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($activeQuestion['answer_video_url'])): ?>
                        <div id="answerVideoContainer"
                            data-vid="<?= Helpers::e($activeQuestion['answer_video_url']) ?>"
                            style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;max-width:700px;margin:0 auto 12px;"></div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 max-w-5xl mx-auto mb-3">
                        <?php foreach ($answerParticipants as $participant): ?>
                            <div class="bg-blue-800 p-2 sm:p-3 rounded-xl flex items-center justify-between gap-1 sm:gap-2">
                                <span class="text-white font-bold truncate text-sm sm:text-base"><?= Helpers::e($participant['name']) ?></span>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" onclick="submitAction('correct', <?= (int) $participant['id'] ?>)"
                                        class="bg-green-500 w-7 h-7 sm:w-8 sm:h-8 rounded text-white font-black hover:bg-green-600 text-base">+</button>
                                    <button type="button" onclick="submitAction('wrong', <?= (int) $participant['id'] ?>)"
                                        class="bg-red-500 w-7 h-7 sm:w-8 sm:h-8 rounded text-white font-black hover:bg-red-600 text-base">-</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-center gap-6 pb-4">
                        <?php if (!$activeQuestionIsCat): ?>
                            <button type="button" onclick="submitAction('skip')" class="text-gray-400 hover:text-white underline text-sm">
                                <?= Helpers::t('game.play.skip_btn') ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="cancelQuestion()" class="text-gray-400 hover:text-white underline text-sm">
                            <?= Helpers::t('game.play.close_question') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Audio hint toast -->
<div id="audioHint" class="fixed top-6 left-1/2 -translate-x-1/2 bg-black bg-opacity-80 text-white text-lg font-bold px-6 py-3 rounded-full z-[200] opacity-0 transition-opacity duration-300 pointer-events-none select-none"></div>

<!-- Floating audio player tab -->
<button id="audioToggleTab"
    onclick="toggleAudioPanel()"
    class="fixed bottom-0 left-6 z-[110] bg-blue-700 hover:bg-blue-600 text-white font-bold px-5 py-2 rounded-t-xl shadow-lg text-sm transition select-none">
    🎵 ▲
</button>

<!-- Floating audio player panel -->
<div id="audioPlayer"
    class="fixed bottom-0 left-0 right-0 z-[100] bg-gray-900 border-t-2 border-blue-700 shadow-2xl translate-y-full transition-transform duration-300 ease-in-out">
    <div class="max-w-3xl mx-auto px-4 py-3 flex flex-col gap-2">

        <!-- Top row: title + controls -->
        <div class="flex items-center gap-3">
            <span class="text-blue-300 font-bold text-sm whitespace-nowrap">
                🎵 <?= Helpers::e($currentRound['name'] ?? 'Раунд ' . $roundNumber) ?>
            </span>

            <!-- Play/Pause -->
            <button id="playPauseBtn" onclick="toggleAudio()"
                class="bg-blue-600 hover:bg-blue-500 text-white font-black text-lg w-10 h-10 rounded-full flex items-center justify-center transition shrink-0">
                ▶
            </button>

            <!-- Restart -->
            <button onclick="restartAudio()" title="Restart (R)"
                class="bg-gray-700 hover:bg-gray-600 text-white text-sm w-8 h-8 rounded-full flex items-center justify-center transition shrink-0">
                ↺
            </button>

            <!-- Progress bar -->
            <div id="progressBar" onclick="seekAudio(event)"
                class="flex-1 h-2 bg-gray-700 rounded-full cursor-pointer relative overflow-hidden">
                <div id="audioProgress" class="h-full bg-blue-500 rounded-full transition-all" style="width:0%"></div>
            </div>

            <!-- Time -->
            <span id="audioTime" class="text-gray-400 text-xs whitespace-nowrap shrink-0">0:00 / 0:00</span>

            <!-- Mute -->
            <button id="muteBtn" onclick="toggleMute()" title="Mute (M)"
                class="text-xl shrink-0 hover:scale-110 transition">🔊</button>

            <!-- Volume slider -->
            <input id="volSlider" type="range" min="0" max="1" step="0.05" value="0.5"
                oninput="setVolume(this.value)"
                class="w-20 accent-blue-500 shrink-0">
        </div>

        <!-- Keyboard shortcuts hint -->
        <div class="text-gray-500 text-xs flex gap-4 flex-wrap">
            <span><kbd class="bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded text-xs">Space</kbd> play/pause</span>
            <span><kbd class="bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded text-xs">M</kbd> mute</span>
            <span><kbd class="bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded text-xs">↑↓</kbd> volume</span>
            <span><kbd class="bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded text-xs">R</kbd> restart</span>
        </div>
    </div>
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
        sessionStorage.setItem('audioAutoplay', '1');
        document.getElementById('nextRoundForm').submit();
    } else if (confirmModalTarget === 'finish') {
        sessionStorage.removeItem('audioAutoplay');
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
    document.getElementById('qContent').classList.add('hidden');
    document.getElementById('showBtn').classList.add('hidden');
    document.getElementById('answerSection').classList.remove('hidden');
    const vc = document.getElementById('answerVideoContainer');
    if (vc && !vc.hasChildNodes()) {
        const iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube.com/embed/' + vc.dataset.vid + '?autoplay=1';
        iframe.frameBorder = '0';
        iframe.allow = 'autoplay; encrypted-media';
        iframe.allowFullscreen = true;
        iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;';
        vc.appendChild(iframe);
    }
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

// ── Audio Player ──────────────────────────────────────────────────
const ROUND_AUDIO_SRC = '/storage/raund_audio/raund_<?= $roundNumber ?>.mpeg';
const ROUND_NUMBER    = <?= $roundNumber ?>;
const AUDIO_STATE_KEY = 'audioState';
let audio = null;
let audioReady = false;
let audioVisible = false;

function initAudio(restore = null) {
    if (audio) return;
    audio = new Audio(ROUND_AUDIO_SRC);
    audio.loop = true;
    audio.volume = restore ? (restore.volume ?? 0.5) : 0.5;
    audio.muted  = restore ? (restore.muted  ?? false) : false;

    audio.addEventListener('loadedmetadata', () => {
        if (restore && restore.time > 0) {
            audio.currentTime = restore.time;
        }
    });

    audio.addEventListener('canplay', () => {
        audioReady = true;
        updateAudioUI();
    });

    audio.addEventListener('error', () => {
        document.getElementById('audioPlayer').style.display = 'none';
        document.getElementById('audioToggleTab').style.display = 'none';
    });

    audio.addEventListener('timeupdate', updateProgress);
    audio.addEventListener('play',  updateAudioUI);
    audio.addEventListener('pause', updateAudioUI);
    audio.addEventListener('volumechange', updateAudioUI);
}

// Сохраняем состояние перед любым уходом со страницы
window.addEventListener('beforeunload', () => {
    if (!audio) return;
    sessionStorage.setItem(AUDIO_STATE_KEY, JSON.stringify({
        round:  ROUND_NUMBER,
        time:   audio.currentTime,
        volume: audio.volume,
        muted:  audio.muted,
        paused: audio.paused,
    }));
});

function toggleAudio() {
    initAudio();
    if (audio.paused) {
        audio.play();
    } else {
        audio.pause();
    }
}

function toggleMute() {
    if (!audio) return;
    audio.muted = !audio.muted;
    updateAudioUI();
}

function setVolume(val) {
    if (!audio) return;
    audio.volume = Math.min(1, Math.max(0, parseFloat(val)));
    audio.muted = false;
    updateAudioUI();
}

function restartAudio() {
    if (!audio) return;
    audio.currentTime = 0;
    audio.play();
}

function changeVolume(delta) {
    if (!audio) { initAudio(); return; }
    setVolume(audio.volume + delta);
    document.getElementById('volSlider').value = audio.volume;
}

function formatTime(s) {
    if (!isFinite(s)) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return m + ':' + String(sec).padStart(2, '0');
}

function updateProgress() {
    if (!audio) return;
    const pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
    document.getElementById('audioProgress').style.width = pct + '%';
    document.getElementById('audioTime').textContent =
        formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration);
}

function updateAudioUI() {
    if (!audio) return;
    const btn = document.getElementById('playPauseBtn');
    btn.textContent = audio.paused ? '▶' : '⏸';
    document.getElementById('muteBtn').textContent = (audio.muted || audio.volume === 0) ? '🔇' : '🔊';
    document.getElementById('volSlider').value = audio.muted ? 0 : audio.volume;
    updateProgress();
}

function seekAudio(e) {
    if (!audio || !audio.duration) return;
    const bar = document.getElementById('progressBar');
    const rect = bar.getBoundingClientRect();
    const pct = (e.clientX - rect.left) / rect.width;
    audio.currentTime = pct * audio.duration;
}

function openAudioPanel() {
    if (audioVisible) return;
    audioVisible = true;
    document.getElementById('audioPlayer').classList.remove('translate-y-full');
    document.getElementById('audioToggleTab').textContent = '🎵 ▼';
}

function toggleAudioPanel() {
    audioVisible = !audioVisible;
    document.getElementById('audioPlayer').classList.toggle('translate-y-full', !audioVisible);
    document.getElementById('audioToggleTab').textContent = audioVisible ? '🎵 ▼' : '🎵 ▲';
    if (audioVisible) initAudio();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    const tag = document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

    switch (e.code) {
        case 'Space':
            e.preventDefault();
            toggleAudio();
            showAudioHint('play/pause');
            break;
        case 'KeyM':
            toggleMute();
            showAudioHint('mute');
            break;
        case 'ArrowUp':
            e.preventDefault();
            changeVolume(0.1);
            showAudioHint('volume +');
            break;
        case 'ArrowDown':
            e.preventDefault();
            changeVolume(-0.1);
            showAudioHint('volume -');
            break;
        case 'KeyR':
            restartAudio();
            showAudioHint('restart');
            break;
    }
});

let hintTimer = null;
function showAudioHint(label) {
    const el = document.getElementById('audioHint');
    el.textContent = '🎵 ' + label;
    el.classList.remove('opacity-0');
    el.classList.add('opacity-100');
    clearTimeout(hintTimer);
    hintTimer = setTimeout(() => {
        el.classList.remove('opacity-100');
        el.classList.add('opacity-0');
    }, 1200);
}

document.addEventListener('DOMContentLoaded', function() {
    const isNewRound = sessionStorage.getItem('audioAutoplay') === '1';
    sessionStorage.removeItem('audioAutoplay');

    let restore = null;
    if (!isNewRound) {
        try {
            const saved = JSON.parse(sessionStorage.getItem(AUDIO_STATE_KEY) || 'null');
            if (saved && saved.round === ROUND_NUMBER) restore = saved;
        } catch (_) {}
    }
    sessionStorage.removeItem(AUDIO_STATE_KEY);

    initAudio(restore);

    if (restore && restore.paused) {
        // Аудио было на паузе — не возобновляем
    } else {
        audio.play().catch(() => {});
    }
});
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
