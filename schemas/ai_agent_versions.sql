-- PostgreSQL
-- Immutable snapshots of ai_agents config at each publish event.

CREATE TABLE ai_agent_versions (
    id               bigint NOT NULL DEFAULT nextval('ai_agent_versions_id_seq'::regclass),
    uuid             uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_agent_id      bigint NOT NULL,
    version          text NOT NULL, -- Version label matching ai_agents.version at publish time.
    changelog        text, -- Human-readable summary of what changed in this version.
    system_prompt    text NOT NULL, -- Snapshot of system_prompt at publish time.
    params           json, -- Snapshot of params at publish time.
    response_format  text NOT NULL DEFAULT 'json'::text,
    response_schema  json, -- Snapshot of response_schema at publish time.
    temperature      real NOT NULL DEFAULT 0.7,
    max_tokens       integer,
    published_at     timestamp with time zone,
    iam_user_id      bigint,
    iam_account_id   bigint,
    created_at       timestamp with time zone NOT NULL DEFAULT now(),
    updated_at       timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at       timestamp with time zone,
    CONSTRAINT ai_agent_versions_ai_agent_id_fkey FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    CONSTRAINT ai_agent_versions_pkey PRIMARY KEY (id)
);

CREATE INDEX idx_ai_agent_versions_agent_id ON public.ai_agent_versions USING btree (ai_agent_id);
CREATE INDEX idx_ai_agent_versions_version ON public.ai_agent_versions USING btree (ai_agent_id, version);
