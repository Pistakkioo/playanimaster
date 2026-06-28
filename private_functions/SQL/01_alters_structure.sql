
ALTER TABLE users_ig
    ADD COLUMN display_name VARCHAR(100) DEFAULT NULL AFTER id_user;

UPDATE users_ig UI
    INNER JOIN users U ON U.id_user = UI.id_user
SET UI.display_name = U.display_name
WHERE UI.display_name IS NULL OR UI.display_name = '';




ALTER TABLE language_texts ADD tag varchar(100) DEFAULT null NULL;
ALTER TABLE language_texts CHANGE tag tag varchar(100) DEFAULT null NULL AFTER dt_c;
ALTER TABLE playanimaster_db.language_texts ADD UNIQUE KEY uniq_language_texts_tag (tag);


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




ALTER TABLE playanimaster_db.animals CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.battles_solo_pve CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.items CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.notifications CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.user_conversations CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.user_quests CHANGE id_user id_user_ig int(11) DEFAULT NULL NULL;





ALTER TABLE playanimaster_db.users_ig ADD gender varchar(1) DEFAULT null NULL;
ALTER TABLE playanimaster_db.users_ig ADD character_type varchar(100) DEFAULT null NULL;
ALTER TABLE playanimaster_db.users_ig ADD move_speed decimal(10,4) DEFAULT null NULL;
ALTER TABLE playanimaster_db.users_ig ADD target_position_x decimal(10,4) DEFAULT null NULL;
ALTER TABLE playanimaster_db.users_ig ADD target_position_y decimal(10,4) DEFAULT null NULL;
ALTER TABLE playanimaster_db.users_ig ADD target_position_z decimal(10,4) DEFAULT null NULL;


ALTER TABLE playanimaster_db.wild_animals ADD id_zone int(11) DEFAULT null NULL;
ALTER TABLE playanimaster_db.wild_animals ADD id_spawn_point int(11) DEFAULT null NULL;
ALTER TABLE playanimaster_db.wild_animals ADD pos_x float DEFAULT null NULL;
ALTER TABLE playanimaster_db.wild_animals ADD pos_y float DEFAULT null NULL;
ALTER TABLE playanimaster_db.wild_animals ADD pos_z float DEFAULT null NULL;




ALTER TABLE playanimaster_db.abilities  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.alliances  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.animals  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.battles_solo_pve  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.battles_solo_pve_moves  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.chat_global_item_config  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.chat_global_passes  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.chat_messages  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.chat_read_cursors  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.chat_word_replacements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.clans  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.classes  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.consequences  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.conversations  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.conversation_consequences  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.conversation_requirements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.costanti  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.dialogues  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.dialogues_options  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.elements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.items  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.item_types  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.language_texts  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.log  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.notifications  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.npcs  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.npc_requirements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.quests  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.quest_requirements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.requirements  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.spawn_points  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.species  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.species_abilities  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.storico_battles_solo_pve_moves  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.subclasses  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.trades  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.trade_item_locks  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.trade_offer_items  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.trade_requests  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.users  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.users_ig  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.user_conversations  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.user_quests  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.wild_animals  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.wild_animal_drop_types  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.zones  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE playanimaster_db.zone_animals  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE playanimaster_db.consequences CHANGE `ref` ref_table varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL NULL;
ALTER TABLE playanimaster_db.requirements CHANGE `ref` ref_table varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL NULL;

ALTER TABLE playanimaster_db.consequences ADD COLUMN params_json TEXT NULL DEFAULT NULL AFTER num;

ALTER TABLE playanimaster_db.user_conversations ADD UNIQUE KEY uniq_user_conversation (id_user_ig, id_conversation);




ALTER TABLE playanimaster_db.users_ig
    ADD COLUMN id_player_class INT(11) DEFAULT NULL COMMENT 'Gameplay class (nerd/stud tree)' AFTER character_type,
    ADD KEY idx_users_ig_player_class (id_player_class);

UPDATE playanimaster_db.users_ig
SET id_player_class = 1
WHERE id_player_class IS NULL;












