-- PostgreSQL
-- Caller API keys for the proxy. Only the sha256 hash is stored; plaintext is shown once at creation.

CREATE SEQUENCE IF NOT EXISTS ai_llm_api_keys_id_seq;

CREATE TABLE ai_llm_api_keys (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_api_keys_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    name                text NOT NULL,
    key_prefix          text NOT NULL, -- First chars of the key for display.
    key_hash            text NOT NULL, -- sha256 of the full key.
    status              text NOT NULL DEFAULT 'active', -- active | revoked
    expires_at          timestamp with time zone,
    last_used_at        timestamp with time zone,
    revoked_at          timestamp with time zone,
    iam_account_id      bigint NOT NULL,
    iam_user_id         bigint NOT NULL,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT ai_llm_api_keys_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_api_keys_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_api_keys_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE UNIQUE INDEX uq_ai_llm_api_keys_hash ON ai_llm_api_keys (key_hash);
CREATE INDEX idx_ai_llm_api_keys_uuid ON ai_llm_api_keys USING btree (uuid);
CREATE INDEX idx_ai_llm_api_keys_iam_account ON ai_llm_api_keys USING btree (iam_account_id);
