-- PostgreSQL
-- One row per proxied upstream call (a broadcast produces one row per target, sharing broadcast_id).

CREATE SEQUENCE IF NOT EXISTS ai_llm_usage_logs_id_seq;

CREATE TABLE ai_llm_usage_logs (
    id                              bigint NOT NULL DEFAULT nextval('ai_llm_usage_logs_id_seq'::regclass),
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    request_id                      text NOT NULL,
    broadcast_id                    text,
    ai_llm_api_key_id               bigint,
    ai_llm_provider_id              bigint,
    provider                        text, -- Provider slug actually used.
    model                           text, -- Upstream model actually used.
    requested_model                 text, -- What the caller sent (may be an alias).
    input_tokens                    integer NOT NULL DEFAULT 0,
    output_tokens                   integer NOT NULL DEFAULT 0,
    upstream_cost                   numeric(18,8), -- Provider cost in USD, NULL if model unpriced.
    cost                            numeric(18,8), -- Amount charged to the account (with markup).
    billed                          boolean NOT NULL DEFAULT false,
    accounting_credit_transaction_id bigint,
    status                          text NOT NULL DEFAULT 'pending', -- pending | success | failed
    status_code                     integer,
    latency_ms                      integer,
    ttft_ms                         integer,
    stream                          boolean NOT NULL DEFAULT false,
    error_message                   text,
    iam_account_id                  bigint NOT NULL,
    iam_user_id                     bigint,
    created_at                      timestamp with time zone NOT NULL DEFAULT now(),
    updated_at                      timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at                      timestamp with time zone,
    CONSTRAINT ai_llm_usage_logs_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_usage_logs_key_fkey FOREIGN KEY (ai_llm_api_key_id) REFERENCES ai_llm_api_keys(id) ON DELETE SET NULL,
    CONSTRAINT ai_llm_usage_logs_provider_fkey FOREIGN KEY (ai_llm_provider_id) REFERENCES ai_llm_providers(id) ON DELETE SET NULL,
    CONSTRAINT ai_llm_usage_logs_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_usage_logs_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE INDEX idx_ai_llm_usage_logs_uuid ON ai_llm_usage_logs USING btree (uuid);
CREATE INDEX idx_ai_llm_usage_logs_account_created ON ai_llm_usage_logs USING btree (iam_account_id, created_at);
CREATE INDEX idx_ai_llm_usage_logs_request ON ai_llm_usage_logs USING btree (request_id);
CREATE INDEX idx_ai_llm_usage_logs_unbilled ON ai_llm_usage_logs USING btree (billed) WHERE billed = false AND status = 'success';
