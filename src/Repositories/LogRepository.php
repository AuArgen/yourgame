<?php

namespace App\Repositories;

use App\Database;
use PDO;

class LogRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function logAction($sessionId, $questionId, $participantId, $action, $pointsChanged) {
        $stmt = $this->db->prepare("
            INSERT INTO game_logs (session_id, question_id, participant_id, action, points_changed)
            VALUES (:session_id, :question_id, :participant_id, :action, :points_changed)
        ");
        return $stmt->execute([
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'participant_id' => $participantId,
            'action' => $action,
            'points_changed' => $pointsChanged
        ]);
    }

    public function getLogsBySession($sessionId) {
        $stmt = $this->db->prepare("
            SELECT l.*, p.name as participant_name, q.points as question_points, q.question_text
            FROM game_logs l
            LEFT JOIN participants p ON l.participant_id = p.id
            JOIN questions q ON l.question_id = q.id
            WHERE l.session_id = :session_id 
            ORDER BY l.created_at ASC
        ");
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnsweredQuestions($sessionId) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT question_id 
            FROM game_logs 
            WHERE session_id = :session_id AND action IN ('answered_correct', 'skipped')
        ");
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
