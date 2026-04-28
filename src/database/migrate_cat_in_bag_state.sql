-- Migration: add active question state for gameplay and "Cat in the Bag"
-- Run once on existing installations

ALTER TABLE game_sessions
ADD COLUMN IF NOT EXISTS active_question_id INTEGER REFERENCES questions(id) ON DELETE SET NULL;

ALTER TABLE game_sessions
ADD COLUMN IF NOT EXISTS cat_target_participant_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_game_sessions_cat_target_participant'
    ) THEN
        ALTER TABLE game_sessions
        ADD CONSTRAINT fk_game_sessions_cat_target_participant
        FOREIGN KEY (cat_target_participant_id) REFERENCES participants(id) ON DELETE SET NULL;
    END IF;
END $$;
