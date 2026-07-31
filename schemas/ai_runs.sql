-- PostgreSQL
-- Stores AI runs for AI assistance in the system.

CREATE TABLE ai_runs (
    id              bigint NOT NULL DEFAULT nextval('ai_runs_id_seq'::regclass),
    uuid            uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_agent_id     bigint, -- Reference to the AI agent that generated the run.
    model           text NOT NULL, -- Model used for the AI run.
    input           json, -- Input data for the AI run.
    output          text, -- Output of the AI run.
    parsed_output   json, -- Parsed output of the AI run.
    status          text NOT NULL DEFAULT 'pending'::text, -- Status of the AI run.
    status_code     integer, -- HTTP status code of the AI run.
    input_tokens    integer NOT NULL DEFAULT 0, -- Number of tokens used as input to the AI run.
    output_tokens   integer NOT NULL DEFAULT 0, -- Number of tokens generated as output from the AI run.
    cost            numeric(10,6) NOT NULL DEFAULT 0, -- Cost of the AI run.
    metadata        json, -- Additional metadata associated with the AI run.
    duration_ms     integer NOT NULL DEFAULT 0, -- Duration of the AI run in milliseconds.
    error_message   text, -- Error message if the AI run failed.
    iam_user_id     bigint,
    iam_account_id  bigint,
    created_at      timestamp with time zone NOT NULL DEFAULT now(),
    updated_at      timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at      timestamp with time zone,
    CONSTRAINT ai_runs_ai_agent_id_fkey FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    CONSTRAINT ai_runs_pkey PRIMARY KEY (id)
);

CREATE INDEX idx_ai_runs_ai_agent_id ON public.ai_runs USING btree (ai_agent_id);
CREATE INDEX idx_ai_runs_created_at ON public.ai_runs USING btree (created_at);
CREATE INDEX idx_ai_runs_iam_account ON public.ai_runs USING btree (iam_account_id);
CREATE INDEX idx_ai_runs_model ON public.ai_runs USING btree (model);
CREATE INDEX idx_ai_runs_status ON public.ai_runs USING btree (status);
CREATE INDEX idx_ai_runs_uuid ON public.ai_runs USING btree (uuid);
