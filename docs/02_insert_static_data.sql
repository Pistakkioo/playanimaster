





INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(1, '2022-01-10 15:10:57.000', NULL, 'Scratch', 'Scratches the opponent', 100, 40, 0, 'none', 0, 'Graffio', 'Graffia l''avversario', 'Arranhao', 'Arranha o adversario', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(2, '2022-01-10 15:11:29.000', NULL, 'Bump', 'Throws itself onto the opponent', 100, 40, 0, 'none', 0, 'Scontro', 'Si butta addosso all''avversario', 'Investida', 'Atira-se ao adversario', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(3, '2022-01-12 15:16:48.000', NULL, 'Hiss', 'Hisses to scare the opponent and lower its attack ', 100, 0, 0, 'lower_target_atk_10_%', 100, 'Soffio', 'Soffia per spaventare l''avversario ed abbassare il suo attacco', 'Sopro', 'Assopra para assustar o adversario e abaixar o seu ataque', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(4, '2022-01-12 15:17:14.000', NULL, 'Growl', 'Growls to scare the opponent and lower its attack ', 100, 0, 0, 'lower_target_atk_10_%', 100, 'Ringhio', 'Ringhia per spaventare l''avversario ed abbassare il suo attacco', 'Grunhido', 'Grunhe para assustar o adversario e abaixar o seu ataque ', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(5, '2022-01-12 15:18:22.000', NULL, 'Fool Around', 'Jump and move in a playful way to lower the opponent''s defense', 100, 0, 0, 'lower_target_def_10_%', 100, 'Gioca', 'Gioca per abbassare la difesa dell''avversario', 'Brinca', 'Brinca para abaixar a defesa do adversario', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(6, '2022-01-12 15:24:21.000', NULL, 'Scrape dirt', 'Scrapes the ground throwing pebbles. Chance to lower the opponent''s accuracy', 100, 20, 0, 'lower_target_acc_10_%', 60, 'Raschia terra', 'Raschia la terra tirando sassi. Possibilita di abbassare la precisione dell''avversario ', 'Raspar Terra', 'Raspa terra atirando pedras ao adversario. Possibilidade de abaixar a pontaria do adversario', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(7, '2022-01-12 18:45:48.000', NULL, 'Peck', 'Pecks the opponent with the beak', 100, 40, 0, 'none', 0, 'Beccata', 'Becca l''avversario', 'Bicada', 'Bica o adversario', 0);
INSERT INTO playanimaster_db.abilities (id_ability, dt_creazione, dt_modifica, ability, descrizione, accuracy, power, m_power, effect, effect_chance, ability_it, descrizione_it, ability_pt, descrizione_pt, id_element) VALUES(8, '2022-01-12 18:50:23.000', NULL, 'Wing Clap', 'Claps wings and throws sand at the opponent, lowering its accuracy', 100, 0, 0, 'lower_target_acc_20_%', 100, 'Scuoti Ale', 'Scuoti le ali tirando sabbia all''avversario, abbassando la sua precisione ', 'Bater as Asas', 'Bate as asas atirando areia ao adversario para abaixar a sua pontaria', 0);




INSERT INTO playanimaster_db.language_texts (dt_c, tag, text, text_it, text_pt) VALUES
(NOW(), 'ui.loading', 'Loading…', 'Caricamento…', 'A carregar…'),
(NOW(), 'ui.back', 'Back', 'Indietro', 'Voltar'),
(NOW(), 'ui.close', 'Close', 'Chiudi', 'Fechar'),
(NOW(), 'ui.save', 'Save', 'Salva', 'Guardar'),
(NOW(), 'ui.cancel', 'Cancel', 'Annulla', 'Cancelar'),
(NOW(), 'ui.ok', 'OK', 'OK', 'OK'),
(NOW(), 'ui.use', 'Use', 'Usa', 'Usar'),
(NOW(), 'ui.next', 'Next', 'Avanti', 'Seguinte'),
(NOW(), 'ui.combat_close_title', 'Only after battle ends', 'Solo a fine lotta', 'Só após o fim da batalha'),

(NOW(), 'combat.title', 'Combat', 'Lotta', 'Combate'),
(NOW(), 'combat.flee', 'Flee', 'Fuga', 'Fugir'),
(NOW(), 'combat.fight', 'Fight', 'Combatti', 'Lutar'),
(NOW(), 'combat.fight_hint', 'Select an ability to attack.', 'Scegli un''abilità per attaccare.', 'Escolhe uma habilidade para atacar.'),
(NOW(), 'combat.items', 'Items', 'Oggetti', 'Itens'),
(NOW(), 'combat.items_hint', 'Use an item on your team.', 'Usa un oggetto sulla squadra.', 'Usa um item na tua equipa.'),
(NOW(), 'combat.team', 'Team', 'Squadra', 'Equipa'),
(NOW(), 'combat.team_hint', 'Switch to another creature.', 'Cambia creatura.', 'Troca de criatura.'),
(NOW(), 'combat.choose_action', 'Choose an action.', 'Scegli un''azione.', 'Escolhe uma ação.'),
(NOW(), 'combat.choose_ability', 'Choose an ability or flee.', 'Scegli un''abilità o fuggi.', 'Escolhe uma habilidade ou foge.'),
(NOW(), 'combat.choose_item', 'Choose an item.', 'Scegli un oggetto.', 'Escolhe um item.'),
(NOW(), 'combat.choose_switch', 'Choose a creature to switch in.', 'Scegli la creatura da mandare in campo.', 'Escolhe a criatura para entrar em campo.'),
(NOW(), 'combat.loading_turn', 'Loading turn…', 'Caricamento turno…', 'A carregar turno…'),
(NOW(), 'combat.resolving_turn', 'Resolving turn…', 'Risoluzione turno…', 'A resolver turno…'),
(NOW(), 'combat.load_failed', 'Combat load failed', 'Caricamento lotta fallito', 'Falha ao carregar combate'),
(NOW(), 'combat.no_battle_data', 'No battle data.', 'Nessun dato di lotta.', 'Sem dados de batalha.'),
(NOW(), 'combat.no_battle_data_turn', 'No battle data for this turn. Close (×) to exit.', 'Nessun dato per questo turno. Chiudi (×) per uscire.', 'Sem dados para este turno. Fecha (×) para sair.'),
(NOW(), 'combat.action_failed', 'Action failed', 'Azione fallita', 'Ação falhou'),
(NOW(), 'combat.status_victory', 'Victory! The wild animal was freed.', 'Vittoria! L''animale selvatico è stato liberato.', 'Vitória! O animal selvagem foi libertado.'),
(NOW(), 'combat.status_defeat', 'You have no usable creatures. You blacked out.', 'Non hai creature utilizzabili. Sei svenuto.', 'Não tens criaturas utilizáveis. Desmaiaste.'),
(NOW(), 'combat.status_fled', 'You fled from battle.', 'Sei fuggito dalla lotta.', 'Fugiste da batalha.'),
(NOW(), 'combat.status_ended', 'Battle ended: {status}', 'Lotta terminata: {status}', 'Batalha terminada: {status}'),
(NOW(), 'combat.status_blackout_loading', 'You have no usable creatures. You blacked out…', 'Non hai creature utilizzabili. Sei svenuto…', 'Não tens criaturas utilizáveis. Desmaiaste…'),
(NOW(), 'combat.recovery_failed', 'Recovery failed. Close (×) to continue.', 'Recupero fallito. Chiudi (×) per continuare.', 'Recuperação falhou. Fecha (×) para continuar.'),
(NOW(), 'combat.loading_abilities', 'Loading abilities…', 'Caricamento abilità…', 'A carregar habilidades…'),
(NOW(), 'combat.no_abilities', 'No abilities available.', 'Nessuna abilità disponibile.', 'Nenhuma habilidade disponível.'),
(NOW(), 'combat.load_abilities_failed', 'Could not load abilities', 'Impossibile caricare le abilità', 'Não foi possível carregar habilidades'),
(NOW(), 'combat.loading_items', 'Loading items…', 'Caricamento oggetti…', 'A carregar itens…'),
(NOW(), 'combat.no_items', 'No usable items in battle.', 'Nessun oggetto utilizzabile in lotta.', 'Nenhum item utilizável em combate.'),
(NOW(), 'combat.load_items_failed', 'Could not load items', 'Impossibile caricare gli oggetti', 'Não foi possível carregar itens'),
(NOW(), 'combat.loading_team', 'Loading team…', 'Caricamento squadra…', 'A carregar equipa…'),
(NOW(), 'combat.load_team_failed', 'Could not load team', 'Impossibile caricare la squadra', 'Não foi possível carregar equipa'),
(NOW(), 'combat.no_switch_targets', 'No other team creatures available.', 'Nessun''altra creatura in squadra disponibile.', 'Nenhuma outra criatura da equipa disponível.'),
(NOW(), 'combat.item_target_prompt', 'Use {item} on which creature?', 'Su quale creatura usare {item}?', 'Em que criatura usar {item}?'),
(NOW(), 'combat.no_item_target', 'No valid target for this item.', 'Nessun bersaglio valido per questo oggetto.', 'Nenhum alvo válido para este item.'),
(NOW(), 'combat.unit_your_animal', 'Your animal', 'La tua creatura', 'A tua criatura'),
(NOW(), 'combat.unit_wild', 'Wild', 'Selvatico', 'Selvagem'),
(NOW(), 'combat.log_turn', 'T{turn}: {description}', 'T{turn}: {description}', 'T{turn}: {description}'),
(NOW(), 'combat.press_space_continue', 'Press Space to continue…', 'Premi Spazio per continuare…', 'Prime Espaço para continuar…'),
(NOW(), 'combat.auto_advance', 'Auto-advance', 'Avanzamento automatico', 'Avanço automático'),
(NOW(), 'combat.missed', 'But it missed!', 'Ma non l''ha preso!', 'Mas falhou!'),
(NOW(), 'combat.hit', 'Hit!', 'Preso!', 'Acertou!'),
(NOW(), 'combat.critical_hit', 'Critical hit!', 'Preso in pieno!', 'Acertou em cheio!'),
(NOW(), 'combat.stat_attack', 'attack', 'attacco', 'ataque'),
(NOW(), 'combat.stat_defense', 'defense', 'difesa', 'defesa'),
(NOW(), 'combat.stat_matk', 'magic attack', 'attacco magico', 'ataque mágico'),
(NOW(), 'combat.stat_mdef', 'magic defense', 'difesa magica', 'defesa mágica'),
(NOW(), 'combat.stat_accuracy', 'accuracy', 'precisione', 'pontaria'),
(NOW(), 'combat.stat_evasion', 'evasion', 'evasione', 'evasão'),
(NOW(), 'combat.stat_critical', 'critical rate', 'probabilità critica', 'probabilidade crítica'),
(NOW(), 'combat.stat_speed', 'speed', 'velocità', 'velocidade'),
(NOW(), 'combat.stat_increased', 'increased', 'è aumentato', 'aumentou'),
(NOW(), 'combat.stat_decreased', 'decreased', 'è diminuito', 'diminuiu'),
(NOW(), 'combat.stat_changed', '{name}''s {stat} {direction}.', '{stat} di {name} {direction}.', 'O {stat} de {name} {direction}.'),

(NOW(), 'dialog.talk_button', 'Talk [Space]', 'Parla [Spazio]', 'Falar [Espaço]'),
(NOW(), 'dialog.choose_topic', 'Choose a topic', 'Scegli un argomento', 'Escolhe um tema'),
(NOW(), 'dialog.choose_topic_prompt', 'What would you like to talk about?', 'Di cosa vorresti parlare?', 'Sobre o que gostarias de falar?'),
(NOW(), 'dialog.npc_fallback', 'NPC', 'PNG', 'NPC'),
(NOW(), 'dialog.conversation_fallback', 'Conversation {id}', 'Conversazione {id}', 'Conversa {id}'),
(NOW(), 'dialog.option_fallback', 'Option {id}', 'Opzione {id}', 'Opção {id}'),

(NOW(), 'hud.help', 'WASD move · Walk into wilds to battle · Talk to NPCs · I bag · T team', 'WASD muovi · Avvicinati ai selvatici per lottare · Parla con PNG · I borsa · T squadra', 'WASD mover · Aproxima-te de selvagens para combater · Falar com NPCs · I mochila · T equipa'),
(NOW(), 'hud.team', 'Team', 'Squadra', 'Equipa'),
(NOW(), 'hud.bag', 'Bag', 'Borsa', 'Mochila'),
(NOW(), 'hud.characters', 'Characters', 'Personaggi', 'Personagens'),
(NOW(), 'hud.logout', 'Logout', 'Esci', 'Sair'),
(NOW(), 'hud.status_counts', 'Others: {others} · Wild: {wilds} · NPC: {npcs}', 'Altri: {others} · Selvatici: {wilds} · PNG: {npcs}', 'Outros: {others} · Selvagens: {wilds} · NPCs: {npcs}'),
(NOW(), 'hud.player_position', '{name} · Zone {zone} · ({x}, {z})', '{name} · Zona {zone} · ({x}, {z})', '{name} · Zona {zone} · ({x}, {z})'),
(NOW(), 'hud.default_player', 'Player', 'Giocatore', 'Jogador'),
(NOW(), 'hud.default_you', 'You', 'Tu', 'Tu'),

(NOW(), 'inventory.title', 'Inventory', 'Inventario', 'Inventário'),
(NOW(), 'inventory.empty', 'Your bag is empty.', 'La borsa è vuota.', 'A mochila está vazia.'),
(NOW(), 'inventory.load_failed', 'Could not load inventory.', 'Impossibile caricare l''inventario.', 'Não foi possível carregar inventário.'),
(NOW(), 'inventory.no_items', 'No items', 'Nessun oggetto', 'Sem itens'),
(NOW(), 'inventory.select_item', 'Select an item from the list.', 'Seleziona un oggetto dall''elenco.', 'Seleciona um item da lista.'),
(NOW(), 'inventory.item_fallback', 'Item {id}', 'Oggetto {id}', 'Item {id}'),
(NOW(), 'inventory.meta_effect', 'Effect: {effect}', 'Effetto: {effect}', 'Efeito: {effect}'),
(NOW(), 'inventory.meta_usable_on', 'Use on: {target}', 'Usa su: {target}', 'Usar em: {target}'),
(NOW(), 'inventory.cannot_use_here', 'This item cannot be used here.', 'Questo oggetto non può essere usato qui.', 'Este item não pode ser usado aqui.'),
(NOW(), 'inventory.team_picker_title', 'Use on which animal?', 'Su quale animale?', 'Em que animal?'),
(NOW(), 'inventory.no_team_animals', 'You have no team animals.', 'Non hai animali in squadra.', 'Não tens animais na equipa.'),
(NOW(), 'inventory.cannot_use_on_animal', 'You cannot use this item on that animal.', 'Non puoi usare questo oggetto su quell''animale.', 'Não podes usar este item nesse animal.'),
(NOW(), 'inventory.using_item', 'Using item…', 'Uso oggetto…', 'A usar item…'),
(NOW(), 'inventory.item_used', 'Item used on {name}.', 'Oggetto usato su {name}.', 'Item usado em {name}.'),
(NOW(), 'inventory.use_failed', 'Could not use item.', 'Impossibile usare l''oggetto.', 'Não foi possível usar item.'),

(NOW(), 'team.title', 'Team', 'Squadra', 'Equipa'),
(NOW(), 'team.empty', 'Your team is empty.', 'La squadra è vuota.', 'A equipa está vazia.'),
(NOW(), 'team.load_failed', 'Could not load team.', 'Impossibile caricare la squadra.', 'Não foi possível carregar equipa.'),
(NOW(), 'team.no_animals', 'No team animals', 'Nessun animale in squadra', 'Sem animais na equipa'),
(NOW(), 'team.nickname_label', 'Nickname', 'Soprannome', 'Alcunha'),
(NOW(), 'team.level_prefix', 'Level {level}', 'Livello {level}', 'Nível {level}'),
(NOW(), 'team.lv_short', 'Lv.{level}', 'Lv.{level}', 'Nv.{level}'),
(NOW(), 'team.element_prefix', 'Element: {element}', 'Elemento: {element}', 'Elemento: {element}'),
(NOW(), 'team.fainted', '(fainted)', '(svenuto)', '(desmaiado)'),
(NOW(), 'team.saving_nickname', 'Saving nickname…', 'Salvataggio soprannome…', 'A guardar alcunha…'),
(NOW(), 'team.nickname_saved', 'Nickname saved.', 'Soprannome salvato.', 'Alcunha guardada.'),
(NOW(), 'team.nickname_save_failed', 'Could not save nickname.', 'Impossibile salvare il soprannome.', 'Não foi possível guardar alcunha.'),
(NOW(), 'team.animal_fallback', 'Animal {id}', 'Animale {id}', 'Animal {id}'),
(NOW(), 'team.species_fallback', 'Species {id}', 'Specie {id}', 'Espécie {id}'),

(NOW(), 'world.wild_fallback', 'Wild #{id}', 'Selvatico #{id}', 'Selvagem #{id}'),
(NOW(), 'world.other_player_fallback', 'Player', 'Giocatore', 'Jogador'),

(NOW(), 'target.type_self', 'Your character', 'Il tuo personaggio', 'O teu personagem'),
(NOW(), 'target.type_player', 'Player', 'Giocatore', 'Jogador'),
(NOW(), 'target.type_npc', 'NPC', 'NPC', 'NPC'),
(NOW(), 'target.type_wild', 'Wild animal', 'Animale selvatico', 'Animal selvagem'),

(NOW(), 'stats.hp_value', '{current} / {max} HP', '{current} / {max} PS', '{current} / {max} HP'),

(NOW(), 'error.start_battle_failed', 'Could not start battle', 'Impossibile iniziare la lotta', 'Não foi possível iniciar batalha');



INSERT INTO playanimaster_db.language_texts (dt_c, tag, text, text_it, text_pt) VALUES
(NOW(), 'chat.title', 'Chat', 'Chat', 'Chat'),
(NOW(), 'chat.send', 'Send', 'Invia', 'Enviar'),
(NOW(), 'chat.empty', 'No messages in this tab.', 'Nessun messaggio in questa scheda.', 'Sem mensagens neste separador.'),
(NOW(), 'chat.input_placeholder', 'Message… @name · !zone · $clan · #party · %alliance · *global', 'Messaggio… @nome · !zona · $clan · #party · %alleanza · *globale', 'Mensagem… @nome · !zona · $clã · #party · %aliança · *global'),
(NOW(), 'chat.whisper_new_player', '@ New player…', '@ Nuovo giocatore…', '@ Novo jogador…'),
(NOW(), 'chat.position_toggle', 'Move chat left / right', 'Sposta chat a sinistra / destra', 'Mover chat esquerda / direita'),
(NOW(), 'chat.collapse', 'Collapse chat', 'Comprimi chat', 'Recolher chat'),
(NOW(), 'chat.expand', 'Expand chat', 'Espandi chat', 'Expandir chat'),
(NOW(), 'chat.minimize_icon', 'Minimize to icon', 'Riduci a icona', 'Minimizar para ícone'),
(NOW(), 'chat.restore_from_icon', 'Open chat', 'Apri chat', 'Abrir chat'),
(NOW(), 'chat.settings_open', 'Channel filters for this tab', 'Filtri canale per questa scheda', 'Filtros de canal para este separador'),
(NOW(), 'chat.settings_close', 'Done', 'Fatto', 'Concluído'),
(NOW(), 'chat.settings_title', 'Show in {tab}', 'Mostra in {tab}', 'Mostrar em {tab}'),
(NOW(), 'chat.tab_main', 'Main', 'Principale', 'Principal'),
(NOW(), 'chat.tab_whisper', 'Whisper', 'Sussurro', 'Sussurro'),
(NOW(), 'chat.tab_zone', 'Zone', 'Zona', 'Zona'),
(NOW(), 'chat.tab_clan', 'Clan', 'Clan', 'Clã'),
(NOW(), 'chat.tab_party', 'Party', 'Party', 'Party'),
(NOW(), 'chat.tab_alliance', 'Alliance', 'Alleanza', 'Aliança'),
(NOW(), 'chat.tab_global', 'Global', 'Globale', 'Global'),
(NOW(), 'chat.channel_local', 'Local', 'Locale', 'Local'),
(NOW(), 'chat.channel_whisper', 'Whisper', 'Sussurro', 'Sussurro'),
(NOW(), 'chat.channel_zone', 'Zone', 'Zona', 'Zona'),
(NOW(), 'chat.channel_clan', 'Clan', 'Clan', 'Clã'),
(NOW(), 'chat.channel_party', 'Party', 'Party', 'Party'),
(NOW(), 'chat.channel_alliance', 'Alliance', 'Alleanza', 'Aliança'),
(NOW(), 'chat.channel_global', 'Global', 'Globale', 'Global'),
(NOW(), 'chat.error_empty_message', 'Message cannot be empty.', 'Il messaggio non può essere vuoto.', 'A mensagem não pode estar vazia.'),
(NOW(), 'chat.error_too_long', 'Message is too long.', 'Messaggio troppo lungo.', 'Mensagem demasiado longa.'),
(NOW(), 'chat.error_whisper_invalid', 'Invalid whisper format. Use @Name message', 'Formato sussurro non valido. Usa @Nome messaggio', 'Formato de sussurro inválido. Usa @Nome mensagem'),
(NOW(), 'chat.error_whisper_no_message', 'Whisper needs a message after the name.', 'Il sussurro richiede un messaggio dopo il nome.', 'O sussurro precisa de uma mensagem após o nome.'),
(NOW(), 'chat.error_whisper_not_found', 'Player not found.', 'Giocatore non trovato.', 'Jogador não encontrado.'),
(NOW(), 'chat.error_whisper_offline', 'That player is offline.', 'Quel giocatore è offline.', 'Esse jogador está offline.'),
(NOW(), 'chat.error_no_clan', 'You are not in a clan.', 'Non sei in un clan.', 'Não estás num clã.'),
(NOW(), 'chat.error_no_party', 'You are not in a party.', 'Non sei in un party.', 'Não estás numa party.'),
(NOW(), 'chat.error_no_alliance', 'You are not in an alliance.', 'Non sei in un''alleanza.', 'Não estás numa aliança.'),
(NOW(), 'chat.error_no_global_pass', 'You need a global chat pass.', 'Serve un pass per la chat globale.', 'Precisas de um passe para o chat global.'),
(NOW(), 'chat.error_invalid_sender', 'Could not send message.', 'Impossibile inviare il messaggio.', 'Não foi possível enviar mensagem.'),
(NOW(), 'chat.error_server_error', 'Chat is temporarily unavailable. Try again later.', 'Chat temporaneamente non disponibile. Riprova più tardi.', 'Chat temporariamente indisponível. Tenta mais tarde.'),
(NOW(), 'chat.error_generic', 'Could not send message.', 'Impossibile inviare il messaggio.', 'Não foi possível enviar mensagem.');






-- UI strings (safe to re-run only if tags do not exist yet)
INSERT INTO playanimaster_db.language_texts (dt_c, tag, text, text_it, text_pt) VALUES
(NOW(), 'trade.request_tooltip', 'Trade', 'Scambia', 'Trocar'),
(NOW(), 'trade.request_sent', 'Trade request sent.', 'Richiesta di scambio inviata.', 'Pedido de troca enviado.'),
(NOW(), 'trade.request_failed', 'Could not send trade request.', 'Impossibile inviare la richiesta di scambio.', 'Não foi possível enviar pedido de troca.'),
(NOW(), 'trade.incoming_title', '{name} wants to trade', '{name} vuole scambiare', '{name} quer trocar'),
(NOW(), 'trade.accept', 'Accept', 'Accetta', 'Aceitar'),
(NOW(), 'trade.decline', 'Decline', 'Rifiuta', 'Recusar'),
(NOW(), 'trade.expired', 'Trade request expired.', 'Richiesta di scambio scaduta.', 'Pedido de troca expirado.'),
(NOW(), 'trade.waiting', 'Waiting for {name}…', 'In attesa di {name}…', 'À espera de {name}…'),
(NOW(), 'trade.title', 'Trade with {name}', 'Scambio con {name}', 'Troca com {name}'),
(NOW(), 'trade.your_offer', 'Your offer', 'La tua offerta', 'A tua oferta'),
(NOW(), 'trade.their_offer', '{name}''s offer', 'Offerta di {name}', 'Oferta de {name}'),
(NOW(), 'trade.gold_label', 'Gold', 'Oro', 'Ouro'),
(NOW(), 'trade.gold_balance', 'You have: {gold}', 'Hai: {gold}', 'Tens: {gold}'),
(NOW(), 'trade.add_item', 'Add item', 'Aggiungi oggetto', 'Adicionar item'),
(NOW(), 'trade.remove_item', 'Remove', 'Rimuovi', 'Remover'),
(NOW(), 'trade.no_items', 'No items offered', 'Nessun oggetto', 'Sem itens'),
(NOW(), 'trade.confirm', 'Confirm trade', 'Conferma scambio', 'Confirmar troca'),
(NOW(), 'trade.confirmed', 'Confirmed — waiting for partner', 'Confermato — in attesa del partner', 'Confirmado — à espera do parceiro'),
(NOW(), 'trade.partner_confirmed', 'Partner confirmed', 'Partner ha confermato', 'Parceiro confirmou'),
(NOW(), 'trade.cancel', 'Cancel trade', 'Annulla scambio', 'Cancelar troca'),
(NOW(), 'trade.completed', 'Trade completed!', 'Scambio completato!', 'Troca concluída!'),
(NOW(), 'trade.cancelled', 'Trade cancelled.', 'Scambio annullato.', 'Troca cancelada.'),
(NOW(), 'trade.error_not_tradable', 'That item cannot be traded.', 'Quell''oggetto non è scambiabile.', 'Esse item não pode ser trocado.'),
(NOW(), 'trade.error_not_enough_gold', 'Not enough gold.', 'Oro insufficiente.', 'Ouro insuficiente.'),
(NOW(), 'trade.error_not_enough_items', 'Not enough items.', 'Oggetti insufficienti.', 'Itens insuficientes.'),
(NOW(), 'trade.error_busy', 'You or the other player are already trading.', 'Tu o l''altro giocatore state già scambiando.', 'Tu ou o outro jogador já estão a trocar.'),
(NOW(), 'trade.error_offline', 'Player is not available.', 'Giocatore non disponibile.', 'Jogador indisponível.'),
(NOW(), 'trade.error_generic', 'Trade failed.', 'Scambio fallito.', 'Troca falhou.'),
(NOW(), 'trade.pick_item_title', 'Add to trade', 'Aggiungi allo scambio', 'Adicionar à troca'),
(NOW(), 'trade.pick_item_empty', 'No tradable items in your bag.', 'Nessun oggetto scambiabile nella borsa.', 'Sem itens trocáveis na mochila.'),
(NOW(), 'trade.pick_quantity_label', 'Quantity', 'Quantità', 'Quantidade'),
(NOW(), 'trade.pick_quantity_hint', 'Available: {max}', 'Disponibili: {max}', 'Disponíveis: {max}');





INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('motherfucker', 'motherforker', 'en', 10),
('motherfucking', 'motherforking', 'en', 10),
('bullshit', 'bullshirt', 'en', 10),
('asshole', 'casserole', 'en', 10),
('dickhead', 'duckhead', 'en', 10),
('douchebag', 'dishwasher', 'en', 10),
('jackass', 'jackfruit', 'en', 10),
('smartass', 'smartgrass', 'en', 10),
('dumbass', 'dumbgrass', 'en', 10),
('badass', 'badgrass', 'en', 10),
('fatass', 'fatgrass', 'en', 10),
('kiss my ass', 'kiss my bass', 'en', 20),
('piece of shit', 'piece of shirt', 'en', 20),
('son of a bitch', 'son of a bench', 'en', 20),
('fucking', 'forking', 'en', 30),
('fucker', 'forker', 'en', 30),
('fucked', 'forked', 'en', 30),
('fucks', 'forks', 'en', 30),
('fuck', 'fork', 'en', 30),
('shitty', 'shirty', 'en', 30),
('shitting', 'shirting', 'en', 30),
('shit', 'shirt', 'en', 30),
('bitchy', 'benchy', 'en', 30),
('bitches', 'benches', 'en', 30),
('bitch', 'bench', 'en', 30),
('bastard', 'mustard', 'en', 30),
('whore', 'wire', 'en', 30),
('slut', 'slot', 'en', 30),
('slutty', 'slotty', 'en', 30),
('cunt', 'cant', 'en', 30),
('pussy', 'puppy', 'en', 30),
('dick', 'duck', 'en', 30),
('cock', 'clock', 'en', 30),
('cocks', 'clocks', 'en', 30),
('piss', 'kiss', 'en', 30),
('pissed', 'kissed', 'en', 30),
('pissing', 'kissing', 'en', 30),
('crap', 'crop', 'en', 30),
('crappy', 'croppy', 'en', 30),
('damn', 'darn', 'en', 30),
('damned', 'darned', 'en', 30),
('hell', 'shell', 'en', 30),
('douche', 'dune', 'en', 30),
('retard', 'regard', 'en', 30),
('retarded', 'regarded', 'en', 30),
('idiot', 'idjit', 'en', 30),
('moron', 'melon', 'en', 30),
('stupid', 'stupendous', 'en', 30),
('wtf', 'waffle', 'en', 30),
('stfu', 'stuff', 'en', 30),
('lmfao', 'lolipop', 'en', 30),
('suck', 'sock', 'en', 30),
('sucks', 'socks', 'en', 30),
('sucker', 'soccer', 'en', 30),
('suckers', 'soccers', 'en', 30),
('boob', 'blob', 'en', 30),
('boobs', 'blobs', 'en', 30),
('tits', 'tots', 'en', 30),
('tit', 'tot', 'en', 30),
('twat', 'twit', 'en', 30),
('wanker', 'walker', 'en', 30),
('wank', 'walk', 'en', 30),
('bollocks', 'balloons', 'en', 30),
('bugger', 'burger', 'en', 30),
('arse', 'parse', 'en', 30),
('arsehole', 'parsenole', 'en', 30),
('ass', 'bass', 'en', 40)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- Italian
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('vaffanculo', 'vaffanbullo', 'it', 10),
('vaffancul', 'vaffanbull', 'it', 10),
('affanculo', 'affanbullo', 'it', 10),
('figlio di puttana', 'figlio di pottana', 'it', 20),
('porco dio', 'porco dino', 'it', 20),
('porcodio', 'porkodino', 'it', 20),
('dio cane', 'dino cane', 'it', 20),
('diocane', 'dinocane', 'it', 20),
('dio porco', 'dino porco', 'it', 20),
('dioporco', 'dinoporco', 'it', 20),
('testa di cazzo', 'testa di casco', 'it', 20),
('pezzo di merda', 'pezzo di marmellata', 'it', 20),
('fanculo', 'fanbullo', 'it', 30),
('coglione', 'coglionzone', 'it', 30),
('coglioni', 'coglionzoni', 'it', 30),
('stronzo', 'stronzolo', 'it', 30),
('stronza', 'stronzola', 'it', 30),
('stronzi', 'stronzoli', 'it', 30),
('bastardo', 'pastardo', 'it', 30),
('bastarda', 'pastarda', 'it', 30),
('puttana', 'pottana', 'it', 30),
('puttanata', 'portanata', 'it', 30),
('troia', 'trolley', 'it', 30),
('troie', 'trolleys', 'it', 30),
('merda', 'marmellata', 'it', 30),
('merdoso', 'marmelloso', 'it', 30),
('merdosa', 'marmellosa', 'it', 30),
('cazzo', 'casco', 'it', 30),
('cazzi', 'caschi', 'it', 30),
('minchia', 'panino', 'it', 30),
('minchiata', 'paninata', 'it', 30),
('figa', 'fiesta', 'it', 30),
('fighe', 'fiestas', 'it', 30),
('fica', 'fiesta', 'it', 30),
('fregna', 'fresca', 'it', 30),
('frocio', 'fioraio', 'it', 30),
('froci', 'fiorai', 'it', 30),
('ricchione', 'ricchionzone', 'it', 30),
('cretino', 'cretinetto', 'it', 30),
('cretina', 'cretinetta', 'it', 30),
('idiota', 'idrata', 'it', 30),
('idiote', 'idrate', 'it', 30),
('scemo', 'scemotto', 'it', 30),
('scema', 'scemotta', 'it', 30),
('deficiente', 'deficientino', 'it', 30),
('imbecille', 'imbecillino', 'it', 30),
('stupido', 'stupendino', 'it', 30),
('stupida', 'stupendina', 'it', 30),
('cagare', 'cioccolare', 'it', 30),
('cagata', 'cioccolata', 'it', 30),
('scopare', 'scappare', 'it', 30),
('scopata', 'scappata', 'it', 30),
('succhiare', 'biscottare', 'it', 30),
('succhia', 'biscotta', 'it', 30),
('porco', 'porcellino', 'it', 40),
('dio', 'dino', 'it', 50)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- Spanish
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('hijo de puta', 'hijo de puerta', 'es', 10),
('hija de puta', 'hija de puerta', 'es', 10),
('la puta madre', 'la puerta madre', 'es', 10),
('puta madre', 'puerta madre', 'es', 10),
('gilipollas', 'gilipuercas', 'es', 10),
('maricon', 'mariposa', 'es', 10),
('maricón', 'mariposa', 'es', 10),
('pendejo', 'pendejito', 'es', 30),
('pendeja', 'pendejita', 'es', 30),
('cabron', 'cebolla', 'es', 30),
('cabrón', 'cebolla', 'es', 30),
('cabrona', 'cebollona', 'es', 30),
('joder', 'yogur', 'es', 30),
('jodido', 'yogurito', 'es', 30),
('jodida', 'yogurita', 'es', 30),
('jodete', 'yogurte', 'es', 30),
('mierda', 'mermelada', 'es', 30),
('mierdoso', 'mermeladoso', 'es', 30),
('puta', 'puerta', 'es', 30),
('puto', 'puerto', 'es', 30),
('putas', 'puertas', 'es', 30),
('coño', 'conejo', 'es', 30),
('cojones', 'cajones', 'es', 30),
('cojon', 'cajon', 'es', 30),
('verga', 'berga', 'es', 30),
('chingar', 'chillar', 'es', 30),
('chingada', 'chillada', 'es', 30),
('chingado', 'chillado', 'es', 30),
('culero', 'culerito', 'es', 30),
('culera', 'culerita', 'es', 30),
('imbecil', 'imbecilino', 'es', 30),
('imbécil', 'imbecilino', 'es', 30),
('idiota', 'idratita', 'es', 30),
('estupido', 'estupendito', 'es', 30),
('estúpido', 'estupendito', 'es', 30),
('tonto', 'tontuelo', 'es', 30),
('tonta', 'tontuela', 'es', 30)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- French
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('fils de pute', 'fils de purée', 'fr', 10),
('fille de pute', 'fille de purée', 'fr', 10),
('ta gueule', 'ta guimauve', 'fr', 10),
('ferme ta gueule', 'ferme ta guimauve', 'fr', 10),
('enculé', 'encellé', 'fr', 30),
('encule', 'encelle', 'fr', 30),
('enculer', 'enceller', 'fr', 30),
('connard', 'fromage', 'fr', 30),
('connarde', 'fromagère', 'fr', 30),
('salope', 'salopette', 'fr', 30),
('salaud', 'saucisson', 'fr', 30),
('pute', 'purée', 'fr', 30),
('putain', 'purée', 'fr', 30),
('merde', 'merveille', 'fr', 30),
('merdique', 'merveilleux', 'fr', 30),
('nique', 'nitrique', 'fr', 30),
('niquer', 'nitriquer', 'fr', 30),
('bite', 'biscuit', 'fr', 30),
('couille', 'coussin', 'fr', 30),
('couilles', 'coussins', 'fr', 30),
('chatte', 'chouquette', 'fr', 30),
('connerie', 'cornetrie', 'fr', 30),
('idiot', 'idjit', 'fr', 30),
('idiote', 'idjite', 'fr', 30),
('debile', 'débile', 'fr', 30),
('débile', 'débilito', 'fr', 30),
('crétin', 'crétinet', 'fr', 30),
('cretin', 'cretinet', 'fr', 30)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- German
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('hurensohn', 'hundesohn', 'de', 10),
('hure', 'haube', 'de', 30),
('scheisse', 'struempfe', 'de', 30),
('scheiße', 'strümpfe', 'de', 30),
('scheiss', 'struempf', 'de', 30),
('scheiß', 'strümpf', 'de', 30),
('scheisser', 'struempfer', 'de', 30),
('scheißer', 'strümpfer', 'de', 30),
('fick', 'flick', 'de', 30),
('ficken', 'flicken', 'de', 30),
('gefickt', 'geflickt', 'de', 30),
('ficker', 'flicker', 'de', 30),
('arsch', 'marsch', 'de', 30),
('arschloch', 'marschloch', 'de', 30),
('mist', 'wist', 'de', 30),
('mistkerl', 'wistkerl', 'de', 30),
('fotze', 'fritze', 'de', 30),
('schlampe', 'schlampf', 'de', 30),
('wichser', 'wichsler', 'de', 30),
('wichsen', 'wischen', 'de', 30),
('dummkopf', 'marmeladenkopf', 'de', 30),
('idiot', 'idjit', 'de', 30),
('depp', 'depperino', 'de', 30),
('spasti', 'spastino', 'de', 30),
('spast', 'spastino', 'de', 30)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- Portuguese
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('filho da puta', 'filho da porta', 'pt', 10),
('filha da puta', 'filha da porta', 'pt', 10),
('vai tomar no cu', 'vai tomar no cuoio', 'pt', 10),
('caralho', 'caramelho', 'pt', 30),
('caralhos', 'caramelhos', 'pt', 30),
('merda', 'marmelada', 'pt', 30),
('merdoso', 'marmeloso', 'pt', 30),
('puta', 'porta', 'pt', 30),
('puto', 'porto', 'pt', 30),
('foder', 'folder', 'pt', 30),
('fodido', 'foldido', 'pt', 30),
('fodida', 'foldida', 'pt', 30),
('foda', 'folda', 'pt', 30),
('buceta', 'bolacha', 'pt', 30),
('cacete', 'cachete', 'pt', 30),
('idiota', 'idratita', 'pt', 30),
('imbecil', 'imbecilino', 'pt', 30),
('estupido', 'estupendito', 'pt', 30),
('estúpido', 'estupendito', 'pt', 30),
('otario', 'otarino', 'pt', 30),
('otário', 'otarino', 'pt', 30)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';


-- ---------------------------------------------------------------------------
-- Polish
-- ---------------------------------------------------------------------------

INSERT INTO playanimaster_db.chat_word_replacements (bad_word, replacement, lang_code, sort_order) VALUES
('kurwa', 'kurczak', 'pl', 30),
('kurwy', 'kurczaki', 'pl', 30),
('kurwo', 'kurczako', 'pl', 30),
('chuj', 'chleb', 'pl', 30),
('chuja', 'chleba', 'pl', 30),
('chujowy', 'chlebowy', 'pl', 30),
('pierdolec', 'pieróg', 'pl', 30),
('pierdol', 'pieróg', 'pl', 30),
('pierdolic', 'pierogować', 'pl', 30),
('pierdolony', 'pierogony', 'pl', 30),
('pizda', 'pizza', 'pl', 30),
('dupa', 'tulipano', 'pl', 30),
('debil', 'debilino', 'pl', 30),
('debilu', 'debilino', 'pl', 30),
('idiota', 'idratita', 'pl', 30),
('skurwysyn', 'skurwyczyn', 'pl', 30)
ON DUPLICATE KEY UPDATE
    replacement = VALUES(replacement),
    lang_code = VALUES(lang_code),
    sort_order = VALUES(sort_order),
    flg_active = 'S';




INSERT INTO playanimaster_db.classes (id_class, class) VALUES(1, 'Mammals');
INSERT INTO playanimaster_db.classes (id_class, class) VALUES(2, 'Reptiles');
INSERT INTO playanimaster_db.classes (id_class, class) VALUES(3, 'Birds');
INSERT INTO playanimaster_db.classes (id_class, class) VALUES(4, 'Fish');
INSERT INTO playanimaster_db.classes (id_class, class) VALUES(5, 'Insects');
INSERT INTO playanimaster_db.classes (id_class, class) VALUES(6, 'Invertebrates');


INSERT INTO playanimaster_db.consequences (id_consequence, consequence_type, id_ref, `ref`, num) VALUES(1, '[obtain item]', 1, 'POTION', 1);

INSERT INTO playanimaster_db.conversation_consequences (id_conversation_consequence, id_conversation, id_option, id_consequence) VALUES(1, 5, 1, 1);
INSERT INTO playanimaster_db.conversation_consequences (id_conversation_consequence, id_conversation, id_option, id_consequence) VALUES(2, 6, 5, 1);

INSERT INTO playanimaster_db.conversation_requirements (id_conversation_requirement, id_conversation, id_requirement) VALUES(4, 4, 3);
INSERT INTO playanimaster_db.conversation_requirements (id_conversation_requirement, id_conversation, id_requirement) VALUES(3, 3, 4);
INSERT INTO playanimaster_db.conversation_requirements (id_conversation_requirement, id_conversation, id_requirement) VALUES(5, 6, 7);

INSERT INTO playanimaster_db.conversations (id_conversation, id_npc, visible, title, title_it, title_pt, flg_register) VALUES(4, 1, 'S', 'First Companion', 'Primo compagno', 'Primeiro companheiro', 'S');
INSERT INTO playanimaster_db.conversations (id_conversation, id_npc, visible, title, title_it, title_pt, flg_register) VALUES(3, 1, 'S', 'Greeting', 'Saluto', 'Saudacao', 'N');
INSERT INTO playanimaster_db.conversations (id_conversation, id_npc, visible, title, title_it, title_pt, flg_register) VALUES(7, 2, 'S', 'Greeting', 'Saluto', 'Saudacao', 'N');
INSERT INTO playanimaster_db.conversations (id_conversation, id_npc, visible, title, title_it, title_pt, flg_register) VALUES(6, 1, 'S', 'Wild animals', 'Animali selvaggi', 'Animais selvagens', 'S');

INSERT INTO playanimaster_db.costanti (id_costante, costante, valore) VALUES(4, 'lvl_up_constant_animal', 40);
INSERT INTO playanimaster_db.costanti (id_costante, costante, valore) VALUES(5, 'lvl_up_constant_player', 80);
INSERT INTO playanimaster_db.costanti (id_costante, costante, valore) VALUES(6, 'exp_loss_percent_on_death', 5);


INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(6, 4, 2, 'S', 'S', 'You do not have any animals yet. Would you like to receive your first companion?', 'Non hai ancora nessun animale. Vuoi ricevere il tuo primo compagno?', 'Ainda nao tens nenhum animal. Queres receber o teu primeiro companheiro?');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(4, 3, 1, 'S', 'N', 'Hello! Good to see you again.', 'Ciao! Che piacere rivederti.', 'Ola! Bom te ver outra vez.');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(5, 4, 1, 'N', 'N', 'Hello! Welcome to Animaster.', 'Ciao! Benvenuto in Animaster.', 'Ola! Bem-vindo ao Animaster.');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(14, 7, 1, 'S', 'N', 'Hello! Good to see you again.', 'Ciao! Che piacere rivederti.', 'Ola! Bom te ver outra vez.');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(13, 6, 3, 'S', 'S', 'Here, take this Potion. Stay safe!', 'Ecco, prendi questa Pozione. Stai attento!', 'Toma, fica com esta Pocao. Cuida-te!');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(12, 6, 2, 'N', 'N', 'If your team gets hurt, use Potions outside battle. Always keep a few with you before exploring.', 'Se la tua squadra si ferisce, usa le Pozioni fuori dalla lotta. Tienine sempre qualcuna prima di esplorare.', 'Se a tua equipa se magoar, usa Pocoes fora de combate. Mantem sempre algumas contigo antes de explorar.');
INSERT INTO playanimaster_db.dialogues (id_dialog, id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt) VALUES(11, 6, 1, 'N', 'N', 'Traveler, be careful out there. The wild animals in this zone have become aggressive and unpredictable.', 'Viaggiatore, fai attenzione la fuori. Gli animali selvaggi in questa zona sono diventati aggressivi e imprevedibili.', 'Viajante, tem cuidado la fora. Os animais selvagens nesta zona tornaram-se agressivos e imprevisiveis.');

INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(1, 6, 1, NULL, NULL, NULL, 'green', 'Yes, give me my first animal!', 'Si, voglio il mio primo animale!', 'Sim, quero o meu primeiro animal!');
INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(2, 6, 2, NULL, NULL, NULL, 'red', 'Not now', 'Non adesso', 'Agora nao');
INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(5, 13, 1, NULL, NULL, NULL, 'green', 'Thank you!', 'Grazie!', 'Obrigado!');


INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(1, 'Water', 'Acqua', 'Agua', '#058ef0');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(2, 'Fire', 'Fuoco', 'Fogo', '#f02c05');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(3, 'Air', 'Aria', 'Ar', '#a0ebf2');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(4, 'Earth', 'Terra', 'Terra', '#9c5313');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(5, 'Electricity', 'Elettricita', 'Electricidade', '#fce112');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(6, 'Light', 'Luce', 'Luz', '#fffded');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(7, 'Dark', 'Buio', 'Trevas', '#35003d');
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES(8, 'none', 'nessuno', 'nenhum', '#7a797a');


INSERT INTO playanimaster_db.item_types (id_item_type, dt_creazione, dt_modifica, item_type, nome, descrizione, price, sell_price, use_effect, flg_holdable, flg_tradable, flg_sellable, flg_usable, usable_on, flg_stackable, stack_limit, flg_usable_in_battle, flg_usable_outside_battle, flg_usable_on_alive, flg_usable_on_fainted, nome_it, nome_pt, descrizione_it, descrizione_pt) VALUES(1, '2022-01-03 12:44:36.000', NULL, 'potions', 'Potion', 'A device that can be used on an animal to heal 20 health points', 100, 50, 20, 'S', 'S', 'S', 'S', 'animals', 'S', 9999, 'S', 'S', 'S', 'N', 'Pozione', 'Bateria', 'Un oggetto che puo essere usato su una creatura per recuperare 20 punti vita', 'Um objecto que pode ser usado numa creatura para recuperar 20 pontos de vida');
INSERT INTO playanimaster_db.item_types (id_item_type, dt_creazione, dt_modifica, item_type, nome, descrizione, price, sell_price, use_effect, flg_holdable, flg_tradable, flg_sellable, flg_usable, usable_on, flg_stackable, stack_limit, flg_usable_in_battle, flg_usable_outside_battle, flg_usable_on_alive, flg_usable_on_fainted, nome_it, nome_pt, descrizione_it, descrizione_pt) VALUES(2, '2022-01-03 12:45:18.000', NULL, 'potions', 'Super Potion', 'A device that can be used on an animal to heal 35 health points', 200, 100, 35, 'S', 'S', 'S', 'S', 'animals', 'S', 9999, 'S', 'S', 'S', 'N', 'Super Pozione', 'Super Bateria', 'Un oggetto che puo essere usato su una creatura per recuperare 35 punti vita', 'Um objecto que pode ser usado numa creatura para recuperar 35 pontos de vida');
INSERT INTO playanimaster_db.item_types (id_item_type, dt_creazione, dt_modifica, item_type, nome, descrizione, price, sell_price, use_effect, flg_holdable, flg_tradable, flg_sellable, flg_usable, usable_on, flg_stackable, stack_limit, flg_usable_in_battle, flg_usable_outside_battle, flg_usable_on_alive, flg_usable_on_fainted, nome_it, nome_pt, descrizione_it, descrizione_pt) VALUES(3, '2022-01-03 12:45:39.000', NULL, 'potions', 'Hyper Potion', 'A device that can be used on an animal to heal 75 health points', 500, 250, 75, 'S', 'S', 'S', 'S', 'animals', 'S', 9999, 'S', 'S', 'S', 'N', 'Hyper Pozione', 'Hyper Bateria', 'Un oggetto che puo essere usato su una creatura per recuperare 75 punti vita', 'Um objecto que pode ser usado numa creatura para recuperar 75 pontos de vida');
INSERT INTO playanimaster_db.item_types (id_item_type, dt_creazione, dt_modifica, item_type, nome, descrizione, price, sell_price, use_effect, flg_holdable, flg_tradable, flg_sellable, flg_usable, usable_on, flg_stackable, stack_limit, flg_usable_in_battle, flg_usable_outside_battle, flg_usable_on_alive, flg_usable_on_fainted, nome_it, nome_pt, descrizione_it, descrizione_pt) VALUES(4, '2022-01-03 12:46:02.000', NULL, 'potions', 'Ultra Potion', 'A device that can be used on an animal to heal 125 health points', 1000, 500, 125, 'S', 'S', 'S', 'S', 'animals', 'S', 9999, 'S', 'S', 'S', 'N', 'Ultra Pozione', 'Ultra Bateria', 'Un oggetto che puo essere usato su una creatura per recuperare 125 punti vita', 'Um objecto que pode ser usado numa creatura para recuperar 125 pontos de vida');
INSERT INTO playanimaster_db.item_types (id_item_type, dt_creazione, dt_modifica, item_type, nome, descrizione, price, sell_price, use_effect, flg_holdable, flg_tradable, flg_sellable, flg_usable, usable_on, flg_stackable, stack_limit, flg_usable_in_battle, flg_usable_outside_battle, flg_usable_on_alive, flg_usable_on_fainted, nome_it, nome_pt, descrizione_it, descrizione_pt) VALUES(5, '2022-01-03 12:55:45.000', NULL, 'potions', 'Giga Potion', 'A device that can be used on an animal to heal 200 health points', 2000, 1000, 200, 'S', 'S', 'S', 'S', 'animals', 'S', 9999, 'S', 'S', 'S', 'N', 'Giga Pozione', 'Giga Bateria', 'Un oggetto che puo essere usato su una creatura per recuperare 200 punti vita', 'Um objecto que pode ser usado numa creatura para recuperar 200 pontos de vida');



INSERT INTO playanimaster_db.npc_requirements (id_npc_requirement, id_npc, id_requirement) VALUES(1, 2, 2);

INSERT INTO playanimaster_db.npcs (id_npc, npc, `type`, id_zone, posx, posy, rangex, rangey, direction, sight_distance, npc_type_prefab, posz, wander_range, euler_x, euler_y, euler_z, gender) VALUES(1, 'Prof', 'story', 1000, 0.0, 0.0, 0, 0, 'D', 0, 'trader', 0.0, 0, 0.0, 0.0, 0.0, NULL);
INSERT INTO playanimaster_db.npcs (id_npc, npc, `type`, id_zone, posx, posy, rangex, rangey, direction, sight_distance, npc_type_prefab, posz, wander_range, euler_x, euler_y, euler_z, gender) VALUES(2, 'Assistant', 'story adjacent?', 1000, 50.0, 0.0, 0, 0, 'D', 0, 'trader', 0.0, 0, 0.0, 0.0, 0.0, NULL);


INSERT INTO playanimaster_db.requirements (id_requirement, requirement_type, id_ref, `ref`, min, max, descrizione) VALUES(2, 'item', 1, 'POTION', 5, 100, 'at least 5 potions');
INSERT INTO playanimaster_db.requirements (id_requirement, requirement_type, id_ref, `ref`, min, max, descrizione) VALUES(3, 'number of animals', 0, 'ZERO', 0, 0, 'Account has no animals');
INSERT INTO playanimaster_db.requirements (id_requirement, requirement_type, id_ref, `ref`, min, max, descrizione) VALUES(4, 'number of animals', 0, 'HAS_ANIMALS', 1, 999, 'Account has at least one animal');
INSERT INTO playanimaster_db.requirements (id_requirement, requirement_type, id_ref, `ref`, min, max, descrizione) VALUES(7, 'item', 1, 'POTION', 0, 4, 'Fewer than 5 potions in inventory');


INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(1, 1000, 100.0, 100.0, 100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(2, 1000, -100.0, 100.0, 100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(3, 1000, 100.0, 100.0, -100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(4, 1000, -100.0, 100.0, -100.0, 50, 5);


INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(1, '2022-01-06 23:03:02.000', NULL, 'Dog', 1, 1, 1, 1.0, 60, 70, 45, 60, 50, 60, 100, 5, 10, 0, 1, 0, 0, 0, 0, 0, 0, 0, 46, NULL, 'S', 'Cane', 'Cao');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(2, '2022-01-06 23:03:53.000', NULL, 'Cat', 1, 2, 2, 1.0, 45, 60, 40, 60, 45, 80, 100, 15, 15, 0, 0, 0, 0, 0, 0, 0, 0, 1, 46, NULL, 'S', 'Gatto', 'Gato');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(3, '2022-01-06 23:05:57.000', NULL, 'Snake', 2, NULL, 3, 1.0, 50, 60, 60, 50, 60, 50, 100, 10, 20, 0, 0, 0, 0, 0, 0, 0, 1, 0, 46, NULL, 'S', 'Serpente', 'Serpente');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(4, '2022-01-06 23:07:20.000', NULL, 'Bat', 1, NULL, NULL, NULL, 18, 10, 15, 25, 25, 5, 100, 10, 10, 0, 0, 0, 0, 0, 1, 0, 0, 0, 46, NULL, 'N', 'Pipistrello', 'Morcego');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(5, '2022-01-08 23:22:32.000', NULL, 'Aardvark', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Aardvark', 'Aardvark');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(6, '2022-01-08 23:22:32.000', NULL, 'Alligator', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Alligator', 'Alligator');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(7, '2022-01-08 23:22:32.000', NULL, 'Alpaca', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Alpaca', 'Alpaca');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(8, '2022-01-08 23:22:32.000', NULL, 'Anaconda', 2, NULL, 3, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Anaconda', 'Anaconda');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(9, '2022-01-08 23:22:32.000', NULL, 'Ant', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ant', 'Ant');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(10, '2022-01-08 23:22:32.000', NULL, 'Antelope', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Antelope', 'Antelope');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(11, '2022-01-08 23:22:32.000', NULL, 'Ape', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ape', 'Ape');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(12, '2022-01-08 23:22:32.000', NULL, 'Aphid', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Aphid', 'Aphid');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(13, '2022-01-08 23:22:32.000', NULL, 'Armadillo', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Armadillo', 'Armadillo');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(14, '2022-01-08 23:22:32.000', NULL, 'Pithon', 2, NULL, 3, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Pithon', 'Pithon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(15, '2022-01-08 23:22:32.000', NULL, 'Ass', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ass', 'Ass');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(16, '2022-01-08 23:22:32.000', NULL, 'Baboon', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Baboon', 'Baboon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(17, '2022-01-08 23:22:32.000', NULL, 'Badger', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Badger', 'Badger');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(18, '2022-01-08 23:22:32.000', NULL, 'Barracuda', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Barracuda', 'Barracuda');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(19, '2022-01-08 23:22:32.000', NULL, 'Bass', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bass', 'Bass');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(20, '2022-01-08 23:22:32.000', NULL, 'Basset Hound', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Basset Hound', 'Basset Hound');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(21, '2022-01-08 23:22:32.000', NULL, 'Bear', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bear', 'Bear');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(22, '2022-01-08 23:22:32.000', NULL, 'Beaver', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Beaver', 'Beaver');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(23, '2022-01-08 23:22:32.000', NULL, 'Bedbug', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bedbug', 'Bedbug');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(24, '2022-01-08 23:22:32.000', NULL, 'Bee', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bee', 'Bee');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(25, '2022-01-08 23:22:32.000', NULL, 'Beetle', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Beetle', 'Beetle');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(26, '2022-01-08 23:22:32.000', NULL, 'Woodpecker', 3, NULL, 91, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Woodpecker', 'Woodpecker');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(27, '2022-01-08 23:22:32.000', NULL, 'Bison', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bison', 'Bison');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(28, '2022-01-08 23:22:32.000', NULL, 'Black Panther', 1, 2, 2, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Black Panther', 'Black Panther');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(29, '2022-01-08 23:22:32.000', NULL, 'Black Widow Spider', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Black Widow Spider', 'Black Widow Spider');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(30, '2022-01-08 23:22:32.000', NULL, 'Seagull', 3, NULL, 32, 1.0, 50, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Seagull', 'Seagull');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(31, '2022-01-08 23:22:32.000', NULL, 'Blue Whale', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Blue Whale', 'Blue Whale');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(32, '2022-01-08 23:22:32.000', NULL, 'Bobcat', 1, 2, 2, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Bobcat', 'Bobcat');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(33, '2022-01-08 23:22:32.000', NULL, 'Buffalo', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Buffalo', 'Buffalo');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(34, '2022-01-08 23:22:32.000', NULL, 'Butterfly', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Butterfly', 'Butterfly');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(35, '2022-01-09 14:52:53.000', NULL, 'King Penguin', 3, NULL, 123, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'King Penguin', 'King Penguin');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(36, '2022-01-08 23:22:32.000', NULL, 'Camel', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Camel', 'Camel');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(37, '2022-01-08 23:22:32.000', NULL, 'Caribou', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Caribou', 'Caribou');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(38, '2022-01-08 23:22:32.000', NULL, 'Carp', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Carp', 'Carp');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(39, '2022-01-08 23:22:32.000', NULL, 'Caterpillar', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Caterpillar', 'Caterpillar');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(40, '2022-01-08 23:22:32.000', NULL, 'Catfish', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Catfish', 'Catfish');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(41, '2022-01-08 23:22:32.000', NULL, 'Cheetah', 1, 2, 2, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cheetah', 'Cheetah');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(42, '2022-01-08 23:22:32.000', NULL, 'Chicken', 3, NULL, 45, 1.0, 40, 60, 50, 40, 60, 50, 100, 15, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Chicken', 'Chicken');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(43, '2022-01-08 23:22:32.000', NULL, 'Chimpanzee', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Chimpanzee', 'Chimpanzee');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(44, '2022-01-08 23:22:32.000', NULL, 'Chipmunk', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Chipmunk', 'Chipmunk');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(45, '2022-01-08 23:22:32.000', NULL, 'Cobra', 2, NULL, 134, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cobra', 'Cobra');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(46, '2022-01-08 23:22:32.000', NULL, 'Cod', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cod', 'Cod');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(47, '2022-01-08 23:22:32.000', NULL, 'Condor', 3, NULL, 58, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Condor', 'Condor');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(48, '2022-01-08 23:22:32.000', NULL, 'Cougar', 1, 2, 2, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cougar', 'Cougar');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(49, '2022-01-08 23:22:32.000', NULL, 'Cow', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cow', 'Cow');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(50, '2022-01-08 23:22:32.000', NULL, 'Coyote', 1, 1, 79, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Coyote', 'Coyote');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(51, '2022-01-08 23:22:32.000', NULL, 'Crab', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Crab', 'Crab');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(52, '2022-01-08 23:22:32.000', NULL, 'Stork', 3, NULL, 91, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Stork', 'Stork');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(53, '2022-01-08 23:22:32.000', NULL, 'Cricket', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Cricket', 'Cricket');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(54, '2022-01-08 23:22:32.000', NULL, 'Crocodile', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Crocodile', 'Crocodile');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(55, '2022-01-08 23:22:32.000', NULL, 'Crow', 3, NULL, 58, 1.0, 40, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Crow', 'Crow');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(56, '2022-01-08 23:22:32.000', NULL, 'Deer', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Deer', 'Deer');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(57, '2022-01-08 23:22:32.000', NULL, 'Komodo Dragon', 2, NULL, 100, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Komodo Dragon', 'Komodo Dragon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(58, '2022-01-08 23:22:32.000', NULL, 'Dolphin', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Dolphin', 'Dolphin');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(59, '2022-01-08 23:22:32.000', NULL, 'Donkey', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Donkey', 'Donkey');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(60, '2022-01-08 23:22:32.000', NULL, 'Dragonfly', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Dragonfly', 'Dragonfly');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(61, '2022-01-08 23:22:32.000', NULL, 'Duck', 3, NULL, 61, 1.0, 50, 60, 40, 60, 50, 68, 100, 20, 12, 0, 0, 0, 0, 0, 0, 0, 0, 0, 46, NULL, 'S', 'Anatra', 'Pato');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(62, '2022-01-08 23:22:32.000', NULL, 'Eagle', 3, NULL, 88, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Eagle', 'Eagle');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(63, '2022-01-08 23:22:32.000', NULL, 'Eel', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Eel', 'Eel');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(64, '2022-01-08 23:22:32.000', NULL, 'Elephant', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Elephant', 'Elephant');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(65, '2022-01-08 23:22:32.000', NULL, 'Emu', 3, NULL, 130, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Emu', 'Emu');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(66, '2022-01-08 23:22:32.000', NULL, 'Falcon', 3, NULL, 88, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Falcon', 'Falcon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(67, '2022-01-08 23:22:32.000', NULL, 'Ferret', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ferret', 'Ferret');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(68, '2022-01-08 23:22:32.000', NULL, 'Snow Owl', 3, NULL, 116, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Snow Owl', 'Snow Owl');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(69, '2022-01-08 23:22:32.000', NULL, 'Fish', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Fish', 'Fish');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(70, '2022-01-08 23:22:32.000', NULL, 'Flamingo', 3, NULL, 32, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Flamingo', 'Flamingo');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(71, '2022-01-08 23:22:32.000', NULL, 'Flea', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Flea', 'Flea');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(72, '2022-01-08 23:22:32.000', NULL, 'Fly', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Fly', 'Fly');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(73, '2022-01-08 23:22:32.000', NULL, 'Fox', 1, 1, 79, 1.0, 45, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Fox', 'Fox');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(74, '2022-01-08 23:22:32.000', NULL, 'Frog', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Frog', 'Frog');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(75, '2022-01-08 23:22:32.000', NULL, 'Goat', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Goat', 'Goat');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(76, '2022-01-08 23:22:32.000', NULL, 'Goose', 3, NULL, 67, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Goose', 'Goose');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(77, '2022-01-08 23:22:32.000', NULL, 'Gopher', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Gopher', 'Gopher');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(78, '2022-01-08 23:22:32.000', NULL, 'Gorilla', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Gorilla', 'Gorilla');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(79, '2022-01-08 23:22:32.000', NULL, 'Grasshopper', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Grasshopper', 'Grasshopper');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(80, '2022-01-08 23:22:32.000', NULL, 'Hamster', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hamster', 'Hamster');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(81, '2022-01-08 23:22:32.000', NULL, 'Hare', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hare', 'Hare');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(82, '2022-01-08 23:22:32.000', NULL, 'Hawk', 3, NULL, 88, 1.0, 50, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hawk', 'Hawk');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(83, '2022-01-08 23:22:32.000', NULL, 'Hippopotamus', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hippopotamus', 'Hippopotamus');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(84, '2022-01-08 23:22:32.000', NULL, 'Horse', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Horse', 'Horse');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(85, '2022-01-08 23:22:32.000', NULL, 'Hummingbird', 3, NULL, 91, 1.0, 35, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hummingbird', 'Hummingbird');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(86, '2022-01-08 23:22:32.000', NULL, 'Humpback Whale', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Humpback Whale', 'Humpback Whale');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(87, '2022-01-08 23:22:32.000', NULL, 'Iguana', 2, NULL, 100, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Iguana', 'Iguana');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(88, '2022-01-08 23:22:32.000', NULL, 'Impala', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Impala', 'Impala');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(89, '2022-01-08 23:22:32.000', NULL, 'Kangaroo', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Kangaroo', 'Kangaroo');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(90, '2022-01-08 23:22:32.000', NULL, 'Ladybug', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ladybug', 'Ladybug');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(91, '2022-01-08 23:22:32.000', NULL, 'Leopard', 1, 2, 2, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Leopard', 'Leopard');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(92, '2022-01-08 23:22:32.000', NULL, 'Lion', 1, 2, 2, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Lion', 'Lion');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(93, '2022-01-08 23:22:32.000', NULL, 'Lizard', 2, NULL, 93, 1.0, 40, 60, 50, 50, 60, 70, 100, 20, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'S', 'Lizard', 'Lizard');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(94, '2022-01-08 23:22:32.000', NULL, 'Llama', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Llama', 'Llama');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(95, '2022-01-08 23:22:32.000', NULL, 'Lobster', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Lobster', 'Lobster');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(96, '2022-01-08 23:22:32.000', NULL, 'Mongoose', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Mongoose', 'Mongoose');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(97, '2022-01-08 23:22:32.000', NULL, 'King Cobra', 2, NULL, 134, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'King Cobra', 'King Cobra');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(98, '2022-01-08 23:22:32.000', NULL, 'Monkey', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Monkey', 'Monkey');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(99, '2022-01-08 23:22:32.000', NULL, 'Moose', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Moose', 'Moose');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(100, '2022-01-08 23:22:32.000', NULL, 'Mosquito', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Mosquito', 'Mosquito');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(101, '2022-01-08 23:22:32.000', NULL, 'Moth', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Moth', 'Moth');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(102, '2022-01-08 23:22:32.000', NULL, 'Mountain goat', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Mountain goat', 'Mountain goat');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(103, '2022-01-08 23:22:32.000', NULL, 'Mouse', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Mouse', 'Mouse');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(104, '2022-01-08 23:22:32.000', NULL, 'Mule', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Mule', 'Mule');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(105, '2022-01-08 23:22:32.000', NULL, 'Octopus', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Octopus', 'Octopus');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(106, '2022-01-08 23:22:32.000', NULL, 'Orca', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Orca', 'Orca');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(107, '2022-01-08 23:22:32.000', NULL, 'Ostrich', 3, NULL, 45, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ostrich', 'Ostrich');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(108, '2022-01-08 23:22:32.000', NULL, 'Otter', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Otter', 'Otter');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(109, '2022-01-08 23:22:32.000', NULL, 'Owl', 3, NULL, 116, 1.0, 40, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Owl', 'Owl');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(110, '2022-01-08 23:22:32.000', NULL, 'Ox', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Ox', 'Ox');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(111, '2022-01-08 23:22:32.000', NULL, 'Oyster', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Oyster', 'Oyster');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(112, '2022-01-08 23:22:32.000', NULL, 'Panda', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Panda', 'Panda');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(113, '2022-01-08 23:22:32.000', NULL, 'Parrot', 3, NULL, 156, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Parrot', 'Parrot');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(114, '2022-01-08 23:22:32.000', NULL, 'Peacock', 3, NULL, 127, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Peacock', 'Peacock');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(115, '2022-01-08 23:22:32.000', NULL, 'Pelican', 3, NULL, 32, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Pelican', 'Pelican');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(116, '2022-01-08 23:22:32.000', NULL, 'Penguin', 3, NULL, 123, 1.0, 50, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Penguin', 'Penguin');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(117, '2022-01-08 23:22:32.000', NULL, 'Perch', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Perch', 'Perch');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(118, '2022-01-08 23:22:32.000', NULL, 'Pheasant', 3, NULL, 127, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Pheasant', 'Pheasant');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(119, '2022-01-08 23:22:32.000', NULL, 'Pig', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Pig', 'Pig');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(120, '2022-01-08 23:22:32.000', NULL, 'Pigeon', 3, NULL, 127, 1.0, 40, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Pigeon', 'Pigeon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(121, '2022-01-08 23:22:32.000', NULL, 'Polar bear', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Polar bear', 'Polar bear');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(122, '2022-01-08 23:22:32.000', NULL, 'Porcupine', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Porcupine', 'Porcupine');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(123, '2022-01-08 23:22:32.000', NULL, 'Quail', 3, NULL, 130, 1.0, 40, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Quail', 'Quail');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(124, '2022-01-08 23:22:32.000', NULL, 'Rabbit', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Rabbit', 'Rabbit');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(125, '2022-01-08 23:22:32.000', NULL, 'Raccoon', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Raccoon', 'Raccoon');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(126, '2022-01-08 23:22:32.000', NULL, 'Rat', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Rat', 'Rat');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(127, '2022-01-08 23:22:32.000', NULL, 'Viper', 2, NULL, 134, 1.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Viper', 'Viper');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(128, '2022-01-08 23:22:32.000', NULL, 'Great Grey Owl', 3, NULL, 116, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Great Grey Owl', 'Great Grey Owl');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(129, '2022-01-09 14:26:10.000', NULL, 'Rhea', 3, NULL, 130, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Rhea', 'Rhea');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(130, '2022-01-08 23:22:32.000', NULL, 'Sea lion', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Sea lion', 'Sea lion');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(131, '2022-01-08 23:22:32.000', NULL, 'Sheep', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Sheep', 'Sheep');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(132, '2022-01-08 23:22:32.000', NULL, 'Shrew', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Shrew', 'Shrew');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(133, '2022-01-08 23:22:32.000', NULL, 'Skunk', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Skunk', 'Skunk');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(134, '2022-01-08 23:22:32.000', NULL, 'Snail', 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Snail', 'Snail');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(135, '2022-01-08 23:22:32.000', NULL, 'Spider', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Spider', 'Spider');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(136, '2022-01-08 23:22:32.000', NULL, 'Tiger', 1, 2, 2, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Tiger', 'Tiger');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(137, '2022-01-08 23:22:32.000', NULL, 'Walrus', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Walrus', 'Walrus');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(138, '2022-01-08 23:22:32.000', NULL, 'Whale', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Whale', 'Whale');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(139, '2022-01-08 23:22:32.000', NULL, 'Wolf', 1, 1, 1, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Wolf', 'Wolf');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(140, '2022-01-08 23:22:32.000', NULL, 'Zebra', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Zebra', 'Zebra');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(141, '2022-01-09 12:09:58.000', NULL, 'Great Wolf', 1, 1, 1, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Great Wolf', 'Great Wolf');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(142, '2022-01-09 12:10:06.000', NULL, 'Dingo', 1, 1, 79, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Dingo', 'Dingo');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(143, '2022-01-09 12:39:15.000', NULL, 'Black Lynx', 1, 2, 2, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Black Lynx', 'Black Lynx');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(144, '2022-01-09 14:16:30.000', NULL, 'Turkey', 3, NULL, 45, 2.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Turkey', 'Turkey');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(145, '2022-01-09 14:17:12.000', NULL, 'Swan', 3, NULL, 67, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Swan', 'Swan');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(146, '2022-01-09 14:34:31.000', NULL, 'Vulture', 3, NULL, 58, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Vulture', 'Vulture');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(147, '2022-01-09 14:47:45.000', NULL, 'Love Bird', 3, NULL, 156, 1.0, 35, 50, 50, 50, 50, 60, 100, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Love Bird', 'Love Bird');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(148, '2022-01-09 14:48:21.000', NULL, 'Hyacinth Macaw', 3, NULL, 156, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Hyacinth Macaw', 'Hyacinth Macaw');
INSERT INTO playanimaster_db.species (id_species, dt_creazione, dt_modifica, species, id_class, id_subclass, id_family, tier, base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr, reward_atk, reward_def, reward_matk, reward_mdef, reward_hp, reward_acc, reward_eva, reward_cr, reward_spd, reward_exp, description, flg_attivo, species_it, species_pt) VALUES(149, '2022-01-09 14:53:02.000', NULL, 'Emperor Penguin', 3, NULL, 123, 3.0, NULL, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'N', 'Emperor Penguin', 'Emperor Penguin');


INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(1, '2022-01-12 18:44:05.000', NULL, 1, 2, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(2, '2022-01-12 18:44:05.000', NULL, 1, 4, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(3, '2022-01-12 18:44:05.000', NULL, 1, 6, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(4, '2022-01-12 18:44:39.000', NULL, 2, 1, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(5, '2022-01-12 18:44:39.000', NULL, 2, 3, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(6, '2022-01-12 18:44:39.000', NULL, 2, 5, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(7, '2022-01-12 18:51:39.000', NULL, 61, 7, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(8, '2022-01-12 18:51:39.000', NULL, 61, 3, 0, 0);
INSERT INTO playanimaster_db.species_abilities (id_species_ability, dt_creazione, dt_modifica, id_species, id_ability, unlock_lvl, id_element) VALUES(9, '2022-01-12 18:51:39.000', NULL, 61, 8, 0, 0);

INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(1, NULL, NULL, 'Canines', 1);
INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(2, NULL, NULL, 'Felines', 1);
INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(3, NULL, NULL, 'Bovines', 1);
INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(4, NULL, NULL, 'Caprines', 1);
INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(5, NULL, NULL, 'Rodents', 1);
INSERT INTO playanimaster_db.subclasses (id_subclass, dt_creazione, dt_modifica, subclass, id_class) VALUES(6, NULL, NULL, 'Marsupials', 1);


INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(17, '2022-01-23 12:57:13.000', '2022-01-23 12:57:13.000', 'item', 1, 1, 1, 100, 1, 1, 10, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(18, '2022-01-23 12:58:15.000', '2022-01-23 12:58:15.000', 'gold', 0, 1, 1, 10, 20, 30, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(19, '2022-01-23 12:58:32.000', '2022-01-23 12:58:32.000', 'gold', 0, 1, 11, 20, 30, 40, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(20, '2022-01-23 12:58:44.000', '2022-01-23 12:58:44.000', 'gold', 0, 1, 21, 30, 40, 50, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(21, '2022-01-23 17:06:02.000', NULL, 'item', 1, 2, 1, 100, 1, 1, 10, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(22, '2022-01-23 17:06:02.000', NULL, 'gold', 0, 2, 1, 10, 20, 30, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(23, '2022-01-23 17:06:02.000', NULL, 'gold', 0, 2, 11, 20, 30, 40, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(24, '2022-01-23 17:06:02.000', NULL, 'gold', 0, 2, 21, 30, 40, 50, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(25, '2022-01-23 17:06:06.000', NULL, 'item', 1, 3, 1, 100, 1, 1, 10, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(26, '2022-01-23 17:06:06.000', NULL, 'gold', 0, 3, 1, 10, 20, 30, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(27, '2022-01-23 17:06:06.000', NULL, 'gold', 0, 3, 11, 20, 30, 40, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(28, '2022-01-23 17:06:06.000', NULL, 'gold', 0, 3, 21, 30, 40, 50, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(29, '2022-01-23 17:06:08.000', NULL, 'item', 1, 4, 1, 100, 1, 1, 10, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(30, '2022-01-23 17:06:08.000', NULL, 'gold', 0, 4, 1, 10, 20, 30, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(31, '2022-01-23 17:06:08.000', NULL, 'gold', 0, 4, 11, 20, 30, 40, 70, 0);
INSERT INTO playanimaster_db.wild_animal_drop_types (id_wild_animal_drop_type, dt_c, dt_m, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required) VALUES(32, '2022-01-23 17:06:08.000', NULL, 'gold', 0, 4, 21, 30, 40, 50, 70, 0);


INSERT INTO playanimaster_db.zone_animals (id_zone_animal, dt_creazione, dt_modifica, id_zone, id_species, lvl_min, lvl_max, chance_points, id_spawn_point) VALUES(5, '2022-01-06 23:15:44.000', NULL, 1000, 1, 2, 5, 100, 1);
INSERT INTO playanimaster_db.zone_animals (id_zone_animal, dt_creazione, dt_modifica, id_zone, id_species, lvl_min, lvl_max, chance_points, id_spawn_point) VALUES(6, '2022-01-06 23:15:48.000', NULL, 1000, 2, 2, 5, 100, 2);
INSERT INTO playanimaster_db.zone_animals (id_zone_animal, dt_creazione, dt_modifica, id_zone, id_species, lvl_min, lvl_max, chance_points, id_spawn_point) VALUES(7, '2022-01-06 23:15:50.000', NULL, 1000, 3, 10, 15, 300, 3);
INSERT INTO playanimaster_db.zone_animals (id_zone_animal, dt_creazione, dt_modifica, id_zone, id_species, lvl_min, lvl_max, chance_points, id_spawn_point) VALUES(8, '2022-01-06 23:15:53.000', NULL, 1000, 67, 2, 5, 100, 4);


INSERT INTO playanimaster_db.zones (id_zone, scene_name) VALUES(1000, 'Zone_1000');
INSERT INTO playanimaster_db.zones (id_zone, scene_name) VALUES(1001, 'Zone_0001');





INSERT INTO playanimaster_db.conversation_consequences (id_conversation_consequence, id_conversation, id_option, id_consequence) VALUES(1, 5, 1, 1);
INSERT INTO playanimaster_db.conversation_consequences (id_conversation_consequence, id_conversation, id_option, id_consequence) VALUES(2, 6, 5, 1);





INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(1, 6, 1, NULL, NULL, NULL, 'green', 'Yes, give me my first animal!', 'Si, voglio il mio primo animale!', 'Sim, quero o meu primeiro animal!');
INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(2, 6, 2, NULL, NULL, NULL, 'red', 'Not now', 'Non adesso', 'Agora nao');
INSERT INTO playanimaster_db.dialogues_options (id_dialog_option, id_dialog, option_n, `option`, option_it, option_pt, option_color, option_text, option_text_it, option_text_pt) VALUES(5, 13, 1, NULL, NULL, NULL, 'green', 'Thank you!', 'Grazie!', 'Obrigado!');




INSERT INTO playanimaster_db.npcs (id_npc, npc, `type`, id_zone, posx, posy, rangex, rangey, direction, sight_distance, npc_type_prefab, posz, wander_range, euler_x, euler_y, euler_z, gender) VALUES(1, 'Prof', 'story', 1000, 0.0, 0.0, 0, 0, 'D', 0, 'trader', 0.0, 0, 0.0, 0.0, 0.0, NULL);
INSERT INTO playanimaster_db.npcs (id_npc, npc, `type`, id_zone, posx, posy, rangex, rangey, direction, sight_distance, npc_type_prefab, posz, wander_range, euler_x, euler_y, euler_z, gender) VALUES(2, 'Assistant', 'story adjacent?', 1000, 50.0, 0.0, 0, 0, 'D', 0, 'trader', 0.0, 0, 0.0, 0.0, 0.0, NULL);



INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(1, 1000, 100.0, 100.0, 100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(2, 1000, -100.0, 100.0, 100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(3, 1000, 100.0, 100.0, -100.0, 50, 5);
INSERT INTO playanimaster_db.spawn_points (id_spawn_point, id_zone, x, y, z, radius, number_of_animals) VALUES(4, 1000, -100.0, 100.0, -100.0, 50, 5);




























