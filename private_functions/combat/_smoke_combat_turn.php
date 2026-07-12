<?php

require __DIR__ . '/CombatSession.php';
require __DIR__ . '/Permissions.php';
require __DIR__ . '/TurnQueue.php';
require __DIR__ . '/AiWild.php';

function smoke_assert($cond, $msg)
{
    if (!$cond)
    {
        fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
        exit(1);
    }

    echo 'OK: ' . $msg . PHP_EOL;
}

smoke_assert(CombatSession::planningRound(0) === 1, 'planning round after 0');
smoke_assert(CombatSession::planningRound(3) === 4, 'planning round after 3');
smoke_assert(CombatSession::isPlanningRoundInSync(4, 3), 'planning round in sync');
smoke_assert(!CombatSession::isPlanningRoundInSync(3, 3), 'resolved round not planning');

smoke_assert(CombatSession::pvpPlanningTurnFromBattle(['current_turn' => 2]) === 2, 'pvp planning turn from battle');
smoke_assert(CombatSession::isPvpTurnInSync(2, 2), 'pvp turn in sync');
smoke_assert(!CombatSession::isPvpTurnInSync(1, 2), 'pvp turn out of sync');

smoke_assert(
    Permissions::partyPveShouldResolveRound(2, 2, null),
    'party resolve when all confirmed'
);
smoke_assert(
    !Permissions::partyPveShouldResolveRound(1, 2, null),
    'party wait when not all confirmed'
);
smoke_assert(
    Permissions::partyPveShouldResolveRound(0, 2, ['action_type' => 'flee', 'flg_confirmed' => 'Y']),
    'party resolve on leader flee confirmed'
);
smoke_assert(
    Permissions::stagedChangeInvalidatesOthers(Permissions::MODE_PARTY, true, true),
    'party invalidates on changed staged choice'
);
smoke_assert(
    !Permissions::stagedChangeInvalidatesOthers(Permissions::MODE_PVP, true, true),
    'pvp does not use confirm invalidation'
);

$choices_flee = [['action_type' => 'action', 'action_id' => 4]];
smoke_assert(Permissions::pvpShouldResolveTurn(1, $choices_flee), 'pvp resolve on flee');
smoke_assert(!Permissions::pvpShouldResolveTurn(1, [['action_type' => 'ability', 'action_id' => 1]]), 'pvp wait for opponent');
smoke_assert(
    Permissions::pvpShouldResolveTurn(2, [['action_type' => 'ability'], ['action_type' => 'ability']]),
    'pvp resolve when both submitted'
);

$slots = TurnQueue::buildPartyPveExecutionSlots(
    [10 => ['action_type' => 'ability']],
    [10 => ['spd' => 50]],
    ['spd' => 80],
    2
);
smoke_assert(count($slots) === 3, 'party queue: 1 human + 2 wild slots');
smoke_assert($slots[0]['kind'] === TurnQueue::SLOT_WILD, 'faster wild slot executes first');
smoke_assert($slots[0]['spd'] === 80.0, 'wild spd preserved');

$sorted = TurnQueue::sortBySpeedDesc([
    ['spd' => 10, 'idx' => 1],
    ['spd' => 20, 'idx' => 0],
    ['spd' => 20, 'idx' => 2],
]);
smoke_assert($sorted[0]['idx'] === 0 && $sorted[1]['idx'] === 2, 'speed tie breaks by idx');

$ordered = TurnQueue::orderPvpActorUserIds(100, 200, 90, 50);
smoke_assert($ordered === [100, 200], 'pvp faster side a first');
$ordered = TurnQueue::orderPvpActorUserIds(100, 200, 40, 60);
smoke_assert($ordered === [200, 100], 'pvp faster side b first');

smoke_assert(TurnQueue::orderSoloTurnSlots(50, 30, 'ability') === ['player', 'wild'], 'solo ability faster player first');
smoke_assert(TurnQueue::orderSoloTurnSlots(20, 30, 'ability') === ['wild', 'player'], 'solo ability faster wild first');
smoke_assert(TurnQueue::orderSoloTurnSlots(20, 30, 'use_on') === ['player', 'wild'], 'solo item always player first');

$targets = [
    ['side' => 'party', 'flg_active' => 'S', 'flg_fainted' => 'N', 'current_hp' => 10, 'id_user_ig' => 1],
    ['side' => 'party', 'flg_active' => 'S', 'flg_fainted' => 'N', 'current_hp' => 0, 'id_user_ig' => 2],
];
$picked = AiWild::pickRandomPartyTarget($targets);
smoke_assert($picked !== null && (int) $picked['id_user_ig'] === 1, 'AiWild picks alive party member');
smoke_assert(AiWild::pickRandomPartyTarget([]) === null, 'AiWild returns null when no targets');

$soloMove = [
    'p_a_id' => 10, 'p_a_id_species' => 3, 'p_a_id_element' => 1, 'p_a_lvl' => 5,
    'p_a_nickname' => 'Rex', 'p_a_species' => 'Snake',
    'p_a_res_hp' => 40, 'p_a_res_max_hp' => 50, 'p_a_res_atk' => 12, 'p_a_res_def' => 8,
    'p_a_res_matk' => 6, 'p_a_res_mdef' => 7, 'p_a_res_acc' => 90, 'p_a_res_eva' => 5,
    'p_a_res_cr' => 10, 'p_a_res_spd' => 15,
    'w_a_id' => 99, 'w_a_id_species' => 3, 'w_a_id_element' => 4, 'w_a_lvl' => 4,
    'w_a_species' => 'Snake', 'w_a_res_hp' => 0, 'w_a_res_max_hp' => 45,
    'w_a_res_atk' => 10, 'w_a_res_def' => 7, 'w_a_res_matk' => 5, 'w_a_res_mdef' => 6,
    'w_a_res_acc' => 85, 'w_a_res_eva' => 4, 'w_a_res_cr' => 8, 'w_a_res_spd' => 12,
];
$soloCombatants = CombatSession::combatantsFromSoloMoves([$soloMove]);
smoke_assert(count($soloCombatants) === 2, 'solo snapshot builds player + wild');
smoke_assert($soloCombatants[1]['fainted'] === true, 'solo wild fainted when hp 0');
$client = CombatantSnapshot::toClient($soloCombatants[0]);
smoke_assert($client['stats']['atk'] == 12 && $client['hp'] === 40, 'client combatant stats');
smoke_assert(
    isset($client['combat_stat_sheet']) && count($client['combat_stat_sheet']) === 10,
    'client stat sheet rows'
);

echo PHP_EOL . 'Combat turn smoke: all passed' . PHP_EOL;
