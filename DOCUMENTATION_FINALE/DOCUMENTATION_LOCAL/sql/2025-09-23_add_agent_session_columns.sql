-- Ajout des colonnes de session pour agents_suzosky (idempotent)
ALTER TABLE agents_suzosky
  ADD COLUMN IF NOT EXISTS current_session_token VARCHAR(128) NULL,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL,
  ADD COLUMN IF NOT EXISTS last_login_user_agent VARCHAR(255) NULL;

-- Index utile pour recherche par token
CREATE INDEX IF NOT EXISTS idx_agents_session_token ON agents_suzosky (current_session_token);
