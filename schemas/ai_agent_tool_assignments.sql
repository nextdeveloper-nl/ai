-- PostgreSQL
-- Declares which helpers/tools an agent is permitted to use.

CREATE TABLE ai_agent_tool_assignments (
    id                      bigint NOT NULL DEFAULT nextval('ai_agent_tool_assignments_id_seq'::regclass),
    uuid                    uuid NOT NULL DEFAULT gen_random_uuid(),
    ai_agent_id             bigint NOT NULL,
    ai_available_helper_id  bigint NOT NULL,
    configuration           json, -- Per-agent override config for this tool, if any.
    created_at              timestamp with time zone NOT NULL DEFAULT now(),
    updated_at              timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT ai_agent_tool_assignments_ai_agent_id_fkey FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    CONSTRAINT ai_agent_tool_assignments_ai_available_helper_id_fkey FOREIGN KEY (ai_available_helper_id) REFERENCES ai_available_helpers(id) ON DELETE CASCADE,
    CONSTRAINT ai_agent_tool_assignments_pkey PRIMARY KEY (id),
    CONSTRAINT ai_agent_tool_assignments_ai_agent_id_ai_available_helper_i_key UNIQUE (ai_agent_id, ai_available_helper_id)
);

CREATE INDEX idx_ai_agent_tool_assignments_agent ON public.ai_agent_tool_assignments USING btree (ai_agent_id);
CREATE INDEX idx_ai_agent_tool_assignments_helper ON public.ai_agent_tool_assignments USING btree (ai_available_helper_id);
