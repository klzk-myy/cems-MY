-- ============================================================================
-- CEMS-MY database hardening — SYSADMIN runbook
-- DB-IMPLEMENTATION-PLAN.md phases 1, 2, 6 (blocked items only)
--
-- Everything the application account cannot execute, packaged for a
-- privileged DBA. Run sections A (my.cnf) then B (grants) then verify
-- with section C. Observed values are from cems_my_staging @ MariaDB
-- 10.11.19 on 2026-09-18.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- A. /etc/my.cnf  (or /etc/mysql/mariadb.conf.d/*.cnf) — [mariadb] section
--    Requires a server restart. These are NOT SQL — add them to the config
--    file, they are listed here for completeness:
--
--    innodb_flush_log_at_trx_commit = 1     (was 0 — commits can be lost on crash)
--    sync_binlog                  = 1       (was 0 — binlog unsafe for PITR/replication)
--    bind_address                 = 127.0.0.1   (was '' — listening on all interfaces)
--    local_infile                 = 0       (was ON — no app code uses LOAD DATA)
--    secure_file_priv             = /var/lib/mysql-files   (was '' — unrestricted)
--    skip_name_resolve            = ON      (was OFF — grants already use 'localhost')
--    character_set_server         = utf8mb4         (was latin1)
--    collation_server             = utf8mb4_unicode_ci    (was latin1_swedish_ci)
--    sql_mode                     = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY,NO_ZERO_DATE,NO_ZERO_IN_DATE'
--                                 (app already enforces this per-session via
--                                  Laravel strict=true; this fixes the global default.
--                                  Zero-date scan is clean — 25 date cols, 0 hits.)
--    long_query_time              = 1.0     (was 3.0 — actionable slow-log capture)
--    log_slow_verbosity           = query_plan
--
--    TLS (alternative to bind_address if remote access is ever needed):
--    require_secure_transport     = ON      (plus ssl_cert/ssl_key/ssl_ca)
-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- B. Grants — run as root. Splits the runtime user (DML only) from the
--    deploy/DDL user. cems_staging currently holds ALL PRIVILEGES on the
--    schema, which includes DROP/ALTER at runtime.
-- ----------------------------------------------------------------------------

-- Runtime user used by the web app (PHP-FPM/Octane/queue workers):
CREATE USER IF NOT EXISTS 'cems_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON `cems_my_staging`.* TO 'cems_app'@'localhost';

-- Deploy-only DDL user (used by deploy.yml for installers + SchemaSeeder):
CREATE USER IF NOT EXISTS 'cems_ddl'@'localhost' IDENTIFIED BY 'CHANGE_ME_strong_password2';
GRANT ALL PRIVILEGES ON `cems_my_staging`.* TO 'cems_ddl'@'localhost';

-- After .env is switched to cems_app and deploy uses cems_ddl:
-- DROP USER 'cems_staging'@'localhost';

-- MariaDB default PUBLIC grants on test/test_% — remove if present:
-- DROP DATABASE IF EXISTS test;
-- DELETE FROM mysql.db WHERE Db LIKE 'test%'; FLUSH PRIVILEGES;

-- ----------------------------------------------------------------------------
-- C. Post-change verification — expected output
-- ----------------------------------------------------------------------------
SHOW GLOBAL VARIABLES WHERE Variable_name IN (
    'innodb_flush_log_at_trx_commit',  -- expect 1
    'sync_binlog',                     -- expect 1
    'bind_address',                    -- expect 127.0.0.1
    'local_infile',                    -- expect OFF
    'secure_file_priv',                -- expect /var/lib/mysql-files
    'skip_name_resolve',               -- expect ON
    'character_set_server',            -- expect utf8mb4
    'collation_server',                -- expect utf8mb4_unicode_ci
    'sql_mode',                        -- expect ONLY_FULL_GROUP_BY + NO_ZERO_DATE present
    'long_query_time'                  -- expect 1.0
);
SHOW GRANTS FOR 'cems_app'@'localhost';  -- expect SELECT,INSERT,UPDATE,DELETE only
SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'test';  -- expect 0 rows
