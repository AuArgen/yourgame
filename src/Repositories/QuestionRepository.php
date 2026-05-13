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

        foreach ($data as $field => $value) {
            if (is_null($value)) {
                $stmt->bindValue(":$field", null, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue(":$field", $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue(":$field", $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$field", $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();
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

        $sql = "UPDATE questions SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        foreach ($data as $field => $value) {
            if (is_null($value)) {
                $stmt->bindValue(":$field", null, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue(":$field", $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue(":$field", $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$field", $value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // Returns all rounds → categories → questions for a game (used in editor)
    public function getFullGameData($gameId) {
        $stmt = $this->db->prepare("
            SELECT
                r.id as round_id, r.name as round_name, r.sort_order as round_sort,
                c.id as category_id, c.name as category_name, c.sort_order as cat_sort,
                q.id as question_id, q.question_text, q.answer_text, q.points,
                q.is_cat_in_bag, q.special_type, q.time_limit, q.image_url, q.answer_image_url, q.video_url, q.sort_order as q_sort
            FROM rounds r
            JOIN categories c ON c.round_id = r.id
            LEFT JOIN questions q ON c.id = q.category_id
            WHERE r.game_id = :game_id
            ORDER BY r.sort_order ASC, r.id ASC, c.sort_order ASC, c.id ASC, q.points ASC, q.sort_order ASC
        ");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Returns categories → questions for a single round (used during gameplay)
    public function getFullRoundData($roundId) {
        $stmt = $this->db->prepare("
            SELECT
                c.id as category_id, c.name as category_name, c.sort_order as cat_sort,
                q.id as question_id, q.question_text, q.answer_text, q.points,
                q.is_cat_in_bag, q.special_type, q.time_limit, q.image_url, q.answer_image_url, q.video_url, q.sort_order as q_sort
            FROM categories c
            LEFT JOIN questions q ON c.id = q.category_id
            WHERE c.round_id = :round_id
            ORDER BY c.sort_order ASC, c.id ASC, q.points ASC, q.sort_order ASC
        ");
        $stmt->execute(['round_id' => $roundId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
