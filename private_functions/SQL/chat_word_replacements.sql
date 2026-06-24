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


-- ---------------------------------------------------------------------------
-- English
-- ---------------------------------------------------------------------------

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
