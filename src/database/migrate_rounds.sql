-- Migration: Add rounds support to existing database
-- Run once on existing installations

-- 1. Add rounds table
CREATE TABLE IF NOT EXISTS rounds (
    id SERIAL PRIMARY KEY,
    game_id INTEGER REFERENCES games(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL DEFAULT '1-раунд',
    sort_order INTEGER DEFAULT 0
);

-- 2. Create a default round for every existing game
INSERT INTO rounds (game_id, name, sort_order)
SELECT DISTINCT game_id, '1-раунд', 0 FROM categories;

-- 3. Add round_id column to categories
ALTER TABLE categories ADD COLUMN IF NOT EXISTS round_id INTEGER REFERENCES rounds(id) ON DELETE CASCADE;

-- 4. Assign each category to the default round of its game
UPDATE categories
SET round_id = rounds.id
FROM rounds
WHERE rounds.game_id = categories.game_id;

-- 5. Drop old game_id column from categories
ALTER TABLE categories DROP COLUMN IF EXISTS game_id;

-- 6. Add current_round_id to game_sessions
ALTER TABLE game_sessions ADD COLUMN IF NOT EXISTS current_round_id INTEGER REFERENCES rounds(id) ON DELETE SET NULL;

-- 7. Set current_round_id for existing active/waiting sessions
UPDATE game_sessions gs
SET current_round_id = (
    SELECT r.id FROM rounds r WHERE r.game_id = gs.game_id ORDER BY r.sort_order ASC, r.id ASC LIMIT 1
)
WHERE gs.current_round_id IS NULL AND gs.status != 'finished';
