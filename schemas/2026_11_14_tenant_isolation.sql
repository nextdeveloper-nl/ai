-- PostgreSQL
-- Tenant isolation (2026-11-14). Run once, in one transaction. Safe to re-run.
-- Checked read-only against the live data first: 3078 conversations (all with a session that has an account),
-- 0 tool assignments, 1 agent (has an account), 0 chat messages.

BEGIN;

-- 1. Conversations get their own owner columns, copied from the session they belong to.
ALTER TABLE ai_conversations
    ADD COLUMN IF NOT EXISTS iam_account_id bigint,
    ADD COLUMN IF NOT EXISTS iam_user_id    bigint;

UPDATE ai_conversations c
SET    iam_account_id = s.iam_account_id,
       iam_user_id    = s.iam_user_id
FROM   ai_sessions s
WHERE  s.id = c.ai_session_id
  AND  (c.iam_account_id IS NULL OR c.iam_user_id IS NULL);

CREATE INDEX IF NOT EXISTS idx_ai_conversations_iam_account ON ai_conversations (iam_account_id) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_ai_conversations_session     ON ai_conversations (ai_session_id);

-- 2. Tool assignments get the account of their agent.
ALTER TABLE ai_agent_tool_assignments
    ADD COLUMN IF NOT EXISTS iam_account_id bigint;

UPDATE ai_agent_tool_assignments t
SET    iam_account_id = a.iam_account_id
FROM   ai_agents a
WHERE  a.id = t.ai_agent_id
  AND  t.iam_account_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_ai_agent_tool_assignments_account ON ai_agent_tool_assignments (iam_account_id);

-- 3. Agents: every agent belongs to an account, and a slug only has to be unique inside its account.
--    Both old constraints are the same global UNIQUE (slug), so tenants collided on slugs and could probe for them.
ALTER TABLE ai_agents ALTER COLUMN iam_account_id SET NOT NULL;

ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS ai_agents_slug_key;
ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS ai_agents_slug_unique;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ai_agents_account_slug ON ai_agents (iam_account_id, slug) WHERE deleted_at IS NULL;

COMMIT;

-- Not run here, on purpose:
--  * ai_conversations.iam_account_id / iam_user_id stay nullable until every writer stamps them
--    (the Conversations observer does not yet); then: ALTER TABLE ai_conversations ALTER COLUMN iam_account_id SET NOT NULL;
--  * ai_llm_chat_messages gets no owner columns: it is only reached through its chat (ai_llm_chats has both).
--  * ai_llm_aliases.iam_account_id is nullable in the live database (13 platform aliases) and its unique index uses
--    COALESCE(iam_account_id, 0). That is correct; only schemas/ai_llm_aliases.sql was out of date.
