
ALTER TABLE users_ig
    ADD COLUMN display_name VARCHAR(100) DEFAULT NULL AFTER id_user;

UPDATE users_ig UI
    INNER JOIN users U ON U.id_user = UI.id_user
SET UI.display_name = U.display_name
WHERE UI.display_name IS NULL OR UI.display_name = '';




ALTER TABLE language_texts ADD tag varchar(100) DEFAULT null NULL;
ALTER TABLE language_texts CHANGE tag tag varchar(100) DEFAULT null NULL AFTER dt_c;


ALTER TABLE elements ADD color varchar(100) DEFAULT null NULL;


-- Local chat support (radius-based).
-- Players type plain text with NO leading ! @ $ % * — that is local chat.
-- The server stores those rows as channel = 'L' (internal code only; never shown or typed by players).
-- Run once on playanimaster_db after chat_system.sql has been applied.

ALTER TABLE playanimaster_db.chat_messages
    ADD COLUMN origin_pos_x DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender X at send time' AFTER id_zone,
    ADD COLUMN origin_pos_y DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender Y at send time' AFTER origin_pos_x,
    ADD COLUMN origin_pos_z DECIMAL(10, 4) DEFAULT NULL COMMENT 'L channel: sender Z at send time' AFTER origin_pos_y;

ALTER TABLE playanimaster_db.chat_messages
    MODIFY COLUMN channel CHAR(1) NOT NULL COMMENT 'DB code: L=local (no user prefix), @ whisper, ! zone, $ clan, % alliance, * server',
    MODIFY COLUMN id_zone INT(11) DEFAULT NULL COMMENT 'L and ! channels: zone at send time';

ALTER TABLE playanimaster_db.chat_read_cursors
    MODIFY COLUMN channel CHAR(1) NOT NULL COMMENT 'DB code: L=local (no user prefix), @ whisper, ! zone, $ clan, % alliance, * server',
    MODIFY COLUMN id_zone INT(11) DEFAULT NULL COMMENT 'For L and ! channel cursors';


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





ALTER TABLE playanimaster_db.zone_animals 
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
ALTER TABLE playanimaster_db.zone_animals ADD id_spawn_point INT NULL;



ALTER TABLE playanimaster_db.dialogues_options ADD option_color varchar(50) DEFAULT null NULL;
ALTER TABLE playanimaster_db.dialogues_options ADD option_text varchar(200) DEFAULT null NULL;
ALTER TABLE playanimaster_db.dialogues_options ADD option_text_it varchar(200) DEFAULT null NULL;
ALTER TABLE playanimaster_db.dialogues_options ADD option_text_pt varchar(200) DEFAULT null NULL;


ALTER TABLE playanimaster_db.npcs ADD posz float DEFAULT 0 NULL;
ALTER TABLE playanimaster_db.npcs ADD wander_range int(11) DEFAULT 0 NULL;
ALTER TABLE playanimaster_db.npcs ADD euler_x float DEFAULT 0 NULL;
ALTER TABLE playanimaster_db.npcs ADD euler_y float DEFAULT 0 NULL;
ALTER TABLE playanimaster_db.npcs ADD euler_z float DEFAULT 0 NULL;
ALTER TABLE playanimaster_db.npcs ADD gender varchar(1) DEFAULT null NULL;



