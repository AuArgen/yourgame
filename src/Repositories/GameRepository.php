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
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title
        ]);
        return $stmt->fetchColumn();
    }

    public function createDemoGame($userId) {
        $gameId = $this->create($userId, 'Менин биринчи оюнум (Демо)');
        
        $catRepo = new CategoryRepository();
        $qRepo = new QuestionRepository();

        // Категория 1: Кыргызстан
        $cat1Id = $catRepo->create($gameId, 'Кыргызстан', 1);
        
        $qRepo->create([
            'category_id' => $cat1Id,
            'question_text' => 'Кыргызстандын борбор шаары кайсы?',
            'answer_text' => 'Бишкек',
            'points' => 100,
            'time_limit' => 20,
            'sort_order' => 1
        ]);

        $qRepo->create([
            'category_id' => $cat1Id,
            'question_text' => 'Кыргызстандын эң бийик чокусу кандай аталат?',
            'answer_text' => 'Жеңиш чокусу',
            'points' => 200,
            'time_limit' => 30,
            'sort_order' => 2
        ]);

        $qRepo->create([
            'category_id' => $cat1Id,
            'question_text' => 'Дүйнөдөгү эң чоң жаңгак токою Кыргызстандын кайсы жеринде жайгашкан?',
            'answer_text' => 'Арстанбап',
            'points' => 300,
            'time_limit' => 30,
            'sort_order' => 3
        ]);

        $qRepo->create([
            'category_id' => $cat1Id,
            'question_text' => 'Кыргыз Республикасынын туусу качан кабыл алынган?',
            'answer_text' => '1992-жылы 3-мартта',
            'points' => 400,
            'time_limit' => 30,
            'sort_order' => 4
        ]);

        $qRepo->create([
            'category_id' => $cat1Id,
            'question_text' => 'Манас эпосундагы эң акыркы бөлүмүнүн башкы каарманы ким?',
            'answer_text' => 'Сейтек',
            'points' => 500,
            'time_limit' => 40,
            'sort_order' => 5
        ]);

        // Категория 2: Спорт
        $cat2Id = $catRepo->create($gameId, 'Спорт', 2);

        $qRepo->create([
            'category_id' => $cat2Id,
            'question_text' => 'Футбол боюнча 2022-жылкы дүйнө чемпиону кайсы өлкө болгон?',
            'answer_text' => 'Аргентина',
            'points' => 100,
            'time_limit' => 20,
            'sort_order' => 1
        ]);

        $qRepo->create([
            'category_id' => $cat2Id,
            'question_text' => 'Олимпиада оюндары канча жылда бир өткөрүлөт?',
            'answer_text' => '4 жылда бир',
            'points' => 200,
            'time_limit' => 20,
            'sort_order' => 2
        ]);

        $qRepo->create([
            'category_id' => $cat2Id,
            'question_text' => 'Кыргызстандан чыккан биринчи Олимпиада чемпиону ким?',
            'answer_text' => 'Каныбек Осмоналиев (Оор атлетика)',
            'points' => 300,
            'time_limit' => 30,
            'sort_order' => 3
        ]);

        $qRepo->create([
            'category_id' => $cat2Id,
            'question_text' => 'Эрежесиз мушташ (UFC) боюнча кыргызстандык эң белгилүү аял мушкер ким?',
            'answer_text' => 'Валентина Шевченко',
            'points' => 400,
            'time_limit' => 20,
            'sort_order' => 4
        ]);

        $qRepo->create([
            'category_id' => $cat2Id,
            'question_text' => 'Дүйнөлүк көчмөндөр оюндары биринчи жолу кайсы жылы жана кайда өткөн?',
            'answer_text' => '2014-жылы Чолпон-Ата шаарында',
            'points' => 500,
            'time_limit' => 40,
            'sort_order' => 5
        ]);

        // Категория 3: Мышык (Кот в мешке)
        $cat3Id = $catRepo->create($gameId, 'Мышык (Сюрприз)', 3);

        $qRepo->create([
            'category_id' => $cat3Id,
            'question_text' => 'Мышыктардын кайсы органы аларга караңгыда жакшы көрүүгө жардам берет?',
            'answer_text' => 'Көздөрү (өзгөчө торчо катмары - Tapetum lucidum)',
            'points' => 200,
            'time_limit' => 20,
            'is_cat_in_bag' => true,
            'sort_order' => 1
        ]);

        $qRepo->create([
            'category_id' => $cat3Id,
            'question_text' => 'Дүйнөдөгү эң чоң мышык тукуму (жапайы эмес) кандай аталат?',
            'answer_text' => 'Мейн-кун',
            'points' => 400,
            'time_limit' => 30,
            'is_cat_in_bag' => true,
            'sort_order' => 2
        ]);

        $qRepo->create([
            'category_id' => $cat3Id,
            'question_text' => 'Мышыктардын орточо өмүрү канча жыл?',
            'answer_text' => '12-18 жыл',
            'points' => 600,
            'time_limit' => 30,
            'is_cat_in_bag' => true,
            'sort_order' => 3
        ]);

        return $gameId;
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT g.*, 
            (SELECT COUNT(*) FROM categories WHERE game_id = g.id) as categories_count,
            (SELECT COUNT(*) FROM questions q JOIN categories c ON q.category_id = c.id WHERE c.game_id = g.id) as questions_count
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
        return $stmt->execute([
            'id' => $id,
            'title' => $title
        ]);
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM games WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            // Эгерде бул оюнга байланыштуу активдүү сессиялар болсо, өчүрүүгө болбойт
            // Бирок init.sql'де ON DELETE CASCADE бар, демек баары өчүрүлүшү керек.
            // Эгерде башка ката болсо (мисалы, базага туташуу), аны билдирүү керек.
            error_log("Game deletion error: " . $e->getMessage());
            return false;
        }
    }
}
