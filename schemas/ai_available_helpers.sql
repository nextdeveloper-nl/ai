-- PostgreSQL

CREATE TABLE ai_available_helpers (
    id                  bigint NOT NULL DEFAULT nextval('ai_available_helpers_id_seq'::regclass),
    uuid                uuid DEFAULT gen_random_uuid(),
    name                text NOT NULL,
    action              text NOT NULL,
    description         text NOT NULL,
    class               text NOT NULL,
    input               text,
    outputs             json,
    parameters          json NOT NULL,
    created_at          timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at          timestamp with time zone,
    common_category_id  bigint,
    icon                text,
    auth_schema         json,
    is_public           boolean NOT NULL DEFAULT true,
    price               numeric(10,6) NOT NULL DEFAULT 0,
    CONSTRAINT ai_available_helpers_pkey PRIMARY KEY (id)
);

CREATE INDEX idx_ai_available_helpers_category ON public.ai_available_helpers USING btree (common_category_id);
CREATE INDEX idx_ai_available_helpers_is_public ON public.ai_available_helpers USING btree (is_public);
