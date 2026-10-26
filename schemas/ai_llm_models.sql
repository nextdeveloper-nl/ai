-- PostgreSQL
-- Models exposed by an upstream provider, with per-1M-token prices (USD) used for cost calculation.

CREATE SEQUENCE IF NOT EXISTS ai_llm_models_id_seq;

CREATE TABLE ai_llm_models (
    id                      bigint NOT NULL DEFAULT nextval('ai_llm_models_id_seq'::regclass),
    uuid                    uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_llm_provider_id      bigint NOT NULL,
    model_id                text NOT NULL, -- Upstream model id, e.g. gpt-4o.
    display_name            text,
    input_price_per_1m      numeric(14,6), -- Upstream cost per 1M input tokens. NULL = unpriced (charging will retry).
    output_price_per_1m     numeric(14,6),
    cache_read_price_per_1m numeric(14,6), -- Upstream cost per 1M cached input tokens read. NULL = billed as normal input.
    cache_write_price_per_1m numeric(14,6), -- Upstream cost per 1M input tokens written to the cache. NULL = billed as normal input.
    public_id               text, -- Name customers use. Offers of several providers sharing a public_id are the same model; the cheapest answers.
    context_window          integer,
    capabilities            json,
    is_active               boolean NOT NULL DEFAULT true,
    is_public               boolean NOT NULL DEFAULT false, -- Anybody may use the model, even when its provider belongs to an account. Customers pay for it.
    iam_account_id          bigint,
    iam_user_id             bigint,
    created_at              timestamp with time zone NOT NULL DEFAULT now(),
    updated_at              timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at              timestamp with time zone,
    CONSTRAINT ai_llm_models_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_models_provider_fkey FOREIGN KEY (ai_llm_provider_id) REFERENCES ai_llm_providers(id) ON DELETE CASCADE,
    CONSTRAINT ai_llm_models_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_models_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE UNIQUE INDEX uq_ai_llm_models_provider_model ON ai_llm_models (ai_llm_provider_id, model_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_ai_llm_models_uuid ON ai_llm_models USING btree (uuid);
CREATE INDEX idx_ai_llm_models_public_id ON ai_llm_models USING btree (public_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_ai_llm_models_provider ON ai_llm_models USING btree (ai_llm_provider_id);
