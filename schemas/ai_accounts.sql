-- PostgreSQL
-- Per-account AI marketplace profile, extending iam_accounts with usage and cost aggregates.

CREATE TABLE ai_accounts (
    id              bigint NOT NULL DEFAULT nextval('ai_accounts_id_seq'::regclass),
    uuid            uuid NOT NULL DEFAULT gen_random_uuid(),
    iam_account_id  bigint NOT NULL,
    total_runs      integer NOT NULL DEFAULT 0, -- Lifetime total number of ai_runs for this account.
    total_cost      numeric(14,6) NOT NULL DEFAULT 0, -- Lifetime total cost of ai_runs for this account.
    configuration   json, -- Account-level AI configuration overrides.
    is_active       boolean NOT NULL DEFAULT true,
    created_at      timestamp with time zone NOT NULL DEFAULT now(),
    updated_at      timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at      timestamp with time zone,
    CONSTRAINT ai_accounts_pkey PRIMARY KEY (id),
    CONSTRAINT ai_accounts_iam_account_id_key UNIQUE (iam_account_id)
);

CREATE INDEX idx_ai_accounts_iam_account ON public.ai_accounts USING btree (iam_account_id);
