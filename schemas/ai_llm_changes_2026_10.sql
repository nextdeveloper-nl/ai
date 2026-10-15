-- PostgreSQL
-- Changes for the LLM proxy panel features. Run once on databases that already have the ai_llm_* tables
-- (the CREATE TABLE files in this folder already contain these columns for fresh installs).

-- Alias extras: description, fallbacks, per-alias defaults, per-alias monthly spend limit.
ALTER TABLE ai_llm_aliases
    ADD COLUMN IF NOT EXISTS description               text,
    ADD COLUMN IF NOT EXISTS fallbacks                 json, -- Ordered list of {"provider": "slug", "model": "model_id"}, tried after the primary target.
    ADD COLUMN IF NOT EXISTS fallback_on_error         boolean NOT NULL DEFAULT true,  -- Upstream error / 5xx / unreachable.
    ADD COLUMN IF NOT EXISTS fallback_on_rate_limit    boolean NOT NULL DEFAULT true,  -- Upstream 429.
    ADD COLUMN IF NOT EXISTS fallback_on_timeout       boolean NOT NULL DEFAULT false, -- Upstream slower than fallback_timeout_seconds (non-stream calls).
    ADD COLUMN IF NOT EXISTS fallback_timeout_seconds  integer,
    ADD COLUMN IF NOT EXISTS temperature               numeric(4,2), -- Default applied when the request omits it.
    ADD COLUMN IF NOT EXISTS max_tokens                integer,      -- Default applied when the request omits it.
    ADD COLUMN IF NOT EXISTS system_prompt             text,         -- Prepended as a system message when set.
    ADD COLUMN IF NOT EXISTS monthly_spend_limit       numeric(14,6); -- USD per calendar month, NULL = no limit.

-- Usage log extras: which alias was used, whether a fallback served the call, and the attempts made.
ALTER TABLE ai_llm_usage_logs
    ADD COLUMN IF NOT EXISTS alias                text,
    ADD COLUMN IF NOT EXISTS served_by_fallback   boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS attempts             json; -- [{"provider","model","status","status_code","error","latency_ms"}]

CREATE INDEX IF NOT EXISTS idx_ai_llm_usage_logs_account_created ON ai_llm_usage_logs USING btree (iam_account_id, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_llm_usage_logs_alias ON ai_llm_usage_logs USING btree (iam_account_id, alias);

-- Change history of aliases ("Primary changed from X to Y by <user>").
CREATE SEQUENCE IF NOT EXISTS ai_llm_alias_history_id_seq;

CREATE TABLE IF NOT EXISTS ai_llm_alias_history (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_alias_history_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_llm_alias_id     bigint NOT NULL,
    alias               text NOT NULL, -- Alias name at the time of the change.
    action              text NOT NULL, -- created | updated | deleted
    changes             json, -- {"field": {"from": x, "to": y}}
    iam_account_id      bigint NOT NULL,
    iam_user_id         bigint,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT ai_llm_alias_history_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_alias_history_alias_fkey FOREIGN KEY (ai_llm_alias_id) REFERENCES ai_llm_aliases(id),
    CONSTRAINT ai_llm_alias_history_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_alias_history_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE INDEX IF NOT EXISTS idx_ai_llm_alias_history_alias ON ai_llm_alias_history USING btree (ai_llm_alias_id, created_at);

-- Account level monthly spend limit.
CREATE SEQUENCE IF NOT EXISTS ai_llm_account_limits_id_seq;

CREATE TABLE IF NOT EXISTS ai_llm_account_limits (
    id                      bigint NOT NULL DEFAULT nextval('ai_llm_account_limits_id_seq'::regclass),
    iam_account_id          bigint NOT NULL,
    monthly_spend_limit     numeric(14,6), -- USD per calendar month, NULL = no limit.
    iam_user_id             bigint, -- Who set it last.
    created_at              timestamp with time zone NOT NULL DEFAULT now(),
    updated_at              timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT ai_llm_account_limits_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_account_limits_account_key UNIQUE (iam_account_id),
    CONSTRAINT ai_llm_account_limits_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_account_limits_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);
