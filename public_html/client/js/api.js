/**
 * Legacy Animaster API wrappers (POST form-urlencoded).
 */ 
var AnimasterApi = (function ()
{
    var BASE = '/funzioni/open_actions/';
    var BATTLE_BASE = '/funzioni/battle_solo_pve/';
    var LANG = (window.ANIMASTER_BOOTSTRAP && window.ANIMASTER_BOOTSTRAP.langApi)
        ? window.ANIMASTER_BOOTSTRAP.langApi
        : '_it';
    var logLastAt = {};

    function apiLog(key)
    {
        var now = performance.now();

        if (logLastAt[key] && now - logLastAt[key] < 10000)
        {
            return;
        }

        logLastAt[key] = now;
        console.log.apply(console, Array.prototype.slice.call(arguments, 1));
    }

    function post(path, fields)
    {
        var body = new URLSearchParams();
        Object.keys(fields).forEach(function (key)
        {
            if (fields[key] !== undefined && fields[key] !== null)
            {
                body.append(key, String(fields[key]));
            }
        });

        return fetch(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (res)
        {
            return res.text();
        });
    }

    function postJson(path, fields)
    {
        return post(path, fields).then(function (text)
        {
            try
            {
                var data = JSON.parse(text);
                apiLog('postJson', '[AnimasterApi] JSON', path, data);
                return data;
            }
            catch (e)
            {
                throw new Error(text || 'Invalid JSON response');
            }
        });
    }

    function parseHashResponse(str)
    {
        if (!str || str === '')
        {
            return [];
        }

        if (Array.isArray(str))
        {
            return str;
        }

        var trimmed = String(str).trim();

        if (trimmed.charAt(0) === '[')
        {
            var rows = JSON.parse(trimmed);

            return Array.isArray(rows) ? rows : [];
        }

        var chunks = [];
        var depth = 0;
        var inString = false;
        var escaped = false;
        var start = 0;

        for (var i = 0; i < trimmed.length; i++)
        {
            var ch = trimmed.charAt(i);

            if (escaped)
            {
                escaped = false;
                continue;
            }

            if (inString)
            {
                if (ch === '\\')
                {
                    escaped = true;
                }
                else if (ch === '"')
                {
                    inString = false;
                }

                continue;
            }

            if (ch === '"')
            {
                inString = true;
                continue;
            }

            if (ch === '{' || ch === '[')
            {
                depth++;
                continue;
            }

            if (ch === '}' || ch === ']')
            {
                depth--;
                continue;
            }

            if (ch === '#' && depth === 0)
            {
                var part = trimmed.slice(start, i).trim();

                if (part)
                {
                    chunks.push(part);
                }

                start = i + 1;
            }
        }

        var tail = trimmed.slice(start).trim();

        if (tail)
        {
            chunks.push(tail);
        }

        return chunks.map(function (chunk)
        {
            return JSON.parse(chunk);
        });
    }

    function unwrap(envelope)
    {
        if (!envelope || envelope.stato !== 'OK')
        {
            throw new Error((envelope && envelope.msg) ? envelope.msg : 'Request failed');
        }

        return envelope;
    }

    function sendPresence(player)
    {
        return postJson(BASE + 'get_other_players.php', {
            id_user: player.id_user,
            id_user_ig: player.id_user_ig || 0,
            id_zone: player.id_zone,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            T_posx: player.x,
            T_posy: player.y,
            T_posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('sendPresence', '[AnimasterApi] sendPresence', result);
            return result;
        });
    }

    function fetchWildAnimals(player)
    {
        return postJson(BASE + 'get_wild_animals.php', {
            id_user: player.id_user,
            id_user_ig: player.id_user_ig,
            id_zone: player.id_zone,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('fetchWildAnimals', '[AnimasterApi] fetchWildAnimals', result);
            return result;
        });
    }

    function getSpawnPoints(player)
    {
        return postJson(BASE + 'get_spawn_points.php', {
            id_zone: player.id_zone,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getSpawnPoints', '[AnimasterApi] getSpawnPoints', result);
            return result;
        });
    }

    function checkSpawn(player, idSpawnPoint)
    {
        return postJson(BASE + 'check_spawn.php', {
            id_zone: player.id_zone,
            id_spawn_point: idSpawnPoint,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('checkSpawn', '[AnimasterApi] checkSpawn', envelope);
            return envelope;
        });
    }

    function fetchNpcs(player)
    {
        return postJson(BASE + 'get_npcs.php', {
            id_user: player.id_user,
            id_user_ig: player.id_user_ig || 0,
            id_zone: player.id_zone,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('fetchNpcs', '[AnimasterApi] fetchNpcs', result);
            return result;
        });
    }

    function fetchWorldTiles(idZone)
    {
        return postJson(BASE + 'get_world_tiles.php', {
            id_zone: idZone,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = JSON.parse(envelope.response || '{}');
            apiLog('fetchWorldTiles', '[AnimasterApi] fetchWorldTiles', result);
            return result;
        });
    }

    function getConversationConsequences(player, idConversation, idOption)
    {
        return postJson(BASE + 'get_conversation_consequences.php', {
            id_user_ig: player.id_user_ig || 0,
            id_conversation: idConversation,
            id_option: idOption || 0,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('getConversationConsequences', '[AnimasterApi] getConversationConsequences', envelope);
            return envelope;
        });
    }


    function startBattle(player, wildId)
    {
        return postJson(BASE + 'solo_pve_start_battle.php', {
            id_user_ig: player.id_user_ig || 0,
            id_zone: player.id_zone,
            id_wild_animal: wildId,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var rows = parseHashResponse(envelope.response);
            var result = rows[0];
            apiLog('startBattle', '[AnimasterApi] startBattle', result);
            return result;
        });
    }

    function startPartyBattle(player, wildId)
    {
        return postJson(BASE + 'party_pve_start_battle.php', {
            id_user_ig: player.id_user_ig || 0,
            id_zone: player.id_zone,
            id_wild_animal: wildId,
            pos_x: player.x,
            pos_z: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var rows = parseHashResponse(envelope.response);
            var result = rows[0] || {};
            apiLog('startPartyBattle', '[AnimasterApi] startPartyBattle', result);

            return {
                id_battle: result.id_battle,
                battle_type: result.battle_type || 'party_pve',
                current_battle_turn: result.current_battle_turn || 0
            };
        });
    }

    /**
     * Unified battle_meta envelope (005c Phase 4): every battle_type
     * (solo_pve, pvp, party_pve, ...) is served by its own endpoint but they
     * all emit a single `battle_meta` key with the same envelope shape.
     */
    function parseBattleMeta(envelope)
    {
        var meta = {};

        if (envelope && envelope.battle_meta)
        {
            if (typeof envelope.battle_meta === 'object')
            {
                meta = envelope.battle_meta;
            }
            else
            {
                try
                {
                    meta = JSON.parse(envelope.battle_meta);
                }
                catch (e)
                {
                    meta = {};
                }
            }
        }

        return meta;
    }

    function getBattleInfo(params)
    {
        var endpoint = 'solo_pve_get_battle_info.php';

        if (params.battle_type === 'pvp')
        {
            endpoint = 'pvp_get_battle_info.php';
        }
        else if (params.battle_type === 'party_pve')
        {
            endpoint = 'party_pve_get_battle_info.php';
        }

        return postJson(BASE + endpoint, params).then(function (envelope)
        {
            unwrap(envelope);
            var moves = parseHashResponse(envelope.response);
            var meta = parseBattleMeta(envelope);
            apiLog('getBattleInfo', '[AnimasterApi] getBattleInfo', moves, meta);

            return {
                moves: moves,
                meta: meta
            };
        });
    }

    function getAbilityList(idUserIg, idAnimal, lvl)
    {
        return postJson(BASE + 'get_ability_list.php', {
            id_user_ig: idUserIg,
            id_active_animal: idAnimal,
            lvl: lvl,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getAbilityList', '[AnimasterApi] getAbilityList', result);
            return result;
        }); 
    }

    function markCharacterOffline()
    {
        return fetch('mark_character_offline.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: ''
        }).then(function (res)
        {
            return res.json();
        }).then(function (data)
        {
            apiLog('markCharacterOffline', '[AnimasterApi] markCharacterOffline', data);

            if (!data || !data.ok)
            {
                throw new Error((data && data.message) ? data.message : 'Could not mark character offline');
            }

            return data;
        });
    }

    function getInventory(player, inBattle)
    {
        return postJson(BASE + 'get_inventory.php', {
            id_user_ig: player.id_user_ig || 0,
            flg_usable_in_battle: inBattle ? 'S' : 'N',
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getInventory', '[AnimasterApi] getInventory', result);
            return result;
        });
    }

    function getSelfInfo(player)
    {
        return postJson(BASE + 'get_self_info.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);

            if (!envelope.response)
            {
                throw new Error('Empty self info');
            }

            var payload = typeof envelope.response === 'string'
                ? JSON.parse(envelope.response)
                : envelope.response;

            apiLog('getSelfInfo', '[AnimasterApi] getSelfInfo', payload);
            return payload;
        });
    }

    function getTeamInfo(player)
    {
        return postJson(BASE + 'get_team_info.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getTeamInfo', '[AnimasterApi] getTeamInfo', result);
            return result;
        });
    }

    function getQuests(player)
    {
        return postJson(BASE + 'get_quests.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getQuests', '[AnimasterApi] getQuests', result);
            return result;
        });
    }

    function useItem(player, idItemType, idAnimal)
    {
        return postJson(BASE + 'use_item.php', {
            id_user_ig: player.id_user_ig || 0,
            id_item_type: idItemType,
            id_animal: idAnimal,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('useItem', '[AnimasterApi] useItem', envelope);
            return envelope;
        });
    }

    function getTeamAnimals(player, params)
    {
        var fields = {
            id_user_ig: player.id_user_ig || 0,
            motive: params.motive || '',
            id_active_animal: params.id_active_animal || 0,
            id_item_type_selected: params.id_item_type_selected || 0,
            lang: LANG
        };

        return postJson(BASE + 'get_team_animals.php', fields).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getTeamAnimals', '[AnimasterApi] getTeamAnimals', result);
            return result;
        });
    }

    function recoverTeamHp(player)
    {
        return postJson(BASE + 'recover_team_hp.php', {
            id_user_ig: player.id_user_ig || 0,
            id_user: player.id_user || 0,
            id_zone: player.id_zone,
            posx: player.x,
            posy: player.y,
            posz: player.z,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);

            if (!envelope.response)
            {
                throw new Error('Empty recovery response');
            }

            var profile = JSON.parse(envelope.response);
            apiLog('recoverTeamHp', '[AnimasterApi] recoverTeamHp', profile);
            return profile;
        });
    }

    function getNotifications(player)
    {
        return postJson(BASE + 'get_notifications.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getNotifications', '[AnimasterApi] getNotifications', result);
            return result;
        });
    }

    function changeAnimalNickname(player, idAnimal, nickname)
    {
        return postJson(BASE + 'change_animal_nickname.php', {
            id_user_ig: player.id_user_ig || 0,
            id_animal: idAnimal,
            nickname: nickname,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('changeAnimalNickname', '[AnimasterApi] changeAnimalNickname', envelope);
            return envelope;
        });
    }

    function saveTeamOrder(player, animalIds)
    {
        return postJson(BASE + 'save_team_order.php', {
            id_user_ig: player.id_user_ig || 0,
            team_order: (animalIds || []).join(','),
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('saveTeamOrder', '[AnimasterApi] saveTeamOrder', envelope);
            return envelope;
        });
    }

    function sendChat(player, message)
    {
        return postJson(BASE + 'send_chat.php', {
            id_user_ig: player.id_user_ig || 0,
            message: message,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);

            if (!envelope.response)
            {
                throw new Error('Empty chat response');
            }

            var msg = JSON.parse(envelope.response);
            apiLog('sendChat', '[AnimasterApi] sendChat', msg);
            return msg;
        });
    }

    function pollChat(player, sinceId, sinceDt)
    {
        return postJson(BASE + 'poll_chat.php', {
            id_user_ig: player.id_user_ig || 0,
            since_id: sinceId || 0,
            since_dt: sinceDt || '',
            posx: player.x,
            posz: player.z,
            id_zone: player.id_zone,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);

            if (!envelope.response)
            {
                return [];
            }

            var result = JSON.parse(envelope.response);
            apiLog('pollChat', '[AnimasterApi] pollChat', result);
            return Array.isArray(result) ? result : [];
        });
    }

    function parseTradeEnvelope(envelope)
    {
        unwrap(envelope);

        if (!envelope.response)
        {
            return {};
        }

        return JSON.parse(envelope.response);
    }

    function parseDuelEnvelope(envelope)
    {
        return parseTradeEnvelope(envelope);
    }

    function parsePartyEnvelope(envelope)
    {
        return parseTradeEnvelope(envelope);
    }

    function createParty(player)
    {
        return postJson(BASE + 'party_create.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function sendPartyInvite(player, idTarget)
    {
        return postJson(BASE + 'party_invite.php', {
            id_user_ig: player.id_user_ig || 0,
            id_target: idTarget,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);

            return envelope;
        });
    }

    function pollParty(player)
    {
        return postJson(BASE + 'party_poll.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function respondPartyInvite(player, idPartyInvite, accept)
    {
        return postJson(BASE + 'party_respond.php', {
            id_user_ig: player.id_user_ig || 0,
            id_party_invite: idPartyInvite,
            accept: accept ? 'S' : 'N',
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function leaveParty(player)
    {
        return postJson(BASE + 'party_leave.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function kickPartyMember(player, idTarget)
    {
        return postJson(BASE + 'party_kick.php', {
            id_user_ig: player.id_user_ig || 0,
            id_target: idTarget,
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function transferPartyLeader(player, idNewLeader)
    {
        return postJson(BASE + 'party_transfer_leader.php', {
            id_user_ig: player.id_user_ig || 0,
            id_new_leader: idNewLeader,
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function setPartySetting(player, setting, value)
    {
        return postJson(BASE + 'party_set_settings.php', {
            id_user_ig: player.id_user_ig || 0,
            setting: setting,
            value: value ? 'S' : 'N',
            lang: LANG
        }).then(function (envelope)
        {
            return parsePartyEnvelope(envelope);
        });
    }

    function sendDuelRequest(player, idTarget)
    {
        return postJson(BASE + 'send_duel_request.php', {
            id_user_ig: player.id_user_ig || 0,
            id_target: idTarget,
            lang: LANG
        }).then(function (envelope)
        {
            return parseDuelEnvelope(envelope);
        });
    }

    function pollDuel(player)
    {
        return postJson(BASE + 'poll_duel.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            return parseDuelEnvelope(envelope);
        });
    }

    function respondDuelRequest(player, idDuelRequest, accept)
    {
        return postJson(BASE + 'respond_duel_request.php', {
            id_user_ig: player.id_user_ig || 0,
            id_duel_request: idDuelRequest,
            accept: accept ? 'S' : 'N',
            lang: LANG
        }).then(function (envelope)
        {
            return parseDuelEnvelope(envelope);
        });
    }

    function sendTradeRequest(player, idTarget)
    {
        return postJson(BASE + 'send_trade_request.php', {
            id_user_ig: player.id_user_ig || 0,
            id_target: idTarget,
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    function pollTrade(player)
    {
        return postJson(BASE + 'poll_trade.php', {
            id_user_ig: player.id_user_ig || 0,
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    function respondTradeRequest(player, idTradeRequest, accept)
    {
        return postJson(BASE + 'respond_trade_request.php', {
            id_user_ig: player.id_user_ig || 0,
            id_trade_request: idTradeRequest,
            accept: accept ? 'S' : 'N',
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    function updateTradeOffer(player, idTrade, gold, items)
    {
        return postJson(BASE + 'update_trade_offer.php', {
            id_user_ig: player.id_user_ig || 0,
            id_trade: idTrade,
            gold: gold || 0,
            items: JSON.stringify(items || []),
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    function confirmTrade(player, idTrade)
    {
        return postJson(BASE + 'confirm_trade.php', {
            id_user_ig: player.id_user_ig || 0,
            id_trade: idTrade,
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    function cancelTrade(player, idTrade)
    {
        return postJson(BASE + 'cancel_trade.php', {
            id_user_ig: player.id_user_ig || 0,
            id_trade: idTrade,
            lang: LANG
        }).then(function (envelope)
        {
            return parseTradeEnvelope(envelope);
        });
    }

    return {
        LANG: LANG,
        parseHashResponse: parseHashResponse,
        sendPresence: sendPresence,
        fetchWildAnimals: fetchWildAnimals,
        getSpawnPoints: getSpawnPoints,
        checkSpawn: checkSpawn,
        fetchNpcs: fetchNpcs,
        fetchWorldTiles: fetchWorldTiles,
        getConversationConsequences: getConversationConsequences,
        startBattle: startBattle,
        startPartyBattle: startPartyBattle,
        getBattleInfo: getBattleInfo,
        getAbilityList: getAbilityList,
        getInventory: getInventory,
        getSelfInfo: getSelfInfo,
        getTeamInfo: getTeamInfo,
        getQuests: getQuests,
        getTeamAnimals: getTeamAnimals,
        useItem: useItem,
        recoverTeamHp: recoverTeamHp,
        getNotifications: getNotifications,
        changeAnimalNickname: changeAnimalNickname,
        saveTeamOrder: saveTeamOrder,
        sendChat: sendChat,
        pollChat: pollChat,
        sendTradeRequest: sendTradeRequest,
        pollTrade: pollTrade,
        respondTradeRequest: respondTradeRequest,
        updateTradeOffer: updateTradeOffer,
        confirmTrade: confirmTrade,
        cancelTrade: cancelTrade,
        createParty: createParty,
        sendPartyInvite: sendPartyInvite,
        pollParty: pollParty,
        respondPartyInvite: respondPartyInvite,
        leaveParty: leaveParty,
        kickPartyMember: kickPartyMember,
        transferPartyLeader: transferPartyLeader,
        setPartySetting: setPartySetting,
        sendDuelRequest: sendDuelRequest,
        pollDuel: pollDuel,
        respondDuelRequest: respondDuelRequest,
        markCharacterOffline: markCharacterOffline
    };
})();
