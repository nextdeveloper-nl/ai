-- PostgreSQL
-- Account-defined model aliases. Targets are stored as literal strings on purpose (see LLMOcean incident 2026-08-17: FK-resolved targets became null).

CREATE SEQUENCE IF NOT EXISTS ai_llm_aliases_id_seq;

CREATE TABLE ai_llm_aliases (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_aliases_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    alias               text NOT NULL, -- Name callers send as "model".
    target_provider     text NOT NULL, -- Provider slug.
    target_model        text NOT NULL, -- Upstream model id.
    is_active           boolean NOT NULL DEFAULT true,
    description               text,
    fallbacks                 json, -- Ordered list of {"provider": "slug", "model": "model_id"}, tried after the primary target.
    fallback_on_error         boolean NOT NULL DEFAULT true,
    fallback_on_rate_limit    boolean NOT NULL DEFAULT true,
    fallback_on_timeout       boolean NOT NULL DEFAULT false,
    fallback_timeout_seconds  integer,
    temperature               numeric(4,2),
    max_tokens                integer,
    system_prompt             text,
    monthly_spend_limit       numeric(14,6),
    iam_account_id      bigint NOT NULL,
    iam_user_id         bigint,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT ai_llm_aliases_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_aliases_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_aliases_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE UNIQUE INDEX uq_ai_llm_aliases_account_alias ON ai_llm_aliases (iam_account_id, alias) WHERE deleted_at IS NULL;
CREATE INDEX idx_ai_llm_aliases_uuid ON ai_llm_aliases USING btree (uuid);
