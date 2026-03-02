<?php

namespace App\Repositories;

use App\Database;
use PDO;

class CategoryRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($gameId, $name, $sortOrder = 0) {
        $stmt = $this->db->prepare("INSERT INTO categories (game_id, name, sort_order) VALUES (:game_id, :name, :sort_order) RETURNING id");
        $stmt->execute([
            'game_id' => $gameId,
            'name' => $name,
            'sort_order' => $sortOrder
        ]);
        return $stmt->fetchColumn();
    }

    public function findByGameId($gameId) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE game_id = :game_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $sortOrder = null) {
        $sql = "UPDATE categories SET name = :name";
        $params = ['id' => $id, 'name' => $name];
        
        if ($sortOrder !== null) {
            $sql .= ", sort_order = :sort_order";
            $params['sort_order'] = $sortOrder;
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
