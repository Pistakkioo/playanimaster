-- CREATE DATABASE `playanimaster_db`; /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

-- playanimaster_db.abilities definition

CREATE TABLE `abilities` (
  `id_ability` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `ability` varchar(100) DEFAULT NULL,
  `descrizione` varchar(300) DEFAULT NULL,
  `accuracy` int(11) DEFAULT NULL,
  `power` int(11) DEFAULT NULL,
  `m_power` int(11) DEFAULT NULL,
  `effect` varchar(100) DEFAULT NULL,
  `effect_chance` int(11) DEFAULT NULL,
  `ability_it` varchar(100) DEFAULT NULL,
  `descrizione_it` varchar(300) DEFAULT NULL,
  `ability_pt` varchar(100) DEFAULT NULL,
  `descrizione_pt` varchar(300) DEFAULT NULL,
  `id_element` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_ability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.animals definition

CREATE TABLE `animals` (
  `id_animal` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_species` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `lvl` int(11) DEFAULT NULL,
  `team_position` int(11) DEFAULT NULL,
  `flg_captured` varchar(1) DEFAULT NULL,
  `id_image` int(11) DEFAULT NULL,
  `dna_atk` int(11) DEFAULT NULL,
  `dna_def` int(11) DEFAULT NULL,
  `dna_matk` int(11) DEFAULT NULL,
  `dna_mdef` int(11) DEFAULT NULL,
  `dna_hp` int(11) DEFAULT NULL,
  `dna_acc` int(11) DEFAULT NULL,
  `dna_eva` int(11) DEFAULT NULL,
  `dna_cr` int(11) DEFAULT NULL,
  `dna_spd` int(11) DEFAULT NULL,
  `pt_atk` int(11) DEFAULT NULL,
  `pt_def` int(11) DEFAULT NULL,
  `pt_matk` int(11) DEFAULT NULL,
  `pt_mdef` int(11) DEFAULT NULL,
  `pt_hp` int(11) DEFAULT NULL,
  `pt_acc` int(11) DEFAULT NULL,
  `pt_eva` int(11) DEFAULT NULL,
  `pt_cr` int(11) DEFAULT NULL,
  `pt_spd` int(11) DEFAULT NULL,
  `xp_atk` int(11) DEFAULT NULL,
  `xp_def` int(11) DEFAULT NULL,
  `xp_matk` int(11) DEFAULT NULL,
  `xp_mdef` int(11) DEFAULT NULL,
  `xp_hp` int(11) DEFAULT NULL,
  `xp_acc` int(11) DEFAULT NULL,
  `xp_eva` int(11) DEFAULT NULL,
  `xp_cr` int(11) DEFAULT NULL,
  `xp_spd` int(11) DEFAULT NULL,
  `current_hp` int(11) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `max_hp` int(11) DEFAULT NULL,
  `experience` int(11) DEFAULT NULL,
  `id_element` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_animal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.battles_solo_pve definition

CREATE TABLE `battles_solo_pve` (
  `id_battle_solo_pve` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_zone` int(11) DEFAULT NULL,
  `id_wild_animal` int(11) DEFAULT NULL,
  `id_user_animal` int(11) DEFAULT NULL,
  `finished` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`id_battle_solo_pve`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.battles_solo_pve_moves definition

CREATE TABLE `battles_solo_pve_moves` (
  `id_battle_solo_pve_move` int(11) NOT NULL AUTO_INCREMENT,
  `id_battle_solo_pve` int(11) DEFAULT NULL,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `turn` int(11) DEFAULT NULL,
  `move_type` varchar(100) DEFAULT NULL,
  `move_speed` int(11) DEFAULT NULL,
  `order_in_turn` int(11) DEFAULT NULL,
  `id_rif` int(11) DEFAULT NULL,
  `move_description` varchar(200) DEFAULT NULL,
  `move_hit` varchar(1) DEFAULT NULL,
  `w_a_res_hp` int(11) DEFAULT NULL,
  `p_a_res_hp` int(11) DEFAULT NULL,
  `w_a_res_atk` decimal(10,2) DEFAULT NULL,
  `w_a_res_def` decimal(10,2) DEFAULT NULL,
  `w_a_res_matk` decimal(10,2) DEFAULT NULL,
  `w_a_res_mdef` decimal(10,2) DEFAULT NULL,
  `w_a_res_acc` int(11) DEFAULT NULL,
  `w_a_res_eva` int(11) DEFAULT NULL,
  `w_a_res_cr` int(11) DEFAULT NULL,
  `w_a_res_spd` decimal(10,2) DEFAULT NULL,
  `p_a_res_atk` decimal(10,2) DEFAULT NULL,
  `p_a_res_def` decimal(10,2) DEFAULT NULL,
  `p_a_res_matk` decimal(10,2) DEFAULT NULL,
  `p_a_res_mdef` decimal(10,2) DEFAULT NULL,
  `p_a_res_acc` int(11) DEFAULT NULL,
  `p_a_res_eva` int(11) DEFAULT NULL,
  `p_a_res_cr` int(11) DEFAULT NULL,
  `p_a_res_spd` decimal(10,2) DEFAULT NULL,
  `w_a_res_max_hp` int(11) DEFAULT NULL,
  `p_a_res_max_hp` int(11) DEFAULT NULL,
  `protagonist_type` varchar(100) DEFAULT NULL,
  `id_protagonist` int(11) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `id_target` int(11) DEFAULT NULL,
  `w_a_id` int(11) DEFAULT NULL,
  `w_a_id_species` int(11) DEFAULT NULL,
  `w_a_species` varchar(100) DEFAULT NULL,
  `w_a_lvl` int(11) DEFAULT NULL,
  `p_a_id` int(11) DEFAULT NULL,
  `p_a_id_species` int(11) DEFAULT NULL,
  `p_a_species` varchar(100) DEFAULT NULL,
  `p_a_lvl` int(11) DEFAULT NULL,
  `p_a_nickname` varchar(100) DEFAULT NULL,
  `resulting_battle_status` varchar(10) DEFAULT NULL,
  `w_a_id_element` int(11) DEFAULT NULL,
  `p_a_id_element` int(11) DEFAULT NULL,
  `p_a_cur_exp` int(11) DEFAULT '0',
  PRIMARY KEY (`id_battle_solo_pve_move`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.classes definition

CREATE TABLE `classes` (
  `id_class` int(11) NOT NULL AUTO_INCREMENT,
  `class` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.consequences definition

CREATE TABLE `consequences` (
  `id_consequence` int(11) NOT NULL AUTO_INCREMENT,
  `consequence_type` varchar(100) DEFAULT NULL,
  `id_ref` int(11) DEFAULT '0',
  `ref` varchar(100) DEFAULT NULL,
  `num` int(11) DEFAULT '1',
  PRIMARY KEY (`id_consequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.conversation_requirements definition

CREATE TABLE `conversation_requirements` (
  `id_conversation_requirement` int(11) NOT NULL AUTO_INCREMENT,
  `id_conversation` int(11) DEFAULT NULL,
  `id_requirement` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_conversation_requirement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.conversations definition

CREATE TABLE `conversations` (
  `id_conversation` int(11) NOT NULL AUTO_INCREMENT,
  `id_npc` int(11) DEFAULT NULL,
  `visible` varchar(1) DEFAULT 'S',
  `title` varchar(200) DEFAULT NULL,
  `title_it` varchar(200) DEFAULT NULL,
  `title_pt` varchar(200) DEFAULT NULL,
  `flg_register` varchar(1) DEFAULT 'N',
  PRIMARY KEY (`id_conversation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.costanti definition

CREATE TABLE `costanti` (
  `id_costante` int(11) NOT NULL AUTO_INCREMENT,
  `costante` varchar(100) DEFAULT NULL,
  `valore` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_costante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.dialogues definition

CREATE TABLE `dialogues` (
  `id_dialog` int(11) NOT NULL AUTO_INCREMENT,
  `id_conversation` int(11) DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `flg_last` varchar(1) DEFAULT 'N',
  `flg_options` varchar(1) DEFAULT 'N',
  `dialog` varchar(500) DEFAULT NULL,
  `dialog_it` varchar(500) DEFAULT NULL,
  `dialog_pt` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_dialog`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.dialogues_options definition

CREATE TABLE `dialogues_options` (
  `id_dialog_option` int(11) NOT NULL AUTO_INCREMENT,
  `id_dialog` int(11) DEFAULT NULL,
  `option_n` int(11) DEFAULT '1',
  `option` varchar(100) DEFAULT NULL,
  `option_it` varchar(100) DEFAULT NULL,
  `option_pt` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_dialog_option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.elements definition

CREATE TABLE `elements` (
  `id_element` int(11) NOT NULL AUTO_INCREMENT,
  `element` varchar(50) DEFAULT NULL,
  `element_it` varchar(50) DEFAULT NULL,
  `element_pt` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_element`),
  UNIQUE KEY `elements_UN` (`element`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.item_types definition

CREATE TABLE `item_types` (
  `id_item_type` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `item_type` varchar(200) DEFAULT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `descrizione` varchar(1000) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `sell_price` int(11) DEFAULT NULL,
  `use_effect` int(11) DEFAULT NULL,
  `flg_holdable` varchar(1) DEFAULT NULL,
  `flg_tradable` varchar(1) DEFAULT NULL,
  `flg_sellable` varchar(1) DEFAULT NULL,
  `flg_usable` varchar(1) DEFAULT NULL,
  `usable_on` varchar(20) DEFAULT NULL,
  `flg_stackable` varchar(1) DEFAULT NULL,
  `stack_limit` int(11) DEFAULT NULL,
  `flg_usable_in_battle` varchar(1) DEFAULT NULL,
  `flg_usable_outside_battle` varchar(1) DEFAULT NULL,
  `flg_usable_on_alive` varchar(1) DEFAULT NULL,
  `flg_usable_on_fainted` varchar(1) DEFAULT NULL,
  `nome_it` varchar(100) DEFAULT NULL,
  `nome_pt` varchar(100) DEFAULT NULL,
  `descrizione_it` varchar(1000) DEFAULT NULL,
  `descrizione_pt` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id_item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.items definition

CREATE TABLE `items` (
  `id_item` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_animal` int(11) DEFAULT NULL,
  `flg_held` varchar(1) DEFAULT NULL,
  `id_item_type` int(11) DEFAULT NULL,
  `dt_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.species_abilities definition

CREATE TABLE `species_abilities` (
  `id_species_ability` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_species` int(11) DEFAULT NULL,
  `id_ability` int(11) DEFAULT NULL,
  `unlock_lvl` int(11) DEFAULT NULL,
  `id_element` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_species_ability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.species definition

CREATE TABLE `species` (
  `id_species` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `species` varchar(100) DEFAULT NULL,
  `id_class` int(11) DEFAULT NULL,
  `id_subclass` int(11) DEFAULT NULL,
  `id_family` int(11) DEFAULT NULL,
  `tier` decimal(3,1) DEFAULT NULL,
  `base_hp` int(11) DEFAULT NULL,
  `base_atk` int(11) DEFAULT NULL,
  `base_def` int(11) DEFAULT NULL,
  `base_matk` int(11) DEFAULT NULL,
  `base_mdef` int(11) DEFAULT NULL,
  `base_spd` int(11) DEFAULT NULL,
  `base_acc` int(11) DEFAULT NULL,
  `base_eva` int(11) DEFAULT NULL,
  `base_cr` int(11) DEFAULT NULL,
  `reward_atk` int(11) DEFAULT '0',
  `reward_def` int(11) DEFAULT '0',
  `reward_matk` int(11) DEFAULT '0',
  `reward_mdef` int(11) DEFAULT '0',
  `reward_hp` int(11) DEFAULT '0',
  `reward_acc` int(11) DEFAULT '0',
  `reward_eva` int(11) DEFAULT '0',
  `reward_cr` int(11) DEFAULT '0',
  `reward_spd` int(11) DEFAULT '0',
  `reward_exp` int(11) DEFAULT '0',
  `description` varchar(100) DEFAULT NULL,
  `flg_attivo` varchar(1) DEFAULT 'N',
  `species_it` varchar(100) DEFAULT NULL,
  `species_pt` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.language_texts definition

CREATE TABLE `language_texts` (
  `id_language_text` int(11) NOT NULL AUTO_INCREMENT,
  `dt_c` timestamp NULL DEFAULT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `text` varchar(500) DEFAULT NULL,
  `text_it` varchar(500) DEFAULT NULL,
  `text_pt` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_language_text`),
  UNIQUE KEY `uniq_language_texts_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.notifications definition

CREATE TABLE `notifications` (
  `id_notification` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `item_type` varchar(100) DEFAULT NULL,
  `id_item_type` int(11) DEFAULT NULL,
  `flg_viewed` varchar(1) DEFAULT 'N',
  `dt_c` timestamp NULL DEFAULT NULL,
  `dt_m` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_notification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.npc_requirements definition

CREATE TABLE `npc_requirements` (
  `id_npc_requirement` int(11) NOT NULL AUTO_INCREMENT,
  `id_npc` int(11) DEFAULT NULL,
  `id_requirement` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_npc_requirement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.npcs definition

CREATE TABLE `npcs` (
  `id_npc` int(11) NOT NULL AUTO_INCREMENT,
  `npc` varchar(100) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `id_zone` int(11) DEFAULT '1000',
  `posx` float DEFAULT '0',
  `posy` float DEFAULT '0',
  `rangex` int(11) DEFAULT '0',
  `rangey` int(11) DEFAULT '0',
  `direction` varchar(1) DEFAULT 'D',
  `sight_distance` int(11) DEFAULT '0',
  `npc_type_prefab` varchar(100) DEFAULT 'trader',
  PRIMARY KEY (`id_npc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.quest_requirements definition

CREATE TABLE `quest_requirements` (
  `id_quest_requirement` int(11) NOT NULL AUTO_INCREMENT,
  `id_quest` int(11) DEFAULT NULL,
  `id_requirement` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_quest_requirement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 


-- playanimaster_db.quests definition

CREATE TABLE `quests` (
  `id_quest` int(11) NOT NULL AUTO_INCREMENT,
  `id_starter_npc` int(11) DEFAULT NULL,
  `quest` varchar(200) DEFAULT NULL,
  `repeatable` varchar(1) DEFAULT 'N',
  `quest_type` varchar(100) DEFAULT NULL,
  `lvl_min` int(11) DEFAULT '0',
  `lvl_max` int(11) DEFAULT '100',
  `ids_quests_required` varchar(100) DEFAULT '-1,-1',
  PRIMARY KEY (`id_quest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.requirements definition

CREATE TABLE `requirements` (
  `id_requirement` int(11) NOT NULL AUTO_INCREMENT,
  `requirement_type` varchar(100) DEFAULT NULL,
  `id_ref` int(11) DEFAULT NULL,
  `ref` varchar(100) DEFAULT NULL,
  `min` int(11) DEFAULT '0',
  `max` int(11) DEFAULT '9999',
  `descrizione` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_requirement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.storico_battles_solo_pve_moves definition

CREATE TABLE `storico_battles_solo_pve_moves` (
  `id_battle_solo_pve_move` int(11) NOT NULL DEFAULT '0',
  `id_battle_solo_pve` int(11) DEFAULT NULL,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `turn` int(11) DEFAULT NULL,
  `move_type` varchar(100) DEFAULT NULL,
  `move_speed` int(11) DEFAULT NULL,
  `order_in_turn` int(11) DEFAULT NULL,
  `id_rif` int(11) DEFAULT NULL,
  `move_description` varchar(200) DEFAULT NULL,
  `move_hit` varchar(1) DEFAULT NULL,
  `w_a_res_hp` int(11) DEFAULT NULL,
  `p_a_res_hp` int(11) DEFAULT NULL,
  `w_a_res_atk` decimal(10,2) DEFAULT NULL,
  `w_a_res_def` decimal(10,2) DEFAULT NULL,
  `w_a_res_matk` decimal(10,2) DEFAULT NULL,
  `w_a_res_mdef` decimal(10,2) DEFAULT NULL,
  `w_a_res_acc` int(11) DEFAULT NULL,
  `w_a_res_eva` int(11) DEFAULT NULL,
  `w_a_res_cr` int(11) DEFAULT NULL,
  `w_a_res_spd` decimal(10,2) DEFAULT NULL,
  `p_a_res_atk` decimal(10,2) DEFAULT NULL,
  `p_a_res_def` decimal(10,2) DEFAULT NULL,
  `p_a_res_matk` decimal(10,2) DEFAULT NULL,
  `p_a_res_mdef` decimal(10,2) DEFAULT NULL,
  `p_a_res_acc` int(11) DEFAULT NULL,
  `p_a_res_eva` int(11) DEFAULT NULL,
  `p_a_res_cr` int(11) DEFAULT NULL,
  `p_a_res_spd` decimal(10,2) DEFAULT NULL,
  `w_a_res_max_hp` int(11) DEFAULT NULL,
  `p_a_res_max_hp` int(11) DEFAULT NULL,
  `protagonist_type` varchar(100) DEFAULT NULL,
  `id_protagonist` int(11) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `id_target` int(11) DEFAULT NULL,
  `w_a_id` int(11) DEFAULT NULL,
  `w_a_element` varchar(10) DEFAULT NULL,
  `w_a_id_species` int(11) DEFAULT NULL,
  `w_a_species` varchar(100) DEFAULT NULL,
  `w_a_lvl` int(11) DEFAULT NULL,
  `p_a_id` int(11) DEFAULT NULL,
  `p_a_id_species` int(11) DEFAULT NULL,
  `p_a_species` varchar(100) DEFAULT NULL,
  `p_a_lvl` int(11) DEFAULT NULL,
  `p_a_nickname` varchar(100) DEFAULT NULL,
  `resulting_battle_status` varchar(10) DEFAULT NULL,
  `w_a_id_element` int(11) DEFAULT NULL,
  `p_a_id_element` int(11) DEFAULT NULL,
  `p_a_cur_exp` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.subclasses definition

CREATE TABLE `subclasses` (
  `id_subclass` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `subclass` varchar(100) DEFAULT NULL,
  `id_class` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_subclass`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.user_conversations definition

CREATE TABLE `user_conversations` (
  `id_user_conversation` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `id_conversation` int(11) DEFAULT NULL,
  `dt_c` timestamp NULL DEFAULT NULL,
  `dt_m` timestamp NULL DEFAULT NULL,
  `finished` varchar(1) DEFAULT 'N',
  `dt_finished` timestamp NULL DEFAULT NULL,
  `finish_option` int(11) DEFAULT '0',
  PRIMARY KEY (`id_user_conversation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.user_quests definition

CREATE TABLE `user_quests` (
  `id_user_quest` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `id_quest` int(11) DEFAULT NULL,
  `phase` int(11) DEFAULT '0',
  `dt_c` timestamp NULL DEFAULT NULL,
  `dt_m` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_user_quest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.users definition

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `password` varchar(500) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.users_ig definition

CREATE TABLE `users_ig` (
  `id_user_ig` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_zone` int(11) DEFAULT NULL,
  `flg_online` varchar(10) DEFAULT NULL,
  `last_online` timestamp NULL DEFAULT NULL,
  `position_x` decimal(10,4) DEFAULT NULL,
  `position_y` decimal(10,4) DEFAULT NULL,
  `direction` varchar(1) DEFAULT NULL,
  `exp_total` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `flg_busy` varchar(1) DEFAULT NULL,
  `flg_battling` varchar(1) DEFAULT NULL,
  `flg_trading` varchar(1) DEFAULT NULL,
  `flg_visible` varchar(1) DEFAULT NULL,
  `id_clan` int(11) DEFAULT NULL,
  `id_party` int(11) DEFAULT NULL,
  `id_zone_last_recover` int(11) DEFAULT NULL,
  `position_x_last_recover` decimal(10,4) DEFAULT NULL,
  `position_y_last_recover` decimal(10,4) DEFAULT NULL,
  `gold` int(11) DEFAULT '0',
  PRIMARY KEY (`id_user_ig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE users_ig ADD position_z DECIMAL(10,4) DEFAULT NULL;
ALTER TABLE users_ig ADD position_z_last_recover decimal(10,4) DEFAULT NULL;



-- playanimaster_db.wild_animal_drop_types definition

CREATE TABLE `wild_animal_drop_types` (
  `id_wild_animal_drop_type` int(11) NOT NULL AUTO_INCREMENT,
  `dt_c` timestamp NULL DEFAULT NULL,
  `dt_m` timestamp NULL DEFAULT NULL,
  `drop_type` varchar(100) DEFAULT NULL,
  `id_item_type` int(11) DEFAULT NULL,
  `id_species` int(11) DEFAULT NULL,
  `lvl_min` int(11) DEFAULT NULL,
  `lvl_max` int(11) DEFAULT NULL,
  `qt_min` int(11) DEFAULT NULL,
  `qt_max` int(11) DEFAULT NULL,
  `chance` int(11) DEFAULT NULL,
  `id_quest_required` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_wild_animal_drop_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.wild_animals definition

CREATE TABLE `wild_animals` (
  `id_wild_animal` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_species` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `battle_type` varchar(100) DEFAULT NULL,
  `id_battle` int(11) DEFAULT NULL,
  `atk` int(11) DEFAULT NULL,
  `def` int(11) DEFAULT NULL,
  `matk` int(11) DEFAULT NULL,
  `mdef` int(11) DEFAULT NULL,
  `hp` int(11) DEFAULT NULL,
  `acc` int(11) DEFAULT NULL,
  `eva` int(11) DEFAULT NULL,
  `cr` int(11) DEFAULT NULL,
  `xp` int(11) DEFAULT NULL,
  `spd` int(11) DEFAULT NULL,
  `id_element` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_wild_animal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.zone_animals definition

CREATE TABLE `zone_animals` (
  `id_zone_animal` int(11) NOT NULL AUTO_INCREMENT,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `dt_modifica` timestamp NULL DEFAULT NULL,
  `id_zone` int(11) DEFAULT NULL,
  `id_species` int(11) DEFAULT NULL,
  `lvl_min` int(11) DEFAULT NULL,
  `lvl_max` int(11) DEFAULT NULL,
  `chance_points` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_zone_animal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- playanimaster_db.zones definition

CREATE TABLE `zones` (
  `id_zone` int(11) NOT NULL,
  `scene_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE trade_requests (
    id_trade_request INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig_sender INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_expires DATETIME NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending, A=accepted, D=declined, X=expired, C=cancelled',
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_request),
    KEY idx_trade_req_target_pending (id_user_ig_target, flg_status, dt_expires),
    KEY idx_trade_req_sender_pending (id_user_ig_sender, flg_status, dt_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE trades (
    id_trade INT(11) NOT NULL AUTO_INCREMENT,
    id_trade_request INT(11) DEFAULT NULL,
    id_user_ig_a INT(11) NOT NULL COMMENT 'Trade request sender',
    id_user_ig_b INT(11) NOT NULL COMMENT 'Trade request target',
    gold_a INT(11) NOT NULL DEFAULT 0,
    gold_b INT(11) NOT NULL DEFAULT 0,
    flg_confirm_a CHAR(1) NOT NULL DEFAULT 'N',
    flg_confirm_b CHAR(1) NOT NULL DEFAULT 'N',
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=open, C=completed, X=cancelled',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade),
    KEY idx_trades_user_a (id_user_ig_a, flg_status),
    KEY idx_trades_user_b (id_user_ig_b, flg_status),
    KEY idx_trades_request (id_trade_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.trade_offer_items (
    id_trade_offer_item INT(11) NOT NULL AUTO_INCREMENT,
    id_trade INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 1,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_offer_item),
    UNIQUE KEY uq_trade_offer_item (id_trade, id_user_ig, id_item_type),
    KEY idx_trade_offer_trade (id_trade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.trade_item_locks (
    id_trade_item_lock INT(11) NOT NULL AUTO_INCREMENT,
    id_trade INT(11) NOT NULL,
    id_item INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_item_lock),
    UNIQUE KEY uq_trade_item_lock_item (id_item),
    KEY idx_trade_item_lock_trade (id_trade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;









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





-- Funny chat word replacements (ANIMASTER)
-- Applied server-side when a message is sent — players still type the original word.
-- Leetspeak variants (sh1t, f*ck, v4ff4ncul0, etc.) are normalized before matching.
-- Run once on playanimaster_db after chat_system.sql.

CREATE TABLE IF NOT EXISTS playanimaster_db.chat_word_replacements (
    id_chat_word_replacement INT(11) NOT NULL AUTO_INCREMENT,
    bad_word VARCHAR(80) NOT NULL COMMENT 'Lowercase word or phrase to match',
    replacement VARCHAR(80) NOT NULL COMMENT 'Funny replacement shown to everyone',
    lang_code VARCHAR(8) DEFAULT NULL COMMENT 'en, it, es, fr, de, pt, pl — metadata only; all rules are active',
    sort_order INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    PRIMARY KEY (id_chat_word_replacement),
    UNIQUE KEY uq_chat_word_bad (bad_word),
    KEY idx_chat_word_active (flg_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- playanimaster_db.conversation_consequences definition

CREATE TABLE `conversation_consequences` (
  `id_conversation_consequence` int(11) NOT NULL AUTO_INCREMENT,
  `id_conversation` int(11) DEFAULT NULL,
  `id_option` int(11) DEFAULT NULL,
  `id_consequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_conversation_consequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- playanimaster_db.spawn_points definition

CREATE TABLE `spawn_points` (
  `id_spawn_point` int(11) NOT NULL AUTO_INCREMENT,
  `id_zone` int(11) DEFAULT NULL,
  `x` float DEFAULT NULL,
  `y` float DEFAULT NULL,
  `z` float DEFAULT NULL,
  `radius` int(11) DEFAULT NULL,
  `number_of_animals` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_spawn_point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `nome_proc` varchar(100) DEFAULT NULL,
  `dt_creazione` timestamp NULL DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;










CREATE TABLE IF NOT EXISTS playanimaster_db.pvp_duel_requests (
    id_duel_request INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig_challenger INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    id_zone INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_expires DATETIME NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending, A=accepted, D=declined, X=expired, C=cancelled',
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_duel_request),
    KEY idx_duel_req_target_pending (id_user_ig_target, flg_status, dt_expires),
    KEY idx_duel_req_challenger_pending (id_user_ig_challenger, flg_status, dt_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_pvp (
    id_battle_pvp INT(11) NOT NULL AUTO_INCREMENT,
    id_duel_request INT(11) DEFAULT NULL,
    id_user_ig_a INT(11) NOT NULL COMMENT 'Challenger',
    id_user_ig_b INT(11) NOT NULL COMMENT 'Target',
    id_zone INT(11) DEFAULT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=ongoing, F=finished, X=cancelled',
    id_winner_user_ig INT(11) DEFAULT NULL,
    end_reason VARCHAR(50) DEFAULT NULL,
    awaiting_user_ig INT(11) DEFAULT NULL,
    current_turn INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_finished TIMESTAMP NULL DEFAULT NULL,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_pvp),
    KEY idx_battles_pvp_user_a (id_user_ig_a, flg_status),
    KEY idx_battles_pvp_user_b (id_user_ig_b, flg_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_pvp_moves (
    id_battle_pvp_move INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_pvp INT(11) DEFAULT NULL,
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
    p_a_cur_exp INT(11) DEFAULT '0',
    PRIMARY KEY (id_battle_pvp_move),
    KEY idx_battles_pvp_moves_battle (id_battle_pvp, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.pvp_turn_choices (
    id_turn_choice INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_pvp INT(11) NOT NULL,
    turn INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_id INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_turn_choice),
    UNIQUE KEY uniq_pvp_turn_choice (id_battle_pvp, turn, id_user_ig),
    KEY idx_pvp_turn_choice_battle (id_battle_pvp, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;










CREATE TABLE IF NOT EXISTS playanimaster_db.buff_definitions (
    id_buff_definition INT(11) NOT NULL AUTO_INCREMENT,
    buff_code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    name_it VARCHAR(100) DEFAULT NULL,
    name_pt VARCHAR(100) DEFAULT NULL,
    description VARCHAR(300) DEFAULT NULL,
    description_it VARCHAR(300) DEFAULT NULL,
    description_pt VARCHAR(300) DEFAULT NULL,
    target_entity ENUM('animal', 'user_ig') NOT NULL DEFAULT 'animal',
    stat_key VARCHAR(20) NOT NULL COMMENT 'atk, def, matk, mdef, spd, acc, eva, cr, hp, max_hp',
    modifier_kind ENUM('flat', 'percent') NOT NULL DEFAULT 'percent',
    modifier_value DECIMAL(10, 4) NOT NULL DEFAULT 0,
    is_debuff CHAR(1) NOT NULL DEFAULT 'N',
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_buff_definition),
    UNIQUE KEY uniq_buff_definitions_code (buff_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.entity_buffs (
    id_entity_buff INT(11) NOT NULL AUTO_INCREMENT,
    id_buff_definition INT(11) NOT NULL,
    entity_type ENUM('animal', 'user_ig') NOT NULL,
    id_entity INT(11) NOT NULL,
    dt_applied_utc DATETIME NOT NULL,
    dt_expires_utc DATETIME NOT NULL,
    source_type VARCHAR(50) DEFAULT NULL COMMENT 'ability, item, quest, admin',
    source_id INT(11) DEFAULT NULL,
    PRIMARY KEY (id_entity_buff),
    KEY idx_entity_buffs_active (entity_type, id_entity, dt_expires_utc),
    KEY idx_entity_buffs_definition (id_buff_definition),
    CONSTRAINT fk_entity_buffs_definition
        FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_turn_buffs (
    id_battle_turn_buff INT(11) NOT NULL AUTO_INCREMENT,
    battle_type ENUM('solo_pve', 'pvp', 'party_pve') NOT NULL,
    id_battle INT(11) NOT NULL,
    entity_type ENUM('animal', 'user_ig', 'wild') NOT NULL,
    id_entity INT(11) NOT NULL,
    id_buff_definition INT(11) NOT NULL,
    applied_at_turn INT(11) NOT NULL DEFAULT 0,
    applied_order INT(11) NOT NULL DEFAULT 0,
    turns_total INT(11) NOT NULL,
    turns_remaining INT(11) NOT NULL,
    dt_applied_utc DATETIME NOT NULL,
    PRIMARY KEY (id_battle_turn_buff),
    KEY idx_battle_turn_buffs_battle (battle_type, id_battle, turns_remaining),
    KEY idx_battle_turn_buffs_entity (entity_type, id_entity),
    CONSTRAINT fk_battle_turn_buffs_definition
        FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS playanimaster_db.pvp_duel_requests (
    id_duel_request INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig_challenger INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    id_zone INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_expires DATETIME NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending, A=accepted, D=declined, X=expired, C=cancelled',
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_duel_request),
    KEY idx_duel_req_target_pending (id_user_ig_target, flg_status, dt_expires),
    KEY idx_duel_req_challenger_pending (id_user_ig_challenger, flg_status, dt_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_pvp (
    id_battle_pvp INT(11) NOT NULL AUTO_INCREMENT,
    id_duel_request INT(11) DEFAULT NULL,
    id_user_ig_a INT(11) NOT NULL COMMENT 'Challenger',
    id_user_ig_b INT(11) NOT NULL COMMENT 'Target',
    id_zone INT(11) DEFAULT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=ongoing, F=finished, X=cancelled',
    id_winner_user_ig INT(11) DEFAULT NULL,
    end_reason VARCHAR(50) DEFAULT NULL,
    awaiting_user_ig INT(11) DEFAULT NULL,
    current_turn INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_finished TIMESTAMP NULL DEFAULT NULL,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_battle_pvp),
    KEY idx_battles_pvp_user_a (id_user_ig_a, flg_status),
    KEY idx_battles_pvp_user_b (id_user_ig_b, flg_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.battles_pvp_moves (
    id_battle_pvp_move INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_pvp INT(11) DEFAULT NULL,
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
    p_a_cur_exp INT(11) DEFAULT '0',
    PRIMARY KEY (id_battle_pvp_move),
    KEY idx_battles_pvp_moves_battle (id_battle_pvp, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.pvp_turn_choices (
    id_turn_choice INT(11) NOT NULL AUTO_INCREMENT,
    id_battle_pvp INT(11) NOT NULL,
    turn INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_id INT(11) NOT NULL DEFAULT 0,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_turn_choice),
    UNIQUE KEY uniq_pvp_turn_choice (id_battle_pvp, turn, id_user_ig),
    KEY idx_pvp_turn_choice_battle (id_battle_pvp, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS playanimaster_db.trade_requests (
    id_trade_request INT(11) NOT NULL AUTO_INCREMENT,
    id_user_ig_sender INT(11) NOT NULL,
    id_user_ig_target INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_expires DATETIME NOT NULL,
    flg_status CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending, A=accepted, D=declined, X=expired, C=cancelled',
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_request),
    KEY idx_trade_req_target_pending (id_user_ig_target, flg_status, dt_expires),
    KEY idx_trade_req_sender_pending (id_user_ig_sender, flg_status, dt_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.trades (
    id_trade INT(11) NOT NULL AUTO_INCREMENT,
    id_trade_request INT(11) DEFAULT NULL,
    id_user_ig_a INT(11) NOT NULL COMMENT 'Trade request sender',
    id_user_ig_b INT(11) NOT NULL COMMENT 'Trade request target',
    gold_a INT(11) NOT NULL DEFAULT 0,
    gold_b INT(11) NOT NULL DEFAULT 0,
    flg_confirm_a CHAR(1) NOT NULL DEFAULT 'N',
    flg_confirm_b CHAR(1) NOT NULL DEFAULT 'N',
    flg_status CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O=open, C=completed, X=cancelled',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dt_m TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade),
    KEY idx_trades_user_a (id_user_ig_a, flg_status),
    KEY idx_trades_user_b (id_user_ig_b, flg_status),
    KEY idx_trades_request (id_trade_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.trade_offer_items (
    id_trade_offer_item INT(11) NOT NULL AUTO_INCREMENT,
    id_trade INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 1,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_offer_item),
    UNIQUE KEY uq_trade_offer_item (id_trade, id_user_ig, id_item_type),
    KEY idx_trade_offer_trade (id_trade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS playanimaster_db.trade_item_locks (
    id_trade_item_lock INT(11) NOT NULL AUTO_INCREMENT,
    id_trade INT(11) NOT NULL,
    id_item INT(11) NOT NULL,
    id_user_ig INT(11) NOT NULL,
    id_item_type INT(11) NOT NULL,
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trade_item_lock),
    UNIQUE KEY uq_trade_item_lock_item (id_item),
    KEY idx_trade_item_lock_trade (id_trade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS playanimaster_db.buff_definitions (
    id_buff_definition INT(11) NOT NULL AUTO_INCREMENT,
    buff_code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    name_it VARCHAR(100) DEFAULT NULL,
    name_pt VARCHAR(100) DEFAULT NULL,
    description VARCHAR(300) DEFAULT NULL,
    description_it VARCHAR(300) DEFAULT NULL,
    description_pt VARCHAR(300) DEFAULT NULL,
    target_entity ENUM('animal', 'user_ig') NOT NULL DEFAULT 'animal',
    stat_key VARCHAR(20) NOT NULL COMMENT 'atk, def, matk, mdef, spd, acc, eva, cr, hp, max_hp',
    modifier_kind ENUM('flat', 'percent') NOT NULL DEFAULT 'percent',
    modifier_value DECIMAL(10, 4) NOT NULL DEFAULT 0,
    is_debuff CHAR(1) NOT NULL DEFAULT 'N',
    flg_active CHAR(1) NOT NULL DEFAULT 'S',
    dt_c TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_buff_definition),
    UNIQUE KEY uniq_buff_definitions_code (buff_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.entity_buffs (
    id_entity_buff INT(11) NOT NULL AUTO_INCREMENT,
    id_buff_definition INT(11) NOT NULL,
    entity_type ENUM('animal', 'user_ig') NOT NULL,
    id_entity INT(11) NOT NULL,
    dt_applied_utc DATETIME NOT NULL,
    dt_expires_utc DATETIME NOT NULL,
    source_type VARCHAR(50) DEFAULT NULL COMMENT 'ability, item, quest, admin',
    source_id INT(11) DEFAULT NULL,
    PRIMARY KEY (id_entity_buff),
    KEY idx_entity_buffs_active (entity_type, id_entity, dt_expires_utc),
    KEY idx_entity_buffs_definition (id_buff_definition),
    CONSTRAINT fk_entity_buffs_definition
        FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playanimaster_db.battle_turn_buffs (
    id_battle_turn_buff INT(11) NOT NULL AUTO_INCREMENT,
    battle_type ENUM('solo_pve', 'pvp', 'party_pve') NOT NULL,
    id_battle INT(11) NOT NULL,
    entity_type ENUM('animal', 'user_ig', 'wild') NOT NULL,
    id_entity INT(11) NOT NULL,
    id_buff_definition INT(11) NOT NULL,
    applied_at_turn INT(11) NOT NULL DEFAULT 0,
    applied_order INT(11) NOT NULL DEFAULT 0,
    turns_total INT(11) NOT NULL,
    turns_remaining INT(11) NOT NULL,
    dt_applied_utc DATETIME NOT NULL,
    PRIMARY KEY (id_battle_turn_buff),
    KEY idx_battle_turn_buffs_battle (battle_type, id_battle, turns_remaining),
    KEY idx_battle_turn_buffs_entity (entity_type, id_entity),
    CONSTRAINT fk_battle_turn_buffs_definition
        FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
