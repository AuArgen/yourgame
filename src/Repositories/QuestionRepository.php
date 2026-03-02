<?php

namespace App\Repositories;

use App\Database;
use PDO;

class QuestionRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":$field"; }, $fields);
        
        $sql = "INSERT INTO questions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $stmt->fetchColumn();
    }

    public function findByCategoryId($categoryId) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE category_id = :category_id ORDER BY points ASC, sort_order ASC");
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = "$field = :$field";
        }
        $data['id'] = $id;
        
        $sql = "UPDATE questions SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getFullGameData($gameId) {
        $stmt = $this->db->prepare("
            SELECT 
                c.id as category_id, c.name as category_name, c.sort_order as cat_sort,
                q.id as question_id, q.question_text, q.answer_text, q.points, 
                q.is_cat_in_bag, q.time_limit, q.image_url, q.video_url, q.sort_order as q_sort
            FROM categories c
            LEFT JOIN questions q ON c.id = q.category_id
            WHERE c.game_id = :game_id
            ORDER BY c.sort_order ASC, c.id ASC, q.points ASC, q.sort_order ASC
        ");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
