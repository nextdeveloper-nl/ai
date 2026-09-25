-- PostgreSQL
-- AI Assistants foundation: helpers become assistants (platform or customer-made, prompt in DB),
-- runs become the metering record, plus daily rollup and alert dedupe tables.
-- Apply once on an existing database. Idempotent (IF NOT EXISTS everywhere).

BEGIN;

-- ---------------------------------------------------------------------------
-- ai_available_helpers: assistant catalog (platform + customer-made)
-- ---------------------------------------------------------------------------
ALTER TABLE ai_available_helpers
    ADD COLUMN IF NOT EXISTS slug                 text,
    ADD COLUMN IF NOT EXISTS color                text,
    ADD COLUMN IF NOT EXISTS default_model        text,
    ADD COLUMN IF NOT EXISTS allow_model_choice   boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS is_free              boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS handler_type         text    NOT NULL DEFAULT 'class',
    ADD COLUMN IF NOT EXISTS iam_account_id       bigint,
    ADD COLUMN IF NOT EXISTS iam_user_id          bigint,
    ADD COLUMN IF NOT EXISTS version              integer NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS system_prompt        text,
    ADD COLUMN IF NOT EXISTS user_prompt_template text,
    ADD COLUMN IF NOT EXISTS input_schema         json,
    ADD COLUMN IF NOT EXISTS response_format      text    NOT NULL DEFAULT 'json',
    ADD COLUMN IF NOT EXISTS response_schema      json,
    ADD COLUMN IF NOT EXISTS temperature          real    NOT NULL DEFAULT 0.7,
    ADD COLUMN IF NOT EXISTS default_max_tokens   integer;

COMMENT ON COLUMN ai_available_helpers.slug IS 'URL-safe identifier, unique per owner (platform or account).';
COMMENT ON COLUMN ai_available_helpers.default_model IS 'LiteLLM model name used when the account has not chosen one.';
COMMENT ON COLUMN ai_available_helpers.allow_model_choice IS 'Whether accounts may override default_model.';
COMMENT ON COLUMN ai_available_helpers.is_free IS 'Platform absorbs token cost; runs are never billed.';
COMMENT ON COLUMN ai_available_helpers.handler_type IS 'class = PHP handler in app/AI, prompt = prompt stored on this row.';
COMMENT ON COLUMN ai_available_helpers.iam_account_id IS 'Owner account for customer-made assistants; NULL for platform assistants.';
COMMENT ON COLUMN ai_available_helpers.version IS 'Bumped on every prompt edit; copied to ai_runs.helper_version.';
COMMENT ON COLUMN ai_available_helpers.user_prompt_template IS 'User message template with {{field}} placeholders filled from input.';

ALTER TABLE ai_available_helpers
    ALTER COLUMN class DROP NOT NULL,
    ALTER COLUMN parameters SET DEFAULT '{}'::json;

ALTER TABLE ai_available_helpers
    DROP CONSTRAINT IF EXISTS ai_available_helpers_handler_type_check,
    ADD CONSTRAINT ai_available_helpers_handler_type_check CHECK (handler_type IN ('class', 'prompt')),
    DROP CONSTRAINT IF EXISTS ai_available_helpers_response_format_check,
    ADD CONSTRAINT ai_available_helpers_response_format_check CHECK (response_format IN ('text', 'json')),
    DROP CONSTRAINT IF EXISTS ai_available_helpers_class_or_prompt_check,
    ADD CONSTRAINT ai_available_helpers_class_or_prompt_check CHECK (
        (handler_type = 'class' AND class IS NOT NULL)
        OR (handler_type = 'prompt' AND system_prompt IS NOT NULL)
    );

-- Backfill slug for existing class helpers from their action.
UPDATE ai_available_helpers SET slug = action WHERE slug IS NULL;

ALTER TABLE ai_available_helpers ALTER COLUMN slug SET NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ai_available_helpers_platform_slug
    ON ai_available_helpers (slug) WHERE iam_account_id IS NULL AND deleted_at IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_ai_available_helpers_account_slug
    ON ai_available_helpers (iam_account_id, slug) WHERE iam_account_id IS NOT NULL AND deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_ai_available_helpers_iam_account
    ON ai_available_helpers (iam_account_id);

-- ---------------------------------------------------------------------------
-- ai_runs: metering record
-- ---------------------------------------------------------------------------
ALTER TABLE ai_runs
    ADD COLUMN IF NOT EXISTS ai_available_helper_id           bigint,
    ADD COLUMN IF NOT EXISTS helper_version                   integer,
    ADD COLUMN IF NOT EXISTS prompt_snapshot                  json,
    ADD COLUMN IF NOT EXISTS idempotency_key                  text,
    ADD COLUMN IF NOT EXISTS trigger_type                     text,
    ADD COLUMN IF NOT EXISTS trigger_id                       text,
    ADD COLUMN IF NOT EXISTS trigger_label                    text,
    ADD COLUMN IF NOT EXISTS billed                           boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS price_snapshot                   json,
    ADD COLUMN IF NOT EXISTS input_cost                       numeric(18,6) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS output_cost                      numeric(18,6) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_cost                       numeric(18,6) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS accounting_credit_transaction_id bigint,
    ADD COLUMN IF NOT EXISTS error_code                       text,
    ADD COLUMN IF NOT EXISTS started_at                       timestamp with time zone,
    ADD COLUMN IF NOT EXISTS finished_at                      timestamp with time zone;

COMMENT ON COLUMN ai_runs.prompt_snapshot IS 'Exact prompt that ran (system, rendered user, temperature, schema) or class + version.';
COMMENT ON COLUMN ai_runs.idempotency_key IS 'Caller-supplied key; a retry with the same key returns the original run and is not charged again.';
COMMENT ON COLUMN ai_runs.price_snapshot IS 'LiteLLM prices at run time: {model, input_per_1m, output_per_1m}.';
COMMENT ON COLUMN ai_runs.billed IS 'Whether total_cost was debited from the account credit.';

ALTER TABLE ai_runs
    DROP CONSTRAINT IF EXISTS ai_runs_ai_available_helper_id_fkey,
    ADD CONSTRAINT ai_runs_ai_available_helper_id_fkey
        FOREIGN KEY (ai_available_helper_id) REFERENCES ai_available_helpers(id) ON DELETE SET NULL,
    DROP CONSTRAINT IF EXISTS ai_runs_trigger_type_check,
    ADD CONSTRAINT ai_runs_trigger_type_check
        CHECK (trigger_type IS NULL OR trigger_type IN ('automation', 'api', 'manual'));

-- Backfill: link old runs to helpers by class, normalise status.
UPDATE ai_runs r
SET ai_available_helper_id = h.id
FROM ai_available_helpers h
WHERE r.ai_available_helper_id IS NULL
  AND h.class = (r.metadata ->> 'class');

UPDATE ai_runs
SET status      = 'success',
    billed      = true,
    total_cost  = cost,
    started_at  = created_at,
    finished_at = updated_at
WHERE status = 'completed';

UPDATE ai_runs
SET started_at  = created_at,
    finished_at = updated_at
WHERE status = 'failed' AND started_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ai_runs_account_idempotency_key
    ON ai_runs (iam_account_id, idempotency_key) WHERE idempotency_key IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_ai_runs_account_helper_created
    ON ai_runs (iam_account_id, ai_available_helper_id, created_at);

-- ---------------------------------------------------------------------------
-- New tables
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_usage_daily (
    id                      bigserial PRIMARY KEY,
    uuid                    uuid NOT NULL DEFAULT gen_random_uuid(),
    iam_account_id          bigint NOT NULL,
    ai_available_helper_id  bigint NOT NULL REFERENCES ai_available_helpers(id) ON DELETE CASCADE,
    date                    date NOT NULL,
    runs                    integer NOT NULL DEFAULT 0,
    failed_runs             integer NOT NULL DEFAULT 0,
    input_tokens            bigint NOT NULL DEFAULT 0,
    output_tokens           bigint NOT NULL DEFAULT 0,
    total_cost              numeric(18,6) NOT NULL DEFAULT 0,
    created_at              timestamp with time zone NOT NULL DEFAULT now(),
    updated_at              timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT uq_ai_usage_daily UNIQUE (iam_account_id, ai_available_helper_id, date)
);

CREATE INDEX IF NOT EXISTS idx_ai_usage_daily_account_date ON ai_usage_daily (iam_account_id, date);

CREATE TABLE IF NOT EXISTS ai_alert_events (
    id              bigserial PRIMARY KEY,
    uuid            uuid NOT NULL DEFAULT gen_random_uuid(),
    iam_account_id  bigint NOT NULL,
    scope           text NOT NULL CHECK (scope IN ('assistant', 'budget')),
    scope_id        bigint,
    period          text NOT NULL,
    pct             integer NOT NULL,
    created_at      timestamp with time zone NOT NULL DEFAULT now(),
    updated_at      timestamp with time zone NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_ai_alert_events
    ON ai_alert_events (iam_account_id, scope, COALESCE(scope_id, 0), period, pct);

COMMIT;
