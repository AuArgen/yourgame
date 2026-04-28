-- Migration: add explicit special question type without complicating normal questions
-- Run once on existing installations

ALTER TABLE questions
ADD COLUMN IF NOT EXISTS special_type VARCHAR(50) NOT NULL DEFAULT 'normal';

UPDATE questions
SET special_type = 'cat_choose_player'
WHERE COALESCE(is_cat_in_bag, FALSE) = TRUE
  AND (special_type IS NULL OR special_type = '' OR special_type = 'normal');
