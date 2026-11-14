-- PostgreSQL

CREATE TABLE ai_conversations (
    id             bigint NOT NULL DEFAULT nextval('ai_conversations_id_seq'::regclass),
    uuid           uuid DEFAULT gen_random_uuid(),
    ai_session_id  bigint,
    iam_account_id bigint, -- Copied from the session; the observer keeps it in line.
    iam_user_id    bigint,
    role           text NOT NULL,
    message        text NOT NULL,
    created_at     timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at     timestamp with time zone,
    CONSTRAINT ai_conversations_pkey PRIMARY KEY (id)
);
