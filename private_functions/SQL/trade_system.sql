-- Player-to-player trade system (ANIMASTER)
-- Run once on playanimaster_db.

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
