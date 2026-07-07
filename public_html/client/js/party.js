/**
 * Party roster, invites, and # chat prerequisite (users_ig.id_party).
 */
var AnimasterParty = (function ()
{
    var INVITE_SECONDS = 30;
    var POLL_MS = 1200;
    var PARTY_FAR_DISTANCE = 50;

    var inviteOverlay = null;
    var inviteTextEl = null;
    var inviteTimerFill = null;
    var inviteAcceptBtn = null;
    var inviteDeclineBtn = null;

    var panel = null;
    var memberListEl = null;
    var statusEl = null;
    var createBtn = null;
    var leaveBtn = null;
    var closeBtn = null;
    var toggleBtn = null;
    var settingsEl = null;
    var inactivityVoteToggle = null;

    var hudEl = null;
    var hudListEl = null;

    var playerRef = null;
    var pollTimer = null;
    var busy = false;
    var partyState = null;
    var onPartyBattleJoin = null;

    function setOnPartyBattleJoin(handler)
    {
        onPartyBattleJoin = typeof handler === 'function' ? handler : null;
    }

    function maybeJoinPartyBattle(battleInfo)
    {
        if (!battleInfo || !battleInfo.id_battle || typeof onPartyBattleJoin !== 'function')
        {
            return;
        }

        onPartyBattleJoin(battleInfo);
    }
    var incomingInvite = null;
    var outgoingInvite = null;
    var inviteLocked = false;
    var inviteExpiresAt = 0;
    var inviteTotalMs = 0;
    var expiryTimer = null;
    var dismissedInviteIds = {};

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function errorText(code)
    {
        var key = String(code || '').toUpperCase();

        if (key === 'OFFLINE')
        {
            return t('party.error_offline');
        }

        if (key === 'TOO_FAR')
        {
            return t('party.error_too_far');
        }

        if (key === 'NOT_LEADER')
        {
            return t('party.error_not_leader');
        }

        if (key === 'PARTY_FULL')
        {
            return t('party.error_party_full');
        }

        if (key === 'TARGET_IN_PARTY')
        {
            return t('party.error_target_in_party');
        }

        if (key === 'TARGET_BUSY' || key === 'BUSY')
        {
            return key === 'TARGET_BUSY' ? t('party.error_target_busy') : t('party.error_busy');
        }

        if (key === 'ALREADY_IN_PARTY')
        {
            return t('party.error_already_in_party');
        }

        if (key === 'ALREADY_PENDING')
        {
            return t('party.error_already_pending');
        }

        if (key === 'EXPIRED' || key === 'NOT_FOUND')
        {
            return t('party.expired');
        }

        return t('party.error_generic');
    }

    function init()
    {
        inviteOverlay = document.getElementById('party-invite-overlay');
        inviteTextEl = document.getElementById('party-invite-text');
        inviteTimerFill = document.getElementById('party-invite-timer-fill');
        inviteAcceptBtn = document.getElementById('party-invite-accept');
        inviteDeclineBtn = document.getElementById('party-invite-decline');

        panel = document.getElementById('party-panel');
        memberListEl = document.getElementById('party-member-list');
        statusEl = document.getElementById('party-status');
        createBtn = document.getElementById('party-create-btn');
        leaveBtn = document.getElementById('party-leave-btn');
        closeBtn = document.getElementById('party-close');
        toggleBtn = document.getElementById('party-toggle');
        settingsEl = document.getElementById('party-settings');
        inactivityVoteToggle = document.getElementById('party-inactivity-vote-toggle');
        hudEl = document.getElementById('party-hud');
        hudListEl = document.getElementById('party-hud-list');

        if (toggleBtn)
        {
            toggleBtn.addEventListener('click', function ()
            {
                toggle();
            });
        }

        if (inactivityVoteToggle)
        {
            inactivityVoteToggle.addEventListener('change', onToggleInactivityVoteSetting);
        }

        if (inviteAcceptBtn)
        {
            inviteAcceptBtn.addEventListener('click', function ()
            {
                respondIncoming(true);
            });
        }

        if (inviteDeclineBtn)
        {
            inviteDeclineBtn.addEventListener('click', function ()
            {
                respondIncoming(false);
            });
        }

        if (createBtn)
        {
            createBtn.addEventListener('click', onCreateParty);
        }

        if (leaveBtn)
        {
            leaveBtn.addEventListener('click', onLeaveParty);
        }

        if (closeBtn)
        {
            closeBtn.addEventListener('click', close);
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'KeyY' && !e.ctrlKey && !e.metaKey && !e.altKey)
            {
                var tag = document.activeElement && document.activeElement.tagName;

                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT')
                {
                    return;
                }

                if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
                {
                    return;
                }

                if (typeof AnimasterDialog !== 'undefined' && AnimasterDialog.isActive())
                {
                    return;
                }

                e.preventDefault();
                toggle();
            }

            if (e.code === 'Escape' && isPanelOpen())
            {
                if (inviteOverlay && !inviteOverlay.hidden)
                {
                    return;
                }

                close();
            }
        });

        pollTimer = setInterval(poll, POLL_MS);
    }

    function setPlayer(player)
    {
        playerRef = player;
        poll();
    }

    function hpRatio(animal)
    {
        if (!animal)
        {
            return 0;
        }

        var hp = parseInt(animal.current_hp, 10) || 0;
        var maxHp = parseInt(animal.max_hp, 10) || 1;
        var ratio = hp / maxHp;

        if (ratio < 0)
        {
            ratio = 0;
        }

        if (ratio > 1)
        {
            ratio = 1;
        }

        return ratio;
    }

    function hpBarClass(ratio)
    {
        if (ratio >= 0.5)
        {
            return 'hp-high';
        }

        if (ratio >= 0.2)
        {
            return 'hp-mid';
        }

        return 'hp-low';
    }

    function spriteDataFromAnimal(animal)
    {
        if (!animal)
        {
            return null;
        }

        return {
            id_species: animal.id_species,
            species: animal.species || animal.species_key,
            species_key: animal.species_key,
            id_wild_animal: parseInt(animal.id_animal, 10) || 0
        };
    }

    function renderHudAnimalThumb(thumbEl, animal)
    {
        if (!thumbEl)
        {
            return;
        }

        thumbEl.innerHTML = '';

        if (!animal)
        {
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.className = 'party-hud-pet-thumb-canvas';
        canvas.width = 24;
        canvas.height = 24;
        canvas.setAttribute('aria-hidden', 'true');
        thumbEl.appendChild(canvas);

        if (typeof AnimasterWildSprites === 'undefined')
        {
            return;
        }

        var ctx = canvas.getContext('2d');

        if (!ctx)
        {
            return;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var elementColor = animal.element_color || '#888888';

        AnimasterWildSprites.draw(ctx, canvas.width / 2, canvas.height / 2, spriteDataFromAnimal(animal), {
            elementColor: elementColor,
            pixelSize: 2
        });
    }

    function renderHudMiniBar(container, ratio, barClass)
    {
        container.innerHTML = '';

        var track = document.createElement('div');
        track.className = 'team-mini-bar';

        var fill = document.createElement('div');
        fill.className = 'team-mini-bar-fill ' + barClass;
        fill.style.width = Math.round(ratio * 100) + '%';

        track.appendChild(fill);
        container.appendChild(track);
    }

    function findMemberByUserId(userId)
    {
        userId = parseInt(userId, 10) || 0;

        if (!partyState || !partyState.members || userId <= 0)
        {
            return null;
        }

        var members = partyState.members;
        var i;

        for (i = 0; i < members.length; i++)
        {
            if (parseInt(members[i].id_user_ig, 10) === userId)
            {
                return members[i];
            }
        }

        return null;
    }

    function resolveMemberWorldPosition(member)
    {
        if (!member)
        {
            return null;
        }

        if (typeof AnimasterWorld !== 'undefined')
        {
            var others = AnimasterWorld.getOthers() || [];
            var i;

            for (i = 0; i < others.length; i++)
            {
                if (parseInt(others[i].id_player, 10) === parseInt(member.id_user_ig, 10))
                {
                    var liveX = parseFloat(others[i].serverPositionX);
                    var liveZ = parseFloat(others[i].serverPositionZ);

                    if (isFinite(liveX) && isFinite(liveZ))
                    {
                        return { x: liveX, z: liveZ };
                    }
                }
            }
        }

        var x = parseFloat(member.position_x);
        var z = parseFloat(member.position_z);

        if (!isFinite(x) || !isFinite(z))
        {
            return null;
        }

        return { x: x, z: z };
    }

    function isMemberTooFar(member)
    {
        if (!member || !playerRef)
        {
            return false;
        }

        if (parseInt(member.id_user_ig, 10) === parseInt(playerRef.id_user_ig, 10))
        {
            return false;
        }

        if (!member.flg_online)
        {
            return false;
        }

        if (parseInt(member.id_zone, 10) !== parseInt(playerRef.id_zone, 10))
        {
            return true;
        }

        var pos = resolveMemberWorldPosition(member);

        if (!pos)
        {
            return false;
        }

        var dx = playerRef.x - pos.x;
        var dz = playerRef.z - pos.z;

        return Math.sqrt((dx * dx) + (dz * dz)) > PARTY_FAR_DISTANCE;
    }

    function isPlayerFarFromParty(userId)
    {
        return isMemberTooFar(findMemberByUserId(userId));
    }

    function isSelfMember(member)
    {
        return !!(member && playerRef && parseInt(member.id_user_ig, 10) === parseInt(playerRef.id_user_ig, 10));
    }

    function isFarFromAnyPartyMember()
    {
        if (!partyState || !partyState.members)
        {
            return false;
        }

        return partyState.members.some(function (member)
        {
            return !isSelfMember(member) && isMemberTooFar(member);
        });
    }

    function computeBearingDeg(member)
    {
        if (!member || !playerRef || isSelfMember(member))
        {
            return null;
        }

        if (parseInt(member.id_zone, 10) !== parseInt(playerRef.id_zone, 10))
        {
            return null;
        }

        var pos = resolveMemberWorldPosition(member);

        if (!pos)
        {
            return null;
        }

        var dx = pos.x - playerRef.x;
        var dz = pos.z - playerRef.z;

        if (Math.abs(dx) < 0.01 && Math.abs(dz) < 0.01)
        {
            return null;
        }

        return ((Math.atan2(dz, dx) * 180 / Math.PI) + 90 + 360) % 360;
    }

    /**
     * Bearings (degrees, 0 = north/up, clockwise) from the local player to each
     * out-of-range party member, for drawing direction arrows near the player's avatar.
     * Members in a different zone (no comparable world position) are omitted.
     */
    function getFarPartyBearings()
    {
        if (!partyState || !partyState.members)
        {
            return [];
        }

        var bearings = [];

        partyState.members.forEach(function (member)
        {
            if (isSelfMember(member) || !isMemberTooFar(member))
            {
                return;
            }

            var bearing = computeBearingDeg(member);

            if (bearing === null)
            {
                return;
            }

            bearings.push({
                id_user_ig: parseInt(member.id_user_ig, 10) || 0,
                display_name: member.display_name || 'Player',
                bearingDeg: bearing
            });
        });

        return bearings;
    }

    function tickDistanceIndicators()
    {
        if (!hudListEl || !partyState || !partyState.id_party)
        {
            return;
        }

        var cards = hudListEl.querySelectorAll('.party-hud-member[data-user-id]');
        var i;

        for (i = 0; i < cards.length; i++)
        {
            var card = cards[i];
            var member = findMemberByUserId(card.getAttribute('data-user-id'));
            var far = isMemberTooFar(member);
            var icon = card.querySelector('.party-hud-far-icon');

            if (icon)
            {
                icon.hidden = !far;
            }

            card.classList.toggle('is-far', far);
        }
    }

    function renderClassIcon(classEl, member)
    {
        if (!classEl)
        {
            return;
        }

        classEl.className = 'party-hud-class';
        classEl.innerHTML = '';

        var code = member.player_class_code || '';

        if (code === 'nerd')
        {
            classEl.classList.add('party-hud-class-nerd');
        }
        else if (code === 'stud')
        {
            classEl.classList.add('party-hud-class-stud');
        }

        var initial = document.createElement('span');
        initial.className = 'party-hud-class-initial';
        initial.textContent = (member.display_name || '?').charAt(0).toUpperCase() || '?';
        classEl.appendChild(initial);
    }

    function renderHud()
    {
        if (!hudEl || !hudListEl)
        {
            return;
        }

        hudListEl.innerHTML = '';

        if (!partyState || !partyState.id_party)
        {
            hudEl.hidden = true;
            hudEl.setAttribute('aria-hidden', 'true');

            return;
        }

        hudEl.hidden = false;
        hudEl.setAttribute('aria-hidden', 'false');

        (partyState.members || []).forEach(function (member)
        {
            var card = document.createElement('div');
            card.className = 'party-hud-member';
            card.setAttribute('data-user-id', String(member.id_user_ig || ''));

            if (!member.flg_online)
            {
                card.classList.add('is-offline');
            }

            if (isMemberTooFar(member))
            {
                card.classList.add('is-far');
            }

            if (playerRef && parseInt(member.id_user_ig, 10) === parseInt(playerRef.id_user_ig, 10))
            {
                card.classList.add('is-self');
            }

            var head = document.createElement('div');
            head.className = 'party-hud-head';

            var classWrap = document.createElement('div');
            classWrap.className = 'party-hud-class-wrap';

            var classEl = document.createElement('div');
            renderClassIcon(classEl, member);
            classWrap.appendChild(classEl);

            if (member.is_leader)
            {
                var star = document.createElement('span');
                star.className = 'party-hud-leader-star';
                star.textContent = '\u2605';
                star.title = t('party.leader');
                star.setAttribute('aria-label', t('party.leader'));
                classWrap.appendChild(star);
            }

            head.appendChild(classWrap);

            var farIcon = document.createElement('span');
            farIcon.className = 'party-hud-far-icon';
            farIcon.textContent = '!';
            farIcon.title = t('party.far_tooltip');
            farIcon.setAttribute('aria-label', t('party.far_tooltip'));
            farIcon.hidden = !isMemberTooFar(member);
            head.appendChild(farIcon);

            var name = document.createElement('span');
            name.className = 'party-hud-name';
            name.textContent = member.display_name || 'Player';
            head.appendChild(name);

            card.appendChild(head);

            var petRow = document.createElement('div');
            petRow.className = 'party-hud-pet';

            var animal = member.lead_animal;

            if (animal)
            {
                var thumb = document.createElement('span');
                thumb.className = 'party-hud-pet-thumb';
                renderHudAnimalThumb(thumb, animal);
                petRow.appendChild(thumb);

                var meta = document.createElement('div');
                meta.className = 'party-hud-pet-meta';

                var lvl = document.createElement('span');
                lvl.className = 'party-hud-pet-lvl';
                lvl.textContent = t('team.lv_short', { level: parseInt(animal.lvl, 10) || 1 });
                meta.appendChild(lvl);

                var hpWrap = document.createElement('div');
                hpWrap.className = 'party-hud-pet-hp';
                renderHudMiniBar(hpWrap, hpRatio(animal), hpBarClass(hpRatio(animal)));
                meta.appendChild(hpWrap);

                petRow.appendChild(meta);
            }
            else
            {
                var empty = document.createElement('span');
                empty.className = 'party-hud-pet-empty';
                empty.textContent = t('party.hud_no_animal');
                petRow.appendChild(empty);
            }

            card.appendChild(petRow);
            hudListEl.appendChild(card);
        });
    }

    function getPartyState()
    {
        return partyState;
    }

    function isInParty()
    {
        return !!(partyState && partyState.id_party);
    }

    function isLeader()
    {
        return !!(partyState && partyState.is_leader);
    }

    function isPanelOpen()
    {
        return panel && !panel.hidden;
    }

    function open()
    {
        if (!panel)
        {
            return;
        }

        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
        renderPanel();
        poll();
    }

    function close()
    {
        if (!panel)
        {
            return;
        }

        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
    }

    function toggle()
    {
        if (isPanelOpen())
        {
            close();
        }
        else
        {
            open();
        }
    }

    function stopExpiryTimer()
    {
        if (expiryTimer)
        {
            clearInterval(expiryTimer);
            expiryTimer = null;
        }
    }

    function showInviteOverlay(invite)
    {
        if (!inviteOverlay || !inviteTextEl)
        {
            return;
        }

        incomingInvite = invite;
        inviteLocked = true;
        var secondsLeft = Math.max(1, parseInt(invite.seconds_left, 10) || INVITE_SECONDS);
        inviteTotalMs = secondsLeft * 1000;
        inviteExpiresAt = Date.now() + inviteTotalMs;

        inviteTextEl.textContent = t('party.incoming_title', { name: invite.sender_name || 'Player' });
        inviteOverlay.hidden = false;
        inviteOverlay.setAttribute('aria-hidden', 'false');

        stopExpiryTimer();
        expiryTimer = setInterval(updateInviteTimer, 200);
        updateInviteTimer();
    }

    function hideInviteOverlay()
    {
        if (!inviteOverlay)
        {
            return;
        }

        inviteOverlay.hidden = true;
        inviteOverlay.setAttribute('aria-hidden', 'true');
        incomingInvite = null;
        inviteLocked = false;
        stopExpiryTimer();
    }

    function updateInviteTimer()
    {
        if (!inviteTimerFill || !incomingInvite)
        {
            return;
        }

        var left = Math.max(0, inviteExpiresAt - Date.now());
        var totalMs = inviteTotalMs > 0 ? inviteTotalMs : (INVITE_SECONDS * 1000);
        var pct = Math.max(0, Math.min(100, (left / totalMs) * 100));
        inviteTimerFill.style.width = pct + '%';

        if (left <= 0)
        {
            hideInviteOverlay();
            poll();
        }
    }

    function respondIncoming(accept)
    {
        if (!playerRef || !incomingInvite || busy)
        {
            return;
        }

        busy = true;
        var inviteId = incomingInvite.id_party_invite;

        AnimasterApi.respondPartyInvite(playerRef, inviteId, accept).then(function (result)
        {
            dismissedInviteIds[inviteId] = true;

            if (accept && result.party)
            {
                partyState = result.party;

                if (typeof AnimasterNotifications !== 'undefined')
                {
                    AnimasterNotifications.showLocal(t('party.joined'), 'party', '');
                }
            }
        }).catch(function (err)
        {
            var code = err && err.message ? err.message : 'GENERIC';

            if (code === 'EXPIRED' || code === 'NOT_FOUND')
            {
                dismissedInviteIds[inviteId] = true;
            }

            alert(errorText(code));
        }).finally(function ()
        {
            busy = false;
            hideInviteOverlay();
            renderPanel();
            poll();
        });
    }

    function requestToPlayer(targetInfo)
    {
        if (!playerRef || !targetInfo || busy)
        {
            return;
        }

        var targetId = parseInt(targetInfo.id, 10) || 0;

        if (targetId <= 0)
        {
            return;
        }

        if (!partyState || !partyState.is_leader)
        {
            if (!partyState)
            {
                onCreateParty(function ()
                {
                    sendInvite(targetId);
                });

                return;
            }

            alert(t('party.error_not_leader'));

            return;
        }

        sendInvite(targetId);
    }

    function sendInvite(targetId)
    {
        busy = true;

        AnimasterApi.sendPartyInvite(playerRef, targetId).then(function ()
        {
            if (typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.invite_sent'), 'party', '');
            }

            poll();
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
        });
    }

    function onCreateParty(done)
    {
        if (!playerRef || busy)
        {
            return;
        }

        if (partyState)
        {
            if (typeof done === 'function')
            {
                done();
            }

            return;
        }

        busy = true;

        AnimasterApi.createParty(playerRef).then(function (result)
        {
            partyState = result.party || null;
            renderPanel();

            if (typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.created'), 'party', '');
            }

            if (typeof done === 'function')
            {
                done();
            }
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
        });
    }

    function onLeaveParty()
    {
        if (!playerRef || !partyState || busy)
        {
            return;
        }

        busy = true;

        AnimasterApi.leaveParty(playerRef).then(function (result)
        {
            partyState = result.party || null;

            if (result.disbanded && typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.disbanded'), 'party', '');
            }
            else if (typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.left'), 'party', '');
            }

            renderPanel();
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
            poll();
        });
    }

    function onKickMember(memberId)
    {
        if (!playerRef || !partyState || !partyState.is_leader || busy)
        {
            return;
        }

        busy = true;

        AnimasterApi.kickPartyMember(playerRef, memberId).then(function (result)
        {
            partyState = result.party || null;

            if (result.disbanded && typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.disbanded'), 'party', '');
            }

            renderPanel();
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
        });
    }

    function onPromoteMember(memberId)
    {
        if (!playerRef || !partyState || !partyState.is_leader || busy)
        {
            return;
        }

        busy = true;

        AnimasterApi.transferPartyLeader(playerRef, memberId).then(function (result)
        {
            partyState = result.party || null;
            renderPanel();
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
        });
    }

    function onToggleInactivityVoteSetting()
    {
        if (!playerRef || !partyState || !inactivityVoteToggle)
        {
            return;
        }

        var desired = inactivityVoteToggle.checked;

        if (!partyState.is_leader)
        {
            // Read-only for non-leaders: snap back to the actual party state.
            inactivityVoteToggle.checked = !!partyState.allow_inactivity_vote;

            return;
        }

        if (busy)
        {
            inactivityVoteToggle.checked = !!partyState.allow_inactivity_vote;

            return;
        }

        busy = true;

        AnimasterApi.setPartySetting(playerRef, 'allow_inactivity_vote', desired).then(function (result)
        {
            partyState = result.party || partyState;
            renderPanel();
        }).catch(function (err)
        {
            inactivityVoteToggle.checked = !!partyState.allow_inactivity_vote;
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
        });
    }

    function renderPanel()
    {
        if (!memberListEl)
        {
            return;
        }

        memberListEl.innerHTML = '';

        if (!partyState)
        {
            if (statusEl)
            {
                statusEl.textContent = t('party.no_party');
            }

            if (createBtn)
            {
                createBtn.hidden = false;
            }

            if (leaveBtn)
            {
                leaveBtn.hidden = true;
            }

            if (settingsEl)
            {
                settingsEl.hidden = true;
            }

            renderHud();

            return;
        }

        if (createBtn)
        {
            createBtn.hidden = true;
        }

        if (leaveBtn)
        {
            leaveBtn.hidden = false;
            leaveBtn.textContent = partyState.is_leader ? t('party.disband') : t('party.leave');
        }

        if (settingsEl)
        {
            settingsEl.hidden = false;
            settingsEl.classList.toggle('is-readonly', !partyState.is_leader);
        }

        if (inactivityVoteToggle)
        {
            inactivityVoteToggle.checked = !!partyState.allow_inactivity_vote;
            inactivityVoteToggle.disabled = !partyState.is_leader;
        }

        if (statusEl)
        {
            statusEl.textContent = t('party.roster_status', {
                count: partyState.member_count,
                max: partyState.max_members
            });
        }

        (partyState.members || []).forEach(function (member)
        {
            var row = document.createElement('div');
            row.className = 'party-member-row';

            var name = document.createElement('span');
            name.className = 'party-member-name';
            name.textContent = member.display_name + (member.is_leader ? ' (' + t('party.leader') + ')' : '');
            row.appendChild(name);

            var online = document.createElement('span');
            online.className = 'party-member-online' + (member.flg_online ? ' is-online' : '');
            online.textContent = member.flg_online ? t('party.online') : t('party.offline');
            row.appendChild(online);

            if (partyState.is_leader && !member.is_leader && parseInt(member.id_user_ig, 10) !== parseInt(playerRef.id_user_ig, 10))
            {
                var actions = document.createElement('span');
                actions.className = 'party-member-actions';

                var promoteBtn = document.createElement('button');
                promoteBtn.type = 'button';
                promoteBtn.className = 'party-member-action-btn';
                promoteBtn.textContent = t('party.promote');
                promoteBtn.addEventListener('click', function ()
                {
                    onPromoteMember(member.id_user_ig);
                });
                actions.appendChild(promoteBtn);

                var kickBtn = document.createElement('button');
                kickBtn.type = 'button';
                kickBtn.className = 'party-member-action-btn';
                kickBtn.textContent = t('party.kick');
                kickBtn.addEventListener('click', function ()
                {
                    onKickMember(member.id_user_ig);
                });
                actions.appendChild(kickBtn);

                row.appendChild(actions);
            }

            memberListEl.appendChild(row);
        });

        renderHud();
    }

    function poll()
    {
        if (!playerRef || busy)
        {
            return;
        }

        AnimasterApi.pollParty(playerRef).then(function (result)
        {
            var prevParty = partyState;
            var wasLeaderWithOthers = !!(prevParty && prevParty.is_leader && (prevParty.member_count || 0) > 1);

            partyState = result.party || null;
            outgoingInvite = result.outgoing_invite || null;

            if (wasLeaderWithOthers && !partyState && typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('party.disbanded'), 'party', '');
            }

            if (!inviteLocked && result.incoming_invites && result.incoming_invites.length)
            {
                var invite = result.incoming_invites[0];

                if (!dismissedInviteIds[invite.id_party_invite])
                {
                    showInviteOverlay(invite);
                }
            }

            if (isPanelOpen())
            {
                renderPanel();
            }
            else
            {
                renderHud();
            }

            if (result.party_pve_battle)
            {
                maybeJoinPartyBattle(result.party_pve_battle);
            }
        }).catch(function (err)
        {
            console.warn('[AnimasterParty] poll failed:', err && err.message ? err.message : err);
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        open: open,
        close: close,
        toggle: toggle,
        isPanelOpen: isPanelOpen,
        isInParty: isInParty,
        isLeader: isLeader,
        setOnPartyBattleJoin: setOnPartyBattleJoin,
        getPartyState: getPartyState,
        requestToPlayer: requestToPlayer,
        poll: poll,
        isPlayerFarFromParty: isPlayerFarFromParty,
        tickDistanceIndicators: tickDistanceIndicators,
        isFarFromAnyPartyMember: isFarFromAnyPartyMember,
        getFarPartyBearings: getFarPartyBearings
    };
})();
