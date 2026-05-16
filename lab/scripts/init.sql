-- =============================================================
--  UrbanUpC Intranet — MySQL 8.0 Schema
--  Database: corpnet_db
--  Charset:  utf8mb4 / utf8mb4_unicode_ci
--  Notes:
--    - INT AUTO_INCREMENT PKs (intentional IDOR surface)
--    - MD5 password hashing (intentionally weak)
--    - sql_mode='' in mysql.cnf (permissive inserts)
-- =============================================================

USE corpnet_db;

-- ── users ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id          INT          NOT NULL AUTO_INCREMENT,
    username    VARCHAR(80)  NOT NULL,
    password    VARCHAR(255) NOT NULL,   -- MD5 hash (intentionally weak)
    email       VARCHAR(255) NOT NULL,
    first_name  VARCHAR(100) DEFAULT NULL,
    last_name   VARCHAR(100) DEFAULT NULL,
    role        ENUM('user','manager','admin') NOT NULL DEFAULT 'user',
    department  VARCHAR(100) DEFAULT NULL,
    phone       VARCHAR(30)  DEFAULT NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login  DATETIME     DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email),
    KEY idx_users_role           (role),
    KEY idx_users_active         (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── sessions ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS sessions (
    id            INT          NOT NULL AUTO_INCREMENT,
    session_token VARCHAR(255) NOT NULL,
    user_id       INT          NOT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    user_agent    TEXT         DEFAULT NULL,
    expires_at    DATETIME     NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_token (session_token),
    KEY idx_sessions_user        (user_id),
    KEY idx_sessions_expires     (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── documents ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS documents (
    id             INT         NOT NULL AUTO_INCREMENT,
    owner_id       INT         NOT NULL,
    title          VARCHAR(255) NOT NULL,
    content        LONGTEXT     DEFAULT NULL,
    classification ENUM('public','internal','confidential','secret')
                               NOT NULL DEFAULT 'internal',
    category       VARCHAR(100) DEFAULT NULL,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_owner          (owner_id),
    KEY idx_documents_classification (classification),
    CONSTRAINT fk_documents_owner FOREIGN KEY (owner_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── announcements ─────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS announcements (
    id         INT          NOT NULL AUTO_INCREMENT,
    author_id  INT          NOT NULL,
    title      VARCHAR(255) NOT NULL,
    content    TEXT         NOT NULL,
    pinned     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_author (author_id),
    KEY idx_announcements_pinned (pinned),
    CONSTRAINT fk_announcements_author FOREIGN KEY (author_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── services ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS services (
    id            INT          NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    description   TEXT         DEFAULT NULL,
    category      VARCHAR(100) DEFAULT NULL,
    icon          VARCHAR(100) DEFAULT NULL,
    contact_name  VARCHAR(150) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    status        ENUM('online','degraded','offline') NOT NULL DEFAULT 'online',
    created_by    INT          NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_services_status   (status),
    KEY idx_services_category (category),
    CONSTRAINT fk_services_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── audit_logs ────────────────────────────────────────────────────────────
-- No FK on user_id — keeps logs even after account deletion

CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          DEFAULT NULL,
    username   VARCHAR(80)  DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    resource   VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    user_agent TEXT         DEFAULT NULL,
    status     VARCHAR(20)  DEFAULT NULL,
    details    TEXT         DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user      (user_id),
    KEY idx_audit_action    (action),
    KEY idx_audit_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── uploads ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS uploads (
    id            INT          NOT NULL AUTO_INCREMENT,
    uploader_id   INT          NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) DEFAULT NULL,
    file_size     INT          DEFAULT NULL,
    upload_path   VARCHAR(500) DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_uploads_uploader (uploader_id),
    CONSTRAINT fk_uploads_uploader FOREIGN KEY (uploader_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── resources ─────────────────────────────────────────────────────────────
-- Student Resource Hub — partage de supports de cours

CREATE TABLE IF NOT EXISTS resources (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    title      VARCHAR(255) NOT NULL,
    file_path  VARCHAR(500) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resources_user (user_id),
    CONSTRAINT fk_resources_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── resource_comments ─────────────────────────────────────────────────────
-- Commentaires sur les ressources — stockés bruts (XSS intentionnel)

CREATE TABLE IF NOT EXISTS resource_comments (
    id          INT  NOT NULL AUTO_INCREMENT,
    resource_id INT  NOT NULL,
    user_id     INT  NOT NULL,
    comment     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rc_resource (resource_id),
    CONSTRAINT fk_rc_resource FOREIGN KEY (resource_id)
        REFERENCES resources (id) ON DELETE CASCADE,
    CONSTRAINT fk_rc_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── internships ───────────────────────────────────────────────────────────
-- Career Center — offres de stage

CREATE TABLE IF NOT EXISTS internships (
    id          INT          NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NOT NULL,
    description TEXT         DEFAULT NULL,
    company     VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── applications ──────────────────────────────────────────────────────────
-- Candidatures — PK séquentielle intentionnelle (surface IDOR)

CREATE TABLE IF NOT EXISTS applications (
    id            INT          NOT NULL AUTO_INCREMENT,
    internship_id INT          NOT NULL,
    student_id    INT          NOT NULL,
    cv_file_path  VARCHAR(500) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_app_internship (internship_id),
    KEY idx_app_student    (student_id),
    CONSTRAINT fk_app_internship FOREIGN KEY (internship_id)
        REFERENCES internships (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_student FOREIGN KEY (student_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── api_key on users ──────────────────────────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS api_key VARCHAR(64) NULL DEFAULT NULL;

-- ── visibility RBAC ───────────────────────────────────────────────────────
-- Allowed values: 'all' | 'admin' | 'manager' | 'user'
ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS visibility VARCHAR(50) NOT NULL DEFAULT 'all';
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS visibility VARCHAR(50) NOT NULL DEFAULT 'all';

-- ── subscribers (Landing Page — newsletter SQLi intentionnelle) ────────────
CREATE TABLE IF NOT EXISTS subscribers (
    id         INT          NOT NULL AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── chat_messages (Channels — Broken Access Control intentionnel) ──────────
CREATE TABLE IF NOT EXISTS chat_messages (
    id           INT         NOT NULL AUTO_INCREMENT,
    channel_name VARCHAR(50) NOT NULL,
    user_id      INT         NOT NULL,
    message      TEXT        NOT NULL,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_channel (channel_name),
    KEY idx_chat_user    (user_id),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Career Center : colonne status pour la modération ────────────────────
ALTER TABLE internships
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER company;

-- ── Dépôt P2P : receiver_id en VARCHAR pour supporter la valeur 'all' ─────
ALTER TABLE file_transfers
    DROP FOREIGN KEY fk_ft_receiver,
    MODIFY COLUMN receiver_id VARCHAR(50) NOT NULL;

-- ── file_transfers (Dépôt P2P — IDOR intentionnel sur le téléchargement) ───
CREATE TABLE IF NOT EXISTS file_transfers (
    id          INT          NOT NULL AUTO_INCREMENT,
    sender_id   INT          NOT NULL,
    receiver_id INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ft_sender   (sender_id),
    KEY idx_ft_receiver (receiver_id),
    CONSTRAINT fk_ft_sender   FOREIGN KEY (sender_id)   REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ft_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Skills sur les utilisateurs (Annuaire / Profil) ───────────────────────
ALTER TABLE users ADD COLUMN skills VARCHAR(255) NULL DEFAULT NULL;

-- ── events (Agenda Promo — Stored XSS intentionnel sur description) ────────
CREATE TABLE IF NOT EXISTS events (
    id          INT          NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NOT NULL,
    description TEXT         DEFAULT NULL,
    event_date  DATETIME     NOT NULL,
    created_by  INT          NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_date       (event_date),
    KEY idx_events_created_by (created_by),
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
