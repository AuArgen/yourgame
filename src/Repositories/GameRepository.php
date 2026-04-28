<?php

namespace App\Repositories;

use App\Database;
use PDO;

class GameRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($userId, $title) {
        $stmt = $this->db->prepare("INSERT INTO games (created_by, title) VALUES (:user_id, :title) RETURNING id");
        $stmt->execute(['user_id' => $userId, 'title' => $title]);
        return $stmt->fetchColumn();
    }

    public function createDemoGame($userId) {
        $gameId = $this->create($userId, 'Менин биринчи оюнум (Демо)');

        $roundRepo = new RoundRepository();
        $catRepo = new CategoryRepository();
        $qRepo = new QuestionRepository();

        // --- Раунд 1 ---
        $round1Id = $roundRepo->create($gameId, '1-раунд', 0);

        $cat1Id = $catRepo->create($round1Id, 'Кыргызстан', 1);

        $qRepo->create(['category_id' => $cat1Id, 'question_text' => 'Кыргызстандын борбор шаары кайсы?', 'answer_text' => 'Бишкек', 'points' => 100, 'time_limit' => 20, 'sort_order' => 1]);
        $qRepo->create(['category_id' => $cat1Id, 'question_text' => 'Кыргызстандын эң бийик чокусу кандай аталат?', 'answer_text' => 'Жеңиш чокусу', 'points' => 200, 'time_limit' => 30, 'sort_order' => 2]);
        $qRepo->create(['category_id' => $cat1Id, 'question_text' => 'Дүйнөдөгү эң чоң жаңгак токою Кыргызстандын кайсы жеринде жайгашкан?', 'answer_text' => 'Арстанбап', 'points' => 300, 'time_limit' => 30, 'sort_order' => 3]);
        $qRepo->create(['category_id' => $cat1Id, 'question_text' => 'Кыргыз Республикасынын туусу качан кабыл алынган?', 'answer_text' => '1992-жылы 3-мартта', 'points' => 400, 'time_limit' => 30, 'sort_order' => 4]);
        $qRepo->create(['category_id' => $cat1Id, 'question_text' => 'Манас эпосундагы эң акыркы бөлүмүнүн башкы каарманы ким?', 'answer_text' => 'Сейтек', 'points' => 500, 'time_limit' => 40, 'sort_order' => 5]);

        $cat2Id = $catRepo->create($round1Id, 'Спорт', 2);

        $qRepo->create(['category_id' => $cat2Id, 'question_text' => 'Футбол боюнча 2022-жылкы дүйнө чемпиону кайсы өлкө болгон?', 'answer_text' => 'Аргентина', 'points' => 100, 'time_limit' => 20, 'sort_order' => 1]);
        $qRepo->create(['category_id' => $cat2Id, 'question_text' => 'Олимпиада оюндары канча жылда бир өткөрүлөт?', 'answer_text' => '4 жылда бир', 'points' => 200, 'time_limit' => 20, 'sort_order' => 2]);
        $qRepo->create(['category_id' => $cat2Id, 'question_text' => 'Кыргызстандан чыккан биринчи Олимпиада чемпиону ким?', 'answer_text' => 'Каныбек Осмоналиев (Оор атлетика)', 'points' => 300, 'time_limit' => 30, 'sort_order' => 3]);
        $qRepo->create(['category_id' => $cat2Id, 'question_text' => 'Эрежесиз мушташ (UFC) боюнча кыргызстандык эң белгилүү аял мушкер ким?', 'answer_text' => 'Валентина Шевченко', 'points' => 400, 'time_limit' => 20, 'sort_order' => 4]);
        $qRepo->create(['category_id' => $cat2Id, 'question_text' => 'Дүйнөлүк көчмөндөр оюндары биринчи жолу кайсы жылы жана кайда өткөн?', 'answer_text' => '2014-жылы Чолпон-Ата шаарында', 'points' => 500, 'time_limit' => 40, 'sort_order' => 5]);

        // --- Раунд 2 ---
        $round2Id = $roundRepo->create($gameId, '2-раунд', 1);

        $cat3Id = $catRepo->create($round2Id, 'Мышык (Сюрприз)', 1);

        $qRepo->create(['category_id' => $cat3Id, 'question_text' => 'Мышыктардын кайсы органы аларга караңгыда жакшы көрүүгө жардам берет?', 'answer_text' => 'Көздөрү (өзгөчө торчо катмары - Tapetum lucidum)', 'points' => 200, 'time_limit' => 20, 'is_cat_in_bag' => true, 'special_type' => 'cat_choose_player', 'sort_order' => 1]);
        $qRepo->create(['category_id' => $cat3Id, 'question_text' => 'Дүйнөдөгү эң чоң мышык тукуму (жапайы эмес) кандай аталат?', 'answer_text' => 'Мейн-кун', 'points' => 400, 'time_limit' => 30, 'is_cat_in_bag' => true, 'special_type' => 'cat_choose_player', 'sort_order' => 2]);
        $qRepo->create(['category_id' => $cat3Id, 'question_text' => 'Мышыктардын орточо өмүрү канча жыл?', 'answer_text' => '12-18 жыл', 'points' => 600, 'time_limit' => 30, 'is_cat_in_bag' => true, 'special_type' => 'cat_choose_player', 'sort_order' => 3]);

        $cat4Id = $catRepo->create($round2Id, 'Жалпы билим', 2);

        $qRepo->create(['category_id' => $cat4Id, 'question_text' => 'Дүйнөдөгү эң узун дарыя кайсы?', 'answer_text' => 'Нил дарыясы', 'points' => 100, 'time_limit' => 20, 'sort_order' => 1]);
        $qRepo->create(['category_id' => $cat4Id, 'question_text' => 'Суунун химиялык формуласы кандай?', 'answer_text' => 'H₂O', 'points' => 200, 'time_limit' => 15, 'sort_order' => 2]);
        $qRepo->create(['category_id' => $cat4Id, 'question_text' => 'Адамдын денесинде канча сөөк бар?', 'answer_text' => '206 сөөк', 'points' => 300, 'time_limit' => 30, 'sort_order' => 3]);
        $qRepo->create(['category_id' => $cat4Id, 'question_text' => 'Дүйнөдөгү эң чоң мухит кайсы?', 'answer_text' => 'Тынч мухит (Тихий океан)', 'points' => 400, 'time_limit' => 20, 'sort_order' => 4]);
        $qRepo->create(['category_id' => $cat4Id, 'question_text' => 'Жердин диаметри канча километр?', 'answer_text' => 'болжол менен 12 742 км', 'points' => 500, 'time_limit' => 40, 'sort_order' => 5]);

        return $gameId;
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT g.*,
            (SELECT COUNT(*) FROM rounds WHERE game_id = g.id) as rounds_count,
            (SELECT COUNT(*) FROM categories c JOIN rounds r ON c.round_id = r.id WHERE r.game_id = g.id) as categories_count,
            (SELECT COUNT(*) FROM questions q JOIN categories c ON q.category_id = c.id JOIN rounds r ON c.round_id = r.id WHERE r.game_id = g.id) as questions_count
            FROM games g
            WHERE g.created_by = :user_id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM games WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $title) {
        $stmt = $this->db->prepare("UPDATE games SET title = :title WHERE id = :id");
        return $stmt->execute(['id' => $id, 'title' => $title]);
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM games WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            error_log("Game deletion error: " . $e->getMessage());
            return false;
        }
    }
}
