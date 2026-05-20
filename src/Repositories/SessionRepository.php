<?php

namespace App\Repositories;

use App\Database;
use PDO;

class SessionRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($gameId, $hostId, $firstRoundId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO game_sessions (game_id, host_id, status, current_round_id)
            VALUES (:game_id, :host_id, 'waiting', :round_id)
            RETURNING id
        ");
        $stmt->execute(['game_id' => $gameId, 'host_id' => $hostId, 'round_id' => $firstRoundId]);
        return $stmt->fetchColumn();
    }

    public function findById($id) {
        $stmt = $this->db->prepare("
            SELECT gs.*, g.title as game_title
            FROM game_sessions gs
            JOIN games g ON gs.game_id = g.id
            WHERE gs.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE game_sessions SET status = :status WHERE id = :id");
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function updateCurrentRound($id, $roundId) {
        $stmt = $this->db->prepare("UPDATE game_sessions SET current_round_id = :round_id WHERE id = :id");
        return $stmt->execute(['id' => $id, 'round_id' => $roundId]);
    }

    public function setActiveQuestion($id, $questionId) {
        $stmt = $this->db->prepare("
            UPDATE game_sessions
            SET active_question_id = :question_id, cat_target_participant_id = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'question_id' => $questionId]);
    }

    public function assignCatTargetParticipant($id, $participantId) {
        $stmt = $this->db->prepare("
            UPDATE game_sessions
            SET cat_target_participant_id = :participant_id
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'participant_id' => $participantId]);
    }

    public function clearActiveQuestion($id) {
        $stmt = $this->db->prepare("
            UPDATE game_sessions
            SET active_question_id = NULL, cat_target_participant_id = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function setFinalCategory($id, $categoryId) {
        $stmt = $this->db->prepare("UPDATE game_sessions SET final_category_id = :cat_id WHERE id = :id");
        return $stmt->execute(['id' => $id, 'cat_id' => $categoryId]);
    }

    public function addParticipant($sessionId, $name) {
        $stmt = $this->db->prepare("INSERT INTO participants (session_id, name) VALUES (:session_id, :name) RETURNING id");
        $stmt->execute(['session_id' => $sessionId, 'name' => $name]);
        return $stmt->fetchColumn();
    }

    public function getParticipants($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM participants WHERE session_id = :session_id ORDER BY id ASC");
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateParticipantScore($participantId, $scoreChange) {
        $stmt = $this->db->prepare("UPDATE participants SET score = score + :change WHERE id = :id");
        return $stmt->execute(['id' => $participantId, 'change' => $scoreChange]);
    }

    public function getSessionHistory($hostId) {
        $stmt = $this->db->prepare("
            SELECT gs.*, g.title as game_title,
            (SELECT COUNT(*) FROM participants WHERE session_id = gs.id) as players_count,
            (SELECT name FROM participants WHERE session_id = gs.id ORDER BY score DESC LIMIT 1) as winner_name
            FROM game_sessions gs
            JOIN games g ON gs.game_id = g.id
            WHERE gs.host_id = :host_id
            ORDER BY gs.created_at DESC
        ");
        $stmt->execute(['host_id' => $hostId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSessionSummary($sessionId) {
        $participants = $this->getParticipants($sessionId);
        $stmt = $this->db->prepare("
            SELECT
                action,
                COUNT(*) as count,
                SUM(ABS(points_changed)) as points_total
            FROM game_logs
            WHERE session_id = :session_id
            GROUP BY action
        ");
        $stmt->execute(['session_id' => $sessionId]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'participants' => $participants,
            'stats' => $stats
        ];
    }
}
