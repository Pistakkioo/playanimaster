-- In-game chat system (ANIMASTER)
-- Player input routing (first character only):
--   (no prefix)         -> LOCAL — nearby players in same zone (radius check in PHP)
--   @playerName + text  -> whisper
--   ! + text            -> zone
--   $ + text            -> clan
--   % + text            -> alliance
--   # + text            -> party
--   * + text            -> server-wide (requires global pass)
--
-- DB column `channel`: local rows use 'L' internally. Players never type L.
--
-- Message length: max 160 characters for the full user input (prefix + payload).
-- Run once on playanimaster_db. Adjust database name if needed.
--
-- NOTE: Legacy tables (users_ig, item_types, zones) are MyISAM in this project.
-- InnoDB cannot reference MyISAM with FOREIGN KEY — related columns use INDEX only.
-- Integrity is enforced in PHP. FKs are used only between new InnoDB chat tables.

-- ---------------------------------------------------------------------------
-- Clans & alliances (foundation: users_ig.id_clan already exists)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS playanimaster_db.alliances (
    id_alliance INT(11) NOT NULL AUTO_INCREMENT,
    alliance_name VARCHAR(64) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    PRIMARY KEY (id_alliance),
    UNIQUE KEY uq_alliances_name (alliance_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.clans (
    id_clan INT(11) NOT NULL AUTO_INCREMENT,
    clan_name VARCHAR(64) NOT NULL,
    id_alliance INT(11) DEFAULT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    PRIMARY KEY (id_clan),
    UNIQUE KEY uq_clans_name (clan_name),
    KEY idx_clans_alliance (id_alliance),
    CONSTRAINT fk_clans_alliance
        FOREIGN KEY (id_alliance) REFERENCES playanimaster_db.alliances (id_alliance)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Global (*) chat passes — granted by quest/special items
-- Either messages_left OR dt_expires (or both: whichever limit is hit first)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS playanimaster_db.chat_global_item_config (
    id_item_type INT(11) NOT NULL,
    messages_grant INT(11) DEFAULT NULL COMMENT 'Messages added when item is consumed; NULL = no message cap from item',
    days_duration INT(11) DEFAULT NULL COMMENT 'Pass duration in days when item is consumed; NULL = no time cap from item',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.chat_global_passes (
    id_chat_global_pass INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) DEFAULT NULL COMMENT 'Item that granted this pass, if any',
    messages_left INT(11) DEFAULT NULL COMMENT 'NULL = unlimited by count (time-only pass)',
    dt_expires DATETIME DEFAULT NULL COMMENT 'NULL = unlimited by time (count-only pass)',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    PRIMARY KEY (id_chat_global_pass),
    KEY idx_chat_global_passes_user_active (id_user_ig, flg_active, dt_expires),
    KEY idx_chat_global_passes_item (id_item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Chat messages (all channels)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS playanimaster_db.chat_messages (
    id_chat_message BIGINT(20) NOT NULL AUTO_INCREMENT,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    id_user_ig_sender INT(11) NOT NULL,
    sender_display_name VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot at send time',

    channel CHAR(1) NOT NULL COMMENT 'DB code: L=local (no user prefix), @ whisper, ! zone, $ clan, % alliance, # party, * server',

    message_text VARCHAR(160) NOT NULL COMMENT 'Visible body without channel prefix',

    id_zone INT(11) DEFAULT NULL COMMENT 'L and ! channels: zone at send time',
    origin_pos_x DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender X at send time',
    origin_pos_y DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender Y at send time',
    origin_pos_z DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender Z at send time',
    id_clan INT(11) DEFAULT NULL COMMENT '$ channel: sender clan at send time',
    id_alliance INT(11) DEFAULT NULL COMMENT '% channel: sender alliance at send time',
    id_party INT(11) DEFAULT NULL COMMENT '# channel: sender party at send time',

    id_user_ig_target INT(11) DEFAULT NULL COMMENT '@ channel: resolved recipient character',
    target_display_name VARCHAR(100) DEFAULT NULL COMMENT '@ channel: name typed by sender',

    id_chat_global_pass INT(11) DEFAULT NULL COMMENT '* channel: pass consumed for this message',

    flg_delivered CHAR(1) NOT NULL DEFAULT 'S' COMMENT '@ whisper: N if target was offline at send time',

    PRIMARY KEY (id_chat_message),

    KEY idx_chat_zone_poll (channel, id_zone, id_chat_message),
    KEY idx_chat_clan_poll (channel, id_clan, id_chat_message),
    KEY idx_chat_alliance_poll (channel, id_alliance, id_chat_message),
    KEY idx_chat_party_poll (channel, id_party, id_chat_message),
    KEY idx_chat_server_poll (channel, id_chat_message),
    KEY idx_chat_whisper_target (channel, id_user_ig_target, id_chat_message),
    KEY idx_chat_whisper_sender (channel, id_user_ig_sender, id_chat_message),
    KEY idx_chat_sender (id_user_ig_sender, dt_c),
    KEY idx_chat_dt (dt_c),
    KEY idx_chat_messages_global_pass (id_chat_global_pass),

    CONSTRAINT fk_chat_messages_clan
        FOREIGN KEY (id_clan) REFERENCES playanimaster_db.clans (id_clan)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_chat_messages_alliance
        FOREIGN KEY (id_alliance) REFERENCES playanimaster_db.alliances (id_alliance)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_chat_messages_global_pass
        FOREIGN KEY (id_chat_global_pass) REFERENCES playanimaster_db.chat_global_passes (id_chat_global_pass)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Per-character read cursors (optional but useful for polling / unread badges)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS playanimaster_db.chat_read_cursors (
    id_chat_read_cursor INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig INT(11) NOT NULL,
    channel CHAR(1) NOT NULL,
    id_zone INT(11) DEFAULT NULL COMMENT 'For L and ! channel cursors',
    id_clan INT(11) DEFAULT NULL COMMENT 'For $ channel cursor',
    id_alliance INT(11) DEFAULT NULL COMMENT 'For % channel cursor',
    id_user_ig_peer INT(11) DEFAULT NULL COMMENT 'For @ whisper thread with one peer',
    last_read_id BIGINT(20) NOT NULL DEFAULT 0,
    dt_m TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_chat_read_cursor),
    KEY idx_chat_read_user (id_user_ig),
    UNIQUE KEY uq_chat_read_whisper (id_user_ig, channel, id_user_ig_peer),
    UNIQUE KEY uq_chat_read_zone (id_user_ig, channel, id_zone),
    UNIQUE KEY uq_chat_read_clan (id_user_ig, channel, id_clan),
    UNIQUE KEY uq_chat_read_alliance (id_user_ig, channel, id_alliance),
    UNIQUE KEY uq_chat_read_server (id_user_ig, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Helpful indexes on existing character table (safe if already present)
-- ---------------------------------------------------------------------------

-- CREATE INDEX idx_users_ig_zone_online ON playanimaster_db.users_ig (id_zone, flg_online);
-- CREATE INDEX idx_users_ig_display_name ON playanimaster_db.users_ig (display_name);
-- CREATE INDEX idx_users_ig_clan ON playanimaster_db.users_ig (id_clan);


-- ---------------------------------------------------------------------------
-- Example: item that grants 10 server messages for 7 days
-- ---------------------------------------------------------------------------
-- INSERT INTO playanimaster_db.chat_global_item_config (id_item_type, messages_grant, days_duration)
-- VALUES (123, 10, 7);
