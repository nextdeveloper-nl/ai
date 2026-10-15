-- PostgreSQL
-- Broadcast groups: one prompt fanned out to several provider/model targets.

CREATE SEQUENCE IF NOT EXISTS ai_llm_broadcast_groups_id_seq;

CREATE TABLE ai_llm_broadcast_groups (
    id                  bigint NOT NULL DEFAULT nextval('ai_llm_broadcast_groups_id_seq'::regclass),
    uuid                uuid NOT NULL DEFAULT gen_random_uuid(),
    name                text NOT NULL,
    mode                text NOT NULL DEFAULT 'broadcast', -- broadcast (return all) | first (return fastest success)
    timeout_ms          integer NOT NULL DEFAULT 60000,
    targets             json NOT NULL, -- [{"provider":"slug","model":"id"} | {"alias":"name"}]
    is_active           boolean NOT NULL DEFAULT true,
    iam_account_id      bigint NOT NULL,
    iam_user_id         bigint,
    created_at          timestamp with time zone NOT NULL DEFAULT now(),
    updated_at          timestamp with time zone NOT NULL DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT ai_llm_broadcast_groups_pkey PRIMARY KEY (id),
    CONSTRAINT ai_llm_broadcast_groups_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT ai_llm_broadcast_groups_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id)
);

CREATE UNIQUE INDEX uq_ai_llm_broadcast_groups_account_name ON ai_llm_broadcast_groups (iam_account_id, name) WHERE deleted_at IS NULL;
CREATE INDEX idx_ai_llm_broadcast_groups_uuid ON ai_llm_broadcast_groups USING btree (uuid);
