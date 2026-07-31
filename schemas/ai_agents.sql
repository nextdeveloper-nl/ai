-- PostgreSQL
-- Stores AI agents for AI assistance in the system.

CREATE TABLE ai_agents (
    id                  bigint NOT NULL DEFAULT nextval('ai_agents_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    name                text NOT NULL, -- Human-readable name of the AI agent.
    slug                text NOT NULL, -- Unique identifier for the AI agent.
    ref_name            text, -- The reference name of the AI agent. eg: plusclouds/domain-extractor
    description         text, -- Optional description of the AI agent.
    system_prompt       text NOT NULL, -- Prompt used by the AI agent to generate responses.
    params              json, -- Parameters for the AI agent, such as model, temperature, etc.
    response_format     text NOT NULL DEFAULT 'json'::text, -- Format of the AI agent response, such as text or JSON.
    response_schema     json, -- Schema for the AI agent response, if applicable.
    temperature         real NOT NULL DEFAULT 0.7, -- Temperature parameter for the AI agent, controlling the randomness of its responses.
    max_tokens          integer, -- Maximum number of tokens allowed in the AI agent response.
    metadata            json, -- Additional metadata associated with the AI agent.
    is_active           boolean NOT NULL DEFAULT true, -- Indicates whether the AI agent is currently active.
    iam_user_id         bigint, -- User who created or owns the AI agent.
    iam_account_id      bigint, -- Account that owns the AI agent.
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    common_category_id  bigint,
    tags                text[],
    cover_image_url     text,
    external_docs_url   text,
    visibility          text NOT NULL DEFAULT 'private'::text,
    version             text NOT NULL DEFAULT '1.0.0'::text,
    is_published        boolean NOT NULL DEFAULT false,
    published_at        timestamp with time zone,
    price               numeric(10,6) NOT NULL DEFAULT 0,
    CONSTRAINT ai_agents_pkey PRIMARY KEY (id),
    CONSTRAINT ai_agents_slug_key UNIQUE (slug),
    CONSTRAINT ai_agents_slug_unique UNIQUE (slug)
);

CREATE INDEX idx_ai_agents_category ON public.ai_agents USING btree (common_category_id);
CREATE INDEX idx_ai_agents_iam_account ON public.ai_agents USING btree (iam_account_id);
CREATE INDEX idx_ai_agents_is_active ON public.ai_agents USING btree (is_active);
CREATE INDEX idx_ai_agents_is_published ON public.ai_agents USING btree (is_published);
CREATE INDEX idx_ai_agents_slug ON public.ai_agents USING btree (slug);
CREATE INDEX idx_ai_agents_tags ON public.ai_agents USING gin (tags);
CREATE INDEX idx_ai_agents_uuid ON public.ai_agents USING btree (uuid);
CREATE INDEX idx_ai_agents_visibility ON public.ai_agents USING btree (visibility);
