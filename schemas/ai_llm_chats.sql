-- PostgreSQL
-- Server-side chat history of the logged-in users' LLM chat (POST /ai/llm/chat). Message text is stored encrypted
-- (APP_KEY). Whether a chat is kept is decided when it is created (request field "keep", else the account default in
-- ai_accounts.configuration -> llm_chat.keep_history). The storage fee is not charged here: the billing service reads
-- content_bytes per account and writes accounting_usage_events.

CREATE SEQUENCE IF NOT EXISTS ai_llm_chats_id_seq;

CREATE TABLE IF NOT EXISTS ai_llm_chats (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_chats_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    iam_user_id         bigint NOT NULL, -- Owner: other users of the account never see the chat.
    iam_account_id      bigint NOT NULL, -- Account that pays the storage.
    title               text,
    model               text, -- Public model id or alias, never an upstream id.
    system_encrypted    text, -- System prompt, encrypted.
    temperature         numeric(4,2),
    max_tokens          integer,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT ai_llm_chats_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_chats_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id),
    CONSTRAINT ai_llm_chats_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id)
);

CREATE INDEX IF NOT EXISTS idx_ai_llm_chats_uuid ON ai_llm_chats USING btree (uuid);
CREATE INDEX IF NOT EXISTS idx_ai_llm_chats_user_updated ON ai_llm_chats USING btree (iam_user_id, updated_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_ai_llm_chats_account ON ai_llm_chats USING btree (iam_account_id) WHERE deleted_at IS NULL;

CREATE SEQUENCE IF NOT EXISTS ai_llm_chat_messages_id_seq;

CREATE TABLE IF NOT EXISTS ai_llm_chat_messages (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_chat_messages_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_llm_chat_id      bigint NOT NULL,
    role                text NOT NULL, -- user | assistant
    content_encrypted   text NOT NULL,
    reasoning_encrypted text,
    meta                json, -- Whitelisted: model (public), requested, promptTokens, completionTokens, latencyMs, servedByFallback, cost, requestId, error (code only).
    position            integer NOT NULL, -- Order inside the chat.
    content_bytes       integer NOT NULL DEFAULT 0, -- Stored size (encrypted content + reasoning), what the billing service meters.
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT ai_llm_chat_messages_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_chat_messages_chat_fkey FOREIGN KEY (ai_llm_chat_id) REFERENCES ai_llm_chats(id) ON DELETE CASCADE,
    CONSTRAINT ai_llm_chat_messages_chat_position_key UNIQUE (ai_llm_chat_id, position)
);

CREATE INDEX IF NOT EXISTS idx_ai_llm_chat_messages_uuid ON ai_llm_chat_messages USING btree (uuid);
