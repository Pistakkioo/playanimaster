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

        return str.split('#').filter(function (chunk)
        {
            return chunk !== '';
        }).map(function (chunk)
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

    function receiveFirstAnimal(player, idSpecies, idElement)
    {
        return postJson(BASE + 'receive_first_animal.php', {
            id_user_ig: player.id_user_ig || 0,
            id_species: idSpecies,
            id_element: idElement,
            lang: LANG
        }).then(function (envelope)
        {
            unwrap(envelope);
            apiLog('receiveFirstAnimal', '[AnimasterApi] receiveFirstAnimal', envelope);
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

    function getBattleInfo(params)
    {
        return postJson(BASE + 'solo_pve_get_battle_info.php', params).then(function (envelope)
        {
            unwrap(envelope);
            var result = parseHashResponse(envelope.response);
            apiLog('getBattleInfo', '[AnimasterApi] getBattleInfo', result);
            return result;
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
        getConversationConsequences: getConversationConsequences,
        receiveFirstAnimal: receiveFirstAnimal,
        startBattle: startBattle,
        getBattleInfo: getBattleInfo,
        getAbilityList: getAbilityList,
        getInventory: getInventory,
        getTeamInfo: getTeamInfo,
        getTeamAnimals: getTeamAnimals,
        useItem: useItem,
        recoverTeamHp: recoverTeamHp,
        getNotifications: getNotifications,
        changeAnimalNickname: changeAnimalNickname,
        sendChat: sendChat,
        pollChat: pollChat,
        sendTradeRequest: sendTradeRequest,
        pollTrade: pollTrade,
        respondTradeRequest: respondTradeRequest,
        updateTradeOffer: updateTradeOffer,
        confirmTrade: confirmTrade,
        cancelTrade: cancelTrade,
        markCharacterOffline: markCharacterOffline
    };
})();
