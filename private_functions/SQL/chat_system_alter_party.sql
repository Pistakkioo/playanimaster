-- Party chat channel (#).
-- Players type #message — routed to party members (users_ig.id_party).
-- Run once on playanimaster_db after chat_system.sql.

ALTER TABLE playanimaster_db.chat_messages
    ADD COLUMN id_party INT(11) DEFAULT NULL COMMENT '# channel: sender party at send time' AFTER id_alliance;

ALTER TABLE playanimaster_db.chat_messages
    ADD KEY idx_chat_party_poll (channel, id_party, id_chat_message);

ALTER TABLE playanimaster_db.chat_messages
    MODIFY COLUMN channel CHAR(1) NOT NULL COMMENT 'DB code: L=local, @ whisper, ! zone, $ clan, % alliance, # party, * server';

ALTER TABLE playanimaster_db.chat_read_cursors
    MODIFY COLUMN channel CHAR(1) NOT NULL COMMENT 'DB code: L=local, @ whisper, ! zone, $ clan, % alliance, # party, * server';
