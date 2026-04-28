<?php

namespace App\Repositories;

use App\Database;
use PDO;

class RoundRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($gameId, $name, $sortOrder = 0) {
        $stmt = $this->db->prepare("INSERT INTO rounds (game_id, name, sort_order) VALUES (:game_id, :name, :sort_order) RETURNING id");
        $stmt->execute(['game_id' => $gameId, 'name' => $name, 'sort_order' => $sortOrder]);
        return $stmt->fetchColumn();
    }

    public function findByGameId($gameId) {
        $stmt = $this->db->prepare("SELECT * FROM rounds WHERE game_id = :game_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM rounds WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getFirstByGameId($gameId) {
        $stmt = $this->db->prepare("SELECT * FROM rounds WHERE game_id = :game_id ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNextRound($gameId, $currentRoundId) {
        $stmt = $this->db->prepare("
            SELECT * FROM rounds
            WHERE game_id = :game_id
              AND (sort_order, id) > (SELECT sort_order, id FROM rounds WHERE id = :round_id)
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute(['game_id' => $gameId, 'round_id' => $currentRoundId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name) {
        $stmt = $this->db->prepare("UPDATE rounds SET name = :name WHERE id = :id");
        return $stmt->execute(['id' => $id, 'name' => $name]);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM rounds WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
