-- PostgreSQL
-- Upstream LLM providers we proxy to. Platform providers have iam_account_id NULL (visible to all accounts, managed by admins).

CREATE SEQUENCE IF NOT EXISTS ai_llm_providers_id_seq;

CREATE TABLE ai_llm_providers (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_providers_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    name                text NOT NULL, -- Human readable provider name.
    slug                text NOT NULL, -- Short identifier used in "provider/model" strings.
    driver              text NOT NULL DEFAULT 'openai_compatible', -- openai_compatible | anthropic
    base_url            text NOT NULL, -- Upstream API base url.
    api_key_encrypted   text, -- AES-256-GCM encrypted upstream key (never exposed).
    is_active           boolean NOT NULL DEFAULT true,
    config              json, -- Extra driver config (headers, timeouts, ...).
    iam_account_id      bigint, -- NULL for platform-wide providers.
    iam_user_id         bigint,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT ai_llm_providers_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_providers_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_providers_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE UNIQUE INDEX uq_ai_llm_providers_slug ON ai_llm_providers (COALESCE(iam_account_id, 0), slug) WHERE deleted_at IS NULL;
CREATE INDEX idx_ai_llm_providers_uuid ON ai_llm_providers USING btree (uuid);
CREATE INDEX idx_ai_llm_providers_iam_account ON ai_llm_providers USING btree (iam_account_id);
