-- PostgreSQL

CREATE TABLE ai_sessions (
    id              bigint NOT NULL DEFAULT nextval('ai_sessions_id_seq'::regclass),
    uuid            uuid DEFAULT gen_random_uuid(),
    created_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      timestamp with time zone,
    iam_user_id     bigint,
    iam_account_id  bigint,
    ai_engine_id    bigint,
    CONSTRAINT ai_sessions_pkey PRIMARY KEY (id)
);
