CREATE TABLE IF NOT EXISTS "#__panopticon_coresums" (
    "id"       SERIAL          NOT NULL,
    "path"     VARCHAR(1024)   NOT NULL,
    "checksum" VARCHAR(128)    NOT NULL,
    PRIMARY KEY ("id")
);
