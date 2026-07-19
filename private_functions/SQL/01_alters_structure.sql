
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

ALTER TABLE playanimaster_db.requirements ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table;
ALTER TABLE playanimaster_db.consequences ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table;

ALTER TABLE playanimaster_db.user_conversations ADD UNIQUE KEY uniq_user_conversation (id_user_ig, id_conversation);




ALTER TABLE playanimaster_db.users_ig
    ADD COLUMN id_player_class INT(11) DEFAULT NULL COMMENT 'Gameplay class (nerd/stud tree)' AFTER character_type,
    ADD KEY idx_users_ig_player_class (id_player_class);

UPDATE playanimaster_db.users_ig
SET id_player_class = 1
WHERE id_player_class IS NULL;


-- Requirements: min/max/descrizione on link tables (catalog row is reusable).
ALTER TABLE playanimaster_db.npc_requirements
    ADD COLUMN min int(11) NOT NULL DEFAULT 0 AFTER id_requirement,
    ADD COLUMN max int(11) NOT NULL DEFAULT 9999 AFTER min,
    ADD COLUMN descrizione varchar(100) NULL DEFAULT NULL AFTER max;

ALTER TABLE playanimaster_db.conversation_requirements
    ADD COLUMN min int(11) NOT NULL DEFAULT 0 AFTER id_requirement,
    ADD COLUMN max int(11) NOT NULL DEFAULT 9999 AFTER min,
    ADD COLUMN descrizione varchar(100) NULL DEFAULT NULL AFTER max;

ALTER TABLE playanimaster_db.quest_requirements
    ADD COLUMN min int(11) NOT NULL DEFAULT 0 AFTER id_requirement,
    ADD COLUMN max int(11) NOT NULL DEFAULT 9999 AFTER min,
    ADD COLUMN descrizione varchar(100) NULL DEFAULT NULL AFTER max;

UPDATE playanimaster_db.npc_requirements NR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = NR.id_requirement
SET NR.min = R.min,
    NR.max = R.max,
    NR.descrizione = R.descrizione;

UPDATE playanimaster_db.conversation_requirements CR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = CR.id_requirement
SET CR.min = R.min,
    CR.max = R.max,
    CR.descrizione = R.descrizione;

UPDATE playanimaster_db.quest_requirements QR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = QR.id_requirement
SET QR.min = R.min,
    QR.max = R.max,
    QR.descrizione = R.descrizione;

UPDATE playanimaster_db.conversation_requirements SET id_requirement = 2 WHERE id_requirement = 7;
UPDATE playanimaster_db.conversation_requirements SET id_requirement = 8 WHERE id_requirement IN (11, 14);
UPDATE playanimaster_db.npc_requirements SET id_requirement = 8 WHERE id_requirement IN (11, 14);

DELETE FROM playanimaster_db.requirements WHERE id_requirement IN (7, 11, 14);

ALTER TABLE playanimaster_db.requirements
    DROP COLUMN min,
    DROP COLUMN max,
    DROP COLUMN descrizione;


-- Requirement link max: NULL = no upper bound (legacy 999 / 9999 meant unbounded).
ALTER TABLE playanimaster_db.npc_requirements
    MODIFY COLUMN max int(11) NULL DEFAULT NULL COMMENT 'NULL = no upper bound';

ALTER TABLE playanimaster_db.conversation_requirements
    MODIFY COLUMN max int(11) NULL DEFAULT NULL COMMENT 'NULL = no upper bound';

ALTER TABLE playanimaster_db.quest_requirements
    MODIFY COLUMN max int(11) NULL DEFAULT NULL COMMENT 'NULL = no upper bound';

UPDATE playanimaster_db.npc_requirements SET max = NULL WHERE max >= 999 OR max = 9999;
UPDATE playanimaster_db.conversation_requirements SET max = NULL WHERE max >= 999 OR max = 9999;
UPDATE playanimaster_db.quest_requirements SET max = NULL WHERE max >= 999 OR max = 9999;


-- Requirements catalog = type only; id_ref/ref_table/ref_description on link tables.
ALTER TABLE playanimaster_db.npc_requirements
    ADD COLUMN id_ref int(11) NULL DEFAULT NULL AFTER id_requirement,
    ADD COLUMN ref_table varchar(100) NULL DEFAULT NULL AFTER id_ref,
    ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table;

ALTER TABLE playanimaster_db.conversation_requirements
    ADD COLUMN id_ref int(11) NULL DEFAULT NULL AFTER id_requirement,
    ADD COLUMN ref_table varchar(100) NULL DEFAULT NULL AFTER id_ref,
    ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table;

ALTER TABLE playanimaster_db.quest_requirements
    ADD COLUMN id_ref int(11) NULL DEFAULT NULL AFTER id_requirement,
    ADD COLUMN ref_table varchar(100) NULL DEFAULT NULL AFTER id_ref,
    ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table;

UPDATE playanimaster_db.npc_requirements NR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = NR.id_requirement
SET NR.id_ref = R.id_ref,
    NR.ref_table = R.ref_table,
    NR.ref_description = R.ref_description;

UPDATE playanimaster_db.conversation_requirements CR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = CR.id_requirement
SET CR.id_ref = R.id_ref,
    CR.ref_table = R.ref_table,
    CR.ref_description = R.ref_description;

UPDATE playanimaster_db.quest_requirements QR
    INNER JOIN playanimaster_db.requirements R ON R.id_requirement = QR.id_requirement
SET QR.id_ref = R.id_ref,
    QR.ref_table = R.ref_table,
    QR.ref_description = R.ref_description;

UPDATE playanimaster_db.conversation_requirements SET id_requirement = 3 WHERE id_requirement IN (4, 9, 12);
UPDATE playanimaster_db.conversation_requirements SET id_requirement = 2 WHERE id_requirement IN (15, 16, 17);
UPDATE playanimaster_db.conversation_requirements SET id_requirement = 10 WHERE id_requirement = 18;

DELETE FROM playanimaster_db.requirements WHERE id_requirement IN (4, 9, 12, 15, 16, 17, 18);

INSERT IGNORE INTO playanimaster_db.requirements (id_requirement, requirement_type) VALUES (11, 'conversation not finished');

ALTER TABLE playanimaster_db.requirements
    DROP COLUMN id_ref,
    DROP COLUMN ref_table,
    DROP COLUMN ref_description;

ALTER TABLE playanimaster_db.requirements
    ADD UNIQUE KEY uniq_requirements_type (requirement_type);


-- Consequences catalog = type only; instance fields on conversation_consequences.
ALTER TABLE playanimaster_db.conversation_consequences
    ADD COLUMN id_ref int(11) NULL DEFAULT NULL AFTER id_consequence,
    ADD COLUMN ref_table varchar(100) NULL DEFAULT NULL AFTER id_ref,
    ADD COLUMN ref_description varchar(200) NULL DEFAULT NULL AFTER ref_table,
    ADD COLUMN num int(11) NOT NULL DEFAULT 1 AFTER ref_description,
    ADD COLUMN params_json text NULL DEFAULT NULL AFTER num;

UPDATE playanimaster_db.conversation_consequences CC
    INNER JOIN playanimaster_db.consequences C ON C.id_consequence = CC.id_consequence
SET CC.id_ref = C.id_ref,
    CC.ref_table = C.ref_table,
    CC.ref_description = C.ref_description,
    CC.num = C.num,
    CC.params_json = C.params_json;

ALTER TABLE playanimaster_db.consequences
    DROP COLUMN id_ref,
    DROP COLUMN ref_table,
    DROP COLUMN ref_description,
    DROP COLUMN num,
    DROP COLUMN params_json;

ALTER TABLE playanimaster_db.consequences
    ADD UNIQUE KEY uniq_consequences_type (consequence_type);


-- Party system (module 002_PARTY_SYSTEM)
CREATE TABLE IF NOT EXISTS playanimaster_db.parties (
    id_party INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig_leader INT(11) NOT NULL,
    id_zone INT(11) DEFAULT NULL,
    max_members TINYINT(3) UNSIGNED NOT NULL DEFAULT 4,
    dt_created TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_party),
    KEY idx_parties_leader (id_user_ig_leader)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.party_members (
    id_party_member INT(11) NOT NULL AUTO_INCREMENT,
    id_party INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    dt_joined TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_party_member),
    UNIQUE KEY uniq_party_member_user (id_user_ig),
    KEY idx_party_members_party (id_party)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.party_invites (
    id_party_invite INT(11) NOT NULL AUTO_INCREMENT,
    id_party INT(11) NOT NULL,
    id_user_ig_sender INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_expires DATETIME NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending, A=accepted, D=declined, X=expired, C=cancelled',
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_party_invite),
    KEY idx_party_inv_target_pending (id_user_ig_target, flg_status, dt_expires),
    KEY idx_party_inv_party_pending (id_party, flg_status, dt_expires),
    KEY idx_party_inv_sender_pending (id_user_ig_sender, flg_status, dt_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Party PvE (module 002b_PARTY_PVE)
CREATE TABLE IF NOT EXISTS playanimaster_db.battles_party_pve (
    id_battle_party_pve INT(11) NOT NULL AUTO_INCREMENT,
    id_party INT(11) NOT NULL,
    id_wild_animal INT(11) NOT NULL,
    id_zone INT(11) DEFAULT NULL,
    id_user_ig_leader INT(11) NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=ongoing, F=finished, X=cancelled',
    current_turn INT(11) NOT NULL DEFAULT 0,
    turn_queue_json TEXT NULL DEFAULT NULL COMMENT 'Ordered actor slots for speed queue',
    turn_index INT(11) NOT NULL DEFAULT 0,
    awaiting_user_ig INT(11) DEFAULT NULL,
    end_reason VARCHAR(50) DEFAULT NULL,
    dt_created TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_finished TIMESTAMP NULL DEFAULT NULL,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_party_pve),
    KEY idx_battles_party_pve_party (id_party, flg_status),
    KEY idx_battles_party_pve_wild (id_wild_animal, flg_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_party_pve_participants (
    id_battle_party_pve_participant INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_party_pve INT(11) NOT NULL,
    id_user_ig INT(11) DEFAULT NULL,
    id_animal INT(11) DEFAULT NULL,
    side CHAR(5) NOT NULL DEFAULT 'party' COMMENT 'party, wild',
    team_position TINYINT(3) UNSIGNED DEFAULT NULL,
    flg_active CHAR(1) NOT NULL DEFAULT 'S' COMMENT 'S=active fighter, N=benched',
    flg_fainted CHAR(1) NOT NULL DEFAULT 'N',
    current_hp INT(11) DEFAULT NULL,
    max_hp INT(11) DEFAULT NULL,
    atk INT(11) DEFAULT NULL,
    def INT(11) DEFAULT NULL,
    matk INT(11) DEFAULT NULL,
    mdef INT(11) DEFAULT NULL,
    acc INT(11) DEFAULT NULL,
    eva INT(11) DEFAULT NULL,
    cr INT(11) DEFAULT NULL,
    spd INT(11) DEFAULT NULL,
    id_species INT(11) DEFAULT NULL,
    id_element INT(11) DEFAULT NULL,
    lvl INT(11) DEFAULT NULL,
    nickname VARCHAR(100) DEFAULT NULL,
    species_name VARCHAR(100) DEFAULT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_party_pve_participant),
    KEY idx_bpp_participants_battle (id_battle_party_pve, side),
    KEY idx_bpp_participants_user (id_user_ig, id_battle_party_pve)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_party_pve_moves (
    id_battle_party_pve_move INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_party_pve INT(11) DEFAULT NULL,
    id_user_ig_actor INT(11) DEFAULT NULL,
    dt_creazione TIMESTAMP NULL DEFAULT NULL,
    dt_modifica TIMESTAMP NULL DEFAULT NULL,
    turn INT(11) DEFAULT NULL,
    move_type VARCHAR(100) DEFAULT NULL,
    move_speed DECIMAL(10,2) DEFAULT NULL,
    order_in_turn INT(11) DEFAULT NULL,
    id_rif INT(11) DEFAULT NULL,
    move_description VARCHAR(200) DEFAULT NULL,
    move_hit VARCHAR(1) DEFAULT NULL,
    w_a_res_hp INT(11) DEFAULT NULL,
    p_a_res_hp INT(11) DEFAULT NULL,
    w_a_res_atk DECIMAL(10,2) DEFAULT NULL,
    w_a_res_def DECIMAL(10,2) DEFAULT NULL,
    w_a_res_matk DECIMAL(10,2) DEFAULT NULL,
    w_a_res_mdef DECIMAL(10,2) DEFAULT NULL,
    w_a_res_acc INT(11) DEFAULT NULL,
    w_a_res_eva INT(11) DEFAULT NULL,
    w_a_res_cr INT(11) DEFAULT NULL,
    w_a_res_spd DECIMAL(10,2) DEFAULT NULL,
    p_a_res_atk DECIMAL(10,2) DEFAULT NULL,
    p_a_res_def DECIMAL(10,2) DEFAULT NULL,
    p_a_res_matk DECIMAL(10,2) DEFAULT NULL,
    p_a_res_mdef DECIMAL(10,2) DEFAULT NULL,
    p_a_res_acc INT(11) DEFAULT NULL,
    p_a_res_eva INT(11) DEFAULT NULL,
    p_a_res_cr INT(11) DEFAULT NULL,
    p_a_res_spd DECIMAL(10,2) DEFAULT NULL,
    w_a_res_max_hp INT(11) DEFAULT NULL,
    p_a_res_max_hp INT(11) DEFAULT NULL,
    protagonist_type VARCHAR(100) DEFAULT NULL,
    id_protagonist INT(11) DEFAULT NULL,
    target_type VARCHAR(100) DEFAULT NULL,
    id_target INT(11) DEFAULT NULL,
    w_a_id INT(11) DEFAULT NULL,
    w_a_id_species INT(11) DEFAULT NULL,
    w_a_species VARCHAR(100) DEFAULT NULL,
    w_a_lvl INT(11) DEFAULT NULL,
    p_a_id INT(11) DEFAULT NULL,
    p_a_id_species INT(11) DEFAULT NULL,
    p_a_species VARCHAR(100) DEFAULT NULL,
    p_a_lvl INT(11) DEFAULT NULL,
    p_a_nickname VARCHAR(100) DEFAULT NULL,
    resulting_battle_status VARCHAR(10) DEFAULT NULL,
    w_a_id_element INT(11) DEFAULT NULL,
    p_a_id_element INT(11) DEFAULT NULL,
    p_a_cur_exp INT(11) DEFAULT 0,
    PRIMARY KEY (id_battle_party_pve_move),
    KEY idx_bpp_moves_battle_turn (id_battle_party_pve, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Party PvE simultaneous turn system: each alive party member stages an action
-- for the current round; the round resolves (party actions + wild's N actions,
-- one per alive party member) once everyone confirms.
CREATE TABLE IF NOT EXISTS playanimaster_db.battles_party_pve_turn_choices (
    id_battle_party_pve_turn_choice INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_party_pve INT(11) NOT NULL,
    round INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    action_type VARCHAR(20) NOT NULL,
    action_id INT(11) NOT NULL DEFAULT 0,
    id_item_type_selected INT(11) NOT NULL DEFAULT 0,
    flg_confirmed CHAR(1) NOT NULL DEFAULT 'N',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_party_pve_turn_choice),
    UNIQUE KEY uniq_bpp_turn_choice (id_battle_party_pve, round, id_user_ig),
    KEY idx_bpp_turn_choice_battle (id_battle_party_pve, round)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Party PvE inactivity-vote (opt-in per party via parties.flg_allow_inactivity_vote):
-- if a party member hasn't staged any action for the current round after a fixed
-- delay (costanti.party_pve_inactivity_vote_delay_seconds), other alive+active
-- members who HAVE already staged their own action this round may vote Y/N on
-- forcing that player's animal to perform a random valid ability. Majority wins;
-- on a tie, the party leader's own cast vote (if any) decides. The moment the
-- target stages a real action, all vote rows against them for the round are
-- deleted (see animaster_party_pve_save_turn_choice).
ALTER TABLE playanimaster_db.parties
    ADD COLUMN flg_allow_inactivity_vote CHAR(1) NOT NULL DEFAULT 'N' AFTER max_members;

ALTER TABLE playanimaster_db.battles_party_pve
    ADD COLUMN dt_round_started TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER current_turn;

CREATE TABLE IF NOT EXISTS playanimaster_db.battles_party_pve_inactivity_votes (
    id_battle_party_pve_inactivity_vote INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_party_pve INT(11) NOT NULL,
    round INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    id_user_ig_voter INT(11) NOT NULL,
    vote_choice CHAR(1) NOT NULL DEFAULT 'Y' COMMENT 'Y=force random action, N=keep waiting',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_party_pve_inactivity_vote),
    UNIQUE KEY uniq_bpp_inactivity_vote (id_battle_party_pve, round, id_user_ig_target, id_user_ig_voter),
    KEY idx_bpp_inactivity_vote_lookup (id_battle_party_pve, round, id_user_ig_target)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO costanti (costante, valore)
SELECT 'party_pve_inactivity_vote_delay_seconds', 45
WHERE NOT EXISTS (
    SELECT 1 FROM costanti WHERE costante = 'party_pve_inactivity_vote_delay_seconds'
);

INSERT INTO costanti (costante, valore)
SELECT 'request_distance_trade', 50
WHERE NOT EXISTS (
    SELECT 1 FROM costanti WHERE costante = 'request_distance_trade'
);

INSERT INTO costanti (costante, valore)
SELECT 'request_distance_party', 1000
WHERE NOT EXISTS (
    SELECT 1 FROM costanti WHERE costante = 'request_distance_party'
);

INSERT INTO costanti (costante, valore)
SELECT 'request_distance_duel', 50
WHERE NOT EXISTS (
    SELECT 1 FROM costanti WHERE costante = 'request_distance_duel'
);

INSERT INTO language_texts (dt_c, tag, text, text_it, text_pt)
SELECT '2026-07-11 20:00:00', 'trade.error_range', 'Player is too far away.', 'Giocatore troppo lontano.', 'Jogador demasiado longe.'
WHERE NOT EXISTS (
    SELECT 1 FROM language_texts WHERE tag = 'trade.error_range'
);


-- Module 04 (Quests): runtime engine on top of the existing quests/user_quests/
-- quest_requirements schema. See docs/modules/004_QUESTS.md.

-- Localized name/description for quests (baseline `quest` column stays English-only).
ALTER TABLE playanimaster_db.quests
    ADD COLUMN quest_it VARCHAR(200) DEFAULT NULL AFTER quest,
    ADD COLUMN quest_pt VARCHAR(200) DEFAULT NULL AFTER quest_it,
    ADD COLUMN description VARCHAR(1000) DEFAULT NULL AFTER quest_pt,
    ADD COLUMN description_it VARCHAR(1000) DEFAULT NULL AFTER description,
    ADD COLUMN description_pt VARCHAR(1000) DEFAULT NULL AFTER description_it;

-- Completion flag; `phase` already exists and now also carries a synthetic
-- "awaiting turn-in" value of MAX(quest_objectives.phase) + 1 once every
-- objective of the final phase is met. One row per (user, quest): repeatable
-- quests reset phase/flg_completed on restart instead of inserting a new row.
ALTER TABLE playanimaster_db.user_quests
    ADD COLUMN flg_completed CHAR(1) NOT NULL DEFAULT 'N' AFTER phase,
    ADD COLUMN dt_completed TIMESTAMP NULL DEFAULT NULL AFTER flg_completed,
    ADD UNIQUE KEY uniq_user_quests_user_quest (id_user_ig, id_quest);

CREATE TABLE IF NOT EXISTS playanimaster_db.quest_objectives (
    id_quest_objective INT(11) NOT NULL AUTO_INCREMENT,
    id_quest INT(11) NOT NULL,
    phase INT(11) NOT NULL DEFAULT 1,
    sort_order INT(11) NOT NULL DEFAULT 0,
    objective_type VARCHAR(30) NOT NULL COMMENT 'kill_species | collect_item | talk_npc | reach_level',
    target_ref INT(11) DEFAULT NULL COMMENT 'id_species / id_item_type / id_conversation, depending on objective_type; NULL for reach_level',
    target_count INT(11) NOT NULL DEFAULT 1 COMMENT 'kill/collect count, or the target level for reach_level',
    description VARCHAR(200) DEFAULT NULL,
    description_it VARCHAR(200) DEFAULT NULL,
    description_pt VARCHAR(200) DEFAULT NULL,
    PRIMARY KEY (id_quest_objective),
    KEY idx_quest_objectives_quest_phase (id_quest, phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.user_quest_objective_progress (
    id_user_quest_objective_progress INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig INT(11) NOT NULL,
    id_quest_objective INT(11) NOT NULL,
    progress_count INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user_quest_objective_progress),
    UNIQUE KEY uniq_user_quest_objective_progress (id_user_ig, id_quest_objective)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- `conversations.flg_register` used to double as "hide this conversation once
-- finished". Split that out: flg_register keeps gating whether a finish gets
-- written to user_conversations (and can thus satisfy `conversation finished`
-- / `conversation not finished` requirements); flg_repeatable independently
-- controls whether the conversation keeps being offered by the NPC after the
-- player has finished it. Default 'N' preserves current behaviour for every
-- existing row (registered conversations still hide once finished until an
-- admin opts a specific conversation into flg_repeatable = 'S').
ALTER TABLE playanimaster_db.conversations
    ADD COLUMN flg_repeatable VARCHAR(1) NOT NULL DEFAULT 'N' AFTER flg_register;

-- Optional element filter on wild loot rows: NULL/0 = any element; otherwise
-- the drop only rolls when the defeated wild's id_element matches.
ALTER TABLE playanimaster_db.wild_animal_drop_types
    ADD COLUMN id_element INT(11) DEFAULT NULL AFTER id_species;

-- 005b: ability-driven combat buff/debuff effects (replaces abilities.effect parsing)
CREATE TABLE IF NOT EXISTS playanimaster_db.ability_effects (
    id_ability_effect INT(11) NOT NULL AUTO_INCREMENT,
    id_ability INT(11) NOT NULL,
    id_buff_definition INT(11) NOT NULL,
    effect_target ENUM('self','target') NOT NULL DEFAULT 'target',
    effect_chance TINYINT UNSIGNED NOT NULL DEFAULT 100,
    duration_turns INT(11) NOT NULL DEFAULT 3,
    sort_order INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_ability_effect),
    KEY idx_ability_effects_ability (id_ability),
    KEY idx_ability_effects_definition (id_buff_definition),
    CONSTRAINT fk_ability_effects_ability FOREIGN KEY (id_ability) REFERENCES abilities (id_ability),
    CONSTRAINT fk_ability_effects_definition FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE playanimaster_db.battle_turn_buffs
    ADD COLUMN id_ability_effect INT(11) NULL AFTER id_buff_definition;

-- Multi-stat buff definitions: comma-separated stat_key / modifier_value pairs + tier column
ALTER TABLE playanimaster_db.buff_definitions
    MODIFY COLUMN stat_key VARCHAR(100) NOT NULL COMMENT 'Comma-separated stat keys, e.g. atk,def,spd',
    MODIFY COLUMN modifier_value VARCHAR(100) NOT NULL DEFAULT '0' COMMENT 'Comma-separated values aligned with stat_key',
    ADD COLUMN tier INT(11) NOT NULL DEFAULT 0 AFTER modifier_value;

ALTER TABLE playanimaster_db.buff_definitions
    ADD COLUMN icon VARCHAR(50) NULL DEFAULT NULL COMMENT 'HUD/combat badge glyph' AFTER tier;



-- 005c: unified combat schema. One battles row per fight (any battle_type),
-- N participants per alliance on battle_participants, planning choices on
-- battle_round_choices, append-only execution log on battle_moves.
-- Replaces battles_solo_pve*, battles_pvp*, battles_party_pve* (dropped in
-- Phase 6 of docs/modules/005c_full_combat_unification.md after cutover).
CREATE TABLE IF NOT EXISTS playanimaster_db.battles (
    id_battle INT(11) NOT NULL AUTO_INCREMENT,
    battle_type VARCHAR(32) NOT NULL COMMENT 'solo_pve | party_pve | pvp_duel | party_vs_party | dungeon | raid | pk_zone',
    planning_mode VARCHAR(24) NOT NULL COMMENT 'instant | simultaneous_submit | simultaneous_confirm',
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=ongoing, F=finished, X=cancelled',
    current_round INT(11) NOT NULL DEFAULT 0,
    id_zone INT(11) DEFAULT NULL,
    id_user_ig_initiator INT(11) DEFAULT NULL,
    id_party_a INT(11) DEFAULT NULL,
    id_party_b INT(11) DEFAULT NULL,
    id_duel_request INT(11) DEFAULT NULL,
    id_winner_alliance CHAR(1) DEFAULT NULL COMMENT 'A | B | NULL',
    end_reason VARCHAR(50) DEFAULT NULL,
    dt_round_started DATETIME DEFAULT NULL,
    dt_created TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_finished TIMESTAMP NULL DEFAULT NULL,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    context_json JSON DEFAULT NULL COMMENT 'Mode-specific extras: dungeon_run_id, encounter_id, pk_flag, reward_split, etc.',
    PRIMARY KEY (id_battle),
    KEY idx_battles_type_status (battle_type, flg_status),
    KEY idx_battles_party_a (id_party_a, flg_status),
    KEY idx_battles_party_b (id_party_b, flg_status),
    KEY idx_battles_zone (id_zone, flg_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_participants (
    id_battle_participant INT(11) NOT NULL AUTO_INCREMENT,
    id_battle INT(11) NOT NULL,
    side CHAR(1) NOT NULL COMMENT 'A | B (alliance)',
    participant_kind VARCHAR(20) NOT NULL COMMENT 'player_animal | wild | scripted',
    id_user_ig INT(11) DEFAULT NULL COMMENT 'owner, when participant_kind = player_animal',
    id_animal INT(11) DEFAULT NULL COMMENT 'animals.id_animal, when participant_kind = player_animal',
    id_wild_animal INT(11) DEFAULT NULL COMMENT 'overworld wild lock, when participant_kind = wild',
    id_species INT(11) DEFAULT NULL,
    id_element INT(11) DEFAULT NULL,
    entity_type VARCHAR(16) NOT NULL COMMENT 'animal | wild | user_ig (for battle_turn_buffs / MoveResolver)',
    id_entity INT(11) NOT NULL COMMENT 'id_animal, id_wild_animal, or id_user_ig depending on entity_type',
    team_position TINYINT(3) UNSIGNED DEFAULT NULL,
    slot_label VARCHAR(32) DEFAULT NULL COMMENT 'active | bench | boss_part',
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    flg_fainted CHAR(1) NOT NULL DEFAULT 'N',
    current_hp INT(11) NOT NULL,
    max_hp INT(11) NOT NULL COMMENT 'battle snapshot; buffed values allowed in-fight',
    atk INT(11) NOT NULL,
    def INT(11) NOT NULL,
    matk INT(11) NOT NULL,
    mdef INT(11) NOT NULL,
    acc INT(11) NOT NULL,
    eva INT(11) NOT NULL,
    cr INT(11) NOT NULL,
    spd INT(11) NOT NULL,
    lvl INT(11) NOT NULL,
    nickname VARCHAR(100) DEFAULT NULL,
    species_name VARCHAR(100) DEFAULT NULL,
    experience INT(11) NOT NULL DEFAULT 0 COMMENT 'player_animal exp snapshot for rewards',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_participant),
    KEY idx_bp_battle_side (id_battle, side, flg_active),
    KEY idx_bp_battle_entity (id_battle, entity_type, id_entity),
    KEY idx_bp_user (id_user_ig, id_battle),
    CONSTRAINT fk_bp_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_round_choices (
    id_battle_round_choice INT(11) NOT NULL AUTO_INCREMENT,
    id_battle INT(11) NOT NULL,
    round INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_battle_participant INT(11) NOT NULL COMMENT 'acting fighter',
    action_type VARCHAR(20) NOT NULL COMMENT 'ability | switch | item | flee',
    action_id INT(11) NOT NULL DEFAULT 0,
    id_item_type_selected INT(11) DEFAULT NULL,
    flg_confirmed CHAR(1) NOT NULL DEFAULT 'N',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_round_choice),
    UNIQUE KEY uq_brc_battle_round_actor (id_battle, round, id_battle_participant),
    KEY idx_brc_battle_round (id_battle, round),
    CONSTRAINT fk_brc_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle),
    CONSTRAINT fk_brc_participant FOREIGN KEY (id_battle_participant) REFERENCES battle_participants (id_battle_participant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_moves (
    id_battle_move INT(11) NOT NULL AUTO_INCREMENT,
    id_battle INT(11) NOT NULL,
    round INT(11) NOT NULL,
    order_in_turn INT(11) NOT NULL,
    id_actor_participant INT(11) NOT NULL,
    id_target_participant INT(11) DEFAULT NULL,
    id_user_ig_actor INT(11) DEFAULT NULL,
    move_type VARCHAR(100) NOT NULL,
    id_rif INT(11) DEFAULT NULL,
    move_speed DECIMAL(10,2) DEFAULT NULL,
    move_description VARCHAR(255) DEFAULT NULL,
    move_hit CHAR(1) DEFAULT NULL,
    actor_hp_after INT(11) DEFAULT NULL,
    target_hp_after INT(11) DEFAULT NULL,
    resulting_battle_status VARCHAR(16) DEFAULT NULL COMMENT 'ongoing | win | defeat | fled | pvp_end | ...',
    meta_json JSON DEFAULT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_move),
    KEY idx_bm_battle_round (id_battle, round, order_in_turn),
    CONSTRAINT fk_bm_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_inactivity_votes (
    id_battle_inactivity_vote INT(11) NOT NULL AUTO_INCREMENT,
    id_battle INT(11) NOT NULL,
    round INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    id_user_ig_voter INT(11) NOT NULL,
    vote_choice CHAR(1) NOT NULL DEFAULT 'Y' COMMENT 'Y=force random action, N=keep waiting',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_inactivity_vote),
    UNIQUE KEY uq_biv (id_battle, round, id_user_ig_target, id_user_ig_voter),
    CONSTRAINT fk_biv_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Overworld tile & terrain system (sidequest): DB-driven ground/landmark
-- images + difficult terrain (move_speed_mult) + barriers (is_walkable),
-- optionally mixed within a single tile via collision_mask (e.g. a round
-- ruin structure blocking only the middle of an otherwise-walkable tile).
-- tile_definitions is the catalog (image + gameplay properties); world_tiles
-- is a sparse per-zone grid placement table. Empty cells are filled
-- client-side by a deterministic hash pick among is_base_pack='S' rows for
-- that zone (or NULL id_zone) -- no DB row needed for plain ground.
--
-- Layers (tile_layers): a fixed, SQL-seeded set (no admin CRUD) that lets a
-- cell stack several tiles -- a ground tile plus any number of overlay
-- tiles (props/structures/canopy) drawn above it. Only the ground layer
-- (is_ground='S') gets deterministic base-pack fill; overlay layers are
-- sparse/explicit-only. Terrain composes cumulatively across layers: a
-- cell is blocked if the ground OR any placed overlay says blocked (per its
-- own is_walkable/collision_mask), and move_speed_mult multiplies across
-- every layer present -- layers can only add restrictions, never remove
-- the ground's.
CREATE TABLE IF NOT EXISTS playanimaster_db.tile_layers (
    id_tile_layer INT(11) NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL COMMENT 'unique human key, e.g. ground, props',
    name VARCHAR(100) NOT NULL COMMENT 'display label for the admin editor layer list',
    z_order INT(11) NOT NULL DEFAULT 0 COMMENT 'render/paint order, lower drawn first (below)',
    is_ground CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'S = the base ground layer (exactly one row): always covers every cell via explicit placement or deterministic base-pack fill',
    sort_order INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_tile_layer),
    UNIQUE KEY uq_tile_layers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.tile_definitions (
    id_tile_definition INT(11) NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL COMMENT 'unique human key, e.g. grass_1',
    image_file VARCHAR(150) NOT NULL COMMENT 'PNG filename under client/img/tiles/',
    category VARCHAR(20) NOT NULL DEFAULT 'base_pack' COMMENT 'base_pack | landmark | terrain (admin palette grouping only)',
    id_zone INT(11) DEFAULT NULL COMMENT 'NULL = usable as base-pack filler in any zone',
    id_tile_layer INT(11) DEFAULT NULL COMMENT 'NULL = usable on any layer; set to scope this definition to one layer in the admin palette',
    is_base_pack CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'S = eligible for deterministic random fill of empty cells (ground layer only)',
    is_walkable CHAR(1) NOT NULL DEFAULT 'S' COMMENT 'N = barrier, blocks movement; whole-tile default when collision_mask is NULL',
    move_speed_mult DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'applied to move_speed while standing on this tile; <1 = difficult terrain; multiplies across layers present on a cell',
    collision_mask VARCHAR(80) DEFAULT NULL COMMENT 'optional 8x8 row-major walkable(1)/blocked(0) grid (64 chars) for sub-tile barrier shapes; overrides is_walkable per sub-cell when set, NULL = whole tile uses is_walkable',
    sort_order INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_tile_definition),
    UNIQUE KEY uq_tile_definitions_code (code),
    KEY idx_tile_definitions_zone_pack (id_zone, is_base_pack),
    CONSTRAINT fk_td_tile_layer FOREIGN KEY (id_tile_layer) REFERENCES tile_layers (id_tile_layer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.world_tiles (
    id_world_tile INT(11) NOT NULL AUTO_INCREMENT,
    id_zone INT(11) NOT NULL,
    grid_x INT(11) NOT NULL COMMENT 'floor(world_x / TILE_WORLD_SIZE)',
    grid_z INT(11) NOT NULL COMMENT 'floor(world_z / TILE_WORLD_SIZE)',
    id_tile_layer INT(11) NOT NULL COMMENT 'which layer this placement occupies; one placement per (zone, cell, layer)',
    id_tile_definition INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_world_tile),
    UNIQUE KEY uq_world_tiles_cell (id_zone, grid_x, grid_z, id_tile_layer),
    KEY idx_world_tiles_zone (id_zone),
    CONSTRAINT fk_wt_tile_definition FOREIGN KEY (id_tile_definition) REFERENCES tile_definitions (id_tile_definition),
    CONSTRAINT fk_wt_tile_layer FOREIGN KEY (id_tile_layer) REFERENCES tile_layers (id_tile_layer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Large multi-cell objects (e.g. a house): unlike tile_definitions (always
-- exactly one grid cell), an object has its own world-unit footprint
-- (width_world/height_world) so it renders at its intended size regardless
-- of the source PNG's native pixel resolution -- same "canvas stretches to
-- destination size" approach tiles already use. anchor_x/anchor_y (0..1
-- fraction of the image) locate which point of the image sits on the
-- placement's grid cell -- default 0.5/1.0 = bottom-center, so the object
-- "stands" on its origin cell. v1 collision is a simple bounding rectangle
-- (no per-object mask).
CREATE TABLE IF NOT EXISTS playanimaster_db.object_definitions (
    id_object_definition INT(11) NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL COMMENT 'unique human key, e.g. house_1',
    image_file VARCHAR(150) NOT NULL COMMENT 'PNG filename under client/img/objects/',
    width_world DECIMAL(6,2) NOT NULL DEFAULT 25.00 COMMENT 'rendered footprint width in world units (TILE_WORLD_SIZE=25 per cell)',
    height_world DECIMAL(6,2) NOT NULL DEFAULT 25.00 COMMENT 'rendered footprint height in world units',
    anchor_x DECIMAL(3,2) NOT NULL DEFAULT 0.50 COMMENT 'fraction (0..1) of image width aligned to the placement grid cell',
    anchor_y DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT 'fraction (0..1) of image height aligned to the placement grid cell',
    is_walkable CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'N = the whole footprint rectangle blocks movement (v1: no sub-object mask)',
    sort_order INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_object_definition),
    UNIQUE KEY uq_object_definitions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.world_objects (
    id_world_object INT(11) NOT NULL AUTO_INCREMENT,
    id_zone INT(11) NOT NULL,
    grid_x INT(11) NOT NULL COMMENT 'anchor cell (see object_definitions.anchor_x/anchor_y)',
    grid_z INT(11) NOT NULL,
    id_object_definition INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_world_object),
    KEY idx_world_objects_zone (id_zone),
    CONSTRAINT fk_wo_object_definition FOREIGN KEY (id_object_definition) REFERENCES object_definitions (id_object_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Party max size driven by costanti.party_max_members (currently 7; raise the
-- costanti row later without a code deploy). Capacity checks read the live
-- constant; parties.max_members is written on create and bumped here so
-- existing parties are not stuck at the old default of 4.
INSERT INTO costanti (costante, valore)
SELECT 'party_max_members', 7
WHERE NOT EXISTS (
    SELECT 1 FROM costanti WHERE costante = 'party_max_members'
);

UPDATE parties SET max_members = 7 WHERE max_members < 7;

ALTER TABLE parties
    MODIFY COLUMN max_members TINYINT(3) UNSIGNED NOT NULL DEFAULT 7;




-- Tile catalog pack tag: groups imported tilesets in the admin palette filter.
-- Empty string = unassigned / legacy rows. Run once on live/testing.
ALTER TABLE playanimaster_db.tile_definitions
    ADD COLUMN pack VARCHAR(50) NOT NULL DEFAULT ''
        COMMENT 'tileset/pack key for admin palette filtering (e.g. forest, dungeon_a)'
        AFTER category,
    ADD KEY idx_tile_definitions_pack (pack);

-- Economy & Shops (module 010_ECONOMY): new tables for live/testing DBs
-- that already exist (mirrors CREATE TABLE blocks added to 00_tables.sql).
CREATE TABLE IF NOT EXISTS playanimaster_db.shops (
    id_shop INT(11) NOT NULL AUTO_INCREMENT,
    shop_key VARCHAR(50) DEFAULT NULL COMMENT 'optional unique human key for dev/debug reference',
    name VARCHAR(100) NOT NULL,
    name_it VARCHAR(100) DEFAULT NULL,
    name_pt VARCHAR(100) DEFAULT NULL,
    shop_type VARCHAR(30) NOT NULL DEFAULT 'general' COMMENT 'flavor/filter only: general | potion | tackle | ...',
    flg_buys_from_player CHAR(1) NOT NULL DEFAULT 'S' COMMENT 'S = this vendor buys sellable items from the player (enables Sell tab)',
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_shop),
    UNIQUE KEY uq_shops_shop_key (shop_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.shop_items (
    id_shop_item INT(11) NOT NULL AUTO_INCREMENT,
    id_shop INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    price_override INT(11) DEFAULT NULL COMMENT 'NULL = use item_types.price for this shop',
    sell_price_override INT(11) DEFAULT NULL COMMENT 'NULL = use item_types.sell_price for this shop',
    stock_qty INT(11) DEFAULT NULL COMMENT 'NULL = unlimited stock; else decremented on buy',
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    sort_order INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_shop_item),
    UNIQUE KEY uq_shop_items_shop_item_type (id_shop, id_item_type),
    KEY idx_shop_items_item_type (id_item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.shop_transactions (
    id_shop_transaction INT(11) NOT NULL AUTO_INCREMENT,
    id_shop INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    direction VARCHAR(4) NOT NULL COMMENT 'BUY | SELL',
    quantity INT(11) NOT NULL,
    unit_price INT(11) NOT NULL,
    total_gold INT(11) NOT NULL,
    gold_after INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_shop_transaction),
    KEY idx_shop_tx_user (id_user_ig, dt_c),
    KEY idx_shop_tx_shop (id_shop, dt_c)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- item_types.flg_buyable: independent of flg_sellable, gates the Buy tab in
-- shop.js / animaster_shop_fetch. Backfill sets it to 'N' for any item with
-- no usable price so existing free/quest-material rows don't show as buyable.
ALTER TABLE playanimaster_db.item_types
    ADD COLUMN flg_buyable CHAR(1) NOT NULL DEFAULT 'S'
    COMMENT 'S = purchasable from vendors (independent of flg_sellable); NULL/0 price still blocks a buy regardless' AFTER flg_sellable;

UPDATE playanimaster_db.item_types SET flg_buyable = 'N' WHERE price IS NULL OR price <= 0;

-- ... 
-- LAUNCHED ON PRODUCTION UP TO HERE 
-- ...
 