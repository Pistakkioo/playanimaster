<?php

require_once __DIR__ . '/CombatantSnapshot.php';

/**
 * Shared round numbering and planning/resolution lifecycle.
 * Mode-specific DB tables remain in each battle module for now.
 */
class CombatSession{
    const TYPE_SOLO = 'solo_pve';
    const TYPE_PVP = 'pvp';
    const TYPE_PARTY = 'party_pve';
    const TYPE_PARTY_VS_PARTY = 'party_vs_party';

    /**
     * Party PvE: last resolved round number stored in current_turn (0 at start).
     * Planning round = current_turn + 1.
     */
    public static function planningRound($lastResolvedRound)
    {
        return (int) $lastResolvedRound + 1;
    }

    /**
     * @param array<string, mixed> $battleRow Must include current_turn
     */
    public static function planningRoundFromBattle(array $battleRow)
    {
        return self::planningRound((int) ($battleRow['current_turn'] ?? 0));
    }

    /**
     * Whether the client turn param matches the open party planning round.
     */
    public static function isPlanningRoundInSync($clientTurn, $lastResolvedRound)
    {
        return (int) $clientTurn === self::planningRound($lastResolvedRound);
    }

    /**
     * PvP: current_turn is the open planning turn (starts at 1).
     *
     * @param array<string, mixed> $battleRow
     */
    public static function pvpPlanningTurnFromBattle(array $battleRow)
    {
        return (int) ($battleRow['current_turn'] ?? 1);
    }

    public static function isPvpTurnInSync($clientTurn, $currentTurn)
    {
        return (int) $clientTurn === (int) $currentTurn;
    }

    /**
     * Party PvE / party-vs-party: after a planning round resolves, clear its
     * choices and mark the round as resolved (current_round = resolved round).
     *
     * @param callable $clearChoices fn(int $resolvedRound): void
     */
    public static function completePartyConfirmRound($conn, $idBattle, $resolvedRound, $battleType, callable $clearChoices)
    {
        $idBattle = (int) $idBattle;
        $resolvedRound = (int) $resolvedRound;

        self::tickRoundBuffs($conn, (string) $battleType, $idBattle);
        $clearChoices($resolvedRound);

        $stmt = $conn->prepare('
            UPDATE battles
            SET current_round = :round,
                dt_round_started = NOW(),
                dt_m = NOW()
            WHERE id_battle = :id_battle
        ');
        $stmt->execute([
            ':round' => $resolvedRound,
            ':id_battle' => $idBattle,
        ]);
    }

    /**
     * @param callable $clearChoices fn(int $resolvedRound): void
     */
    public static function completePartyPveRound($conn, $idBattle, $resolvedRound, callable $clearChoices)
    {
        self::completePartyConfirmRound($conn, $idBattle, $resolvedRound, self::TYPE_PARTY, $clearChoices);
    }

    /**
     * PvP: after both choices resolve for a turn, clear staging rows and advance
     * to the next planning turn when the battle is still open.
     *
     * @param callable $clearChoices fn(int $resolvedTurn): void
     * @return array{current_turn: int, awaiting_user_ig: null}
     */
    public static function completePvpPlanningTurn(
        $conn,
        $idBattle,
        $resolvedTurn,
        $battleStillOpen,
        callable $clearChoices
    )
    {
        $idBattle = (int) $idBattle;
        $resolvedTurn = (int) $resolvedTurn;

        self::tickRoundBuffs($conn, self::TYPE_PVP, $idBattle);
        $clearChoices($resolvedTurn);

        if ($battleStillOpen)
        {
            $nextTurn = $resolvedTurn + 1;

            $stmt = $conn->prepare('
                UPDATE battles
                SET current_round = :turn,
                    dt_round_started = NOW(),
                    dt_m = NOW()
                WHERE id_battle = :id_battle
            ');
            $stmt->execute([
                ':turn' => $nextTurn,
                ':id_battle' => $idBattle,
            ]);

            return [
                'current_turn' => $nextTurn,
                'awaiting_user_ig' => null,
            ];
        }

        $stmt = $conn->prepare('
            UPDATE battles
            SET dt_m = NOW()
            WHERE id_battle = :id_battle
        ');
        $stmt->execute([
            ':id_battle' => $idBattle,
        ]);

        return [
            'current_turn' => $resolvedTurn,
            'awaiting_user_ig' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $participants
     * @return array<int, array<string, mixed>>
     */
    public static function combatantsFromPartyParticipants(array $participants)
    {
        $combatants = [];

        foreach ($participants as $participant)
        {
            $combatants[] = CombatantSnapshot::fromPartyParticipant($participant);
        }

        return $combatants;
    }

    /**
     * Latest player + wild from solo move rows (last row wins).
     *
     * @param array<int, array<string, mixed>> $moveRows
     * @return array<int, array<string, mixed>>
     */
    public static function combatantsFromSoloMoves(array $moveRows)
    {
        if (!$moveRows)
        {
            return [];
        }

        $last = $moveRows[count($moveRows) - 1];

        return [
            CombatantSnapshot::fromSoloMoveSide($last, 'player'),
            CombatantSnapshot::fromSoloMoveSide($last, 'wild'),
        ];
    }

    /**
     * @param array<string, mixed> $state animaster_pvp_state_from_move shape
     * @param array<string, mixed> $battleRow Optional battles_pvp row for owner ids
     * @return array<int, array<string, mixed>>
     */
    public static function combatantsFromPvpState(array $state, array $battleRow = [])
    {
        return [
            self::withPvpOwnerId(
                CombatantSnapshot::fromPvpStateSide($state, 'p_a'),
                (int) ($battleRow['id_user_ig_a'] ?? 0)
            ),
            self::withPvpOwnerId(
                CombatantSnapshot::fromPvpStateSide($state, 'w_a'),
                (int) ($battleRow['id_user_ig_b'] ?? 0)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private static function withPvpOwnerId(array $snapshot, $id_user_ig)
    {
        $snapshot['id_user_ig'] = $id_user_ig > 0 ? $id_user_ig : null;

        return $snapshot;
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     * @return array<int, array<string, mixed>>
     */
    public static function combatantsToClient(array $snapshots, $conn = null, $battleType = '', $idBattle = 0, $lang = '')
    {
        $client = [];

        foreach ($snapshots as $snapshot)
        {
            $client[] = self::combatantToClient($snapshot, $conn, $battleType, $idBattle, $lang);
        }

        return $client;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function combatantToClient(array $snapshot, $conn = null, $battleType = '', $idBattle = 0, $lang = '')
    {
        if ($conn && $battleType !== '' && (int) $idBattle > 0)
        {
            if (!class_exists('BUFFS'))
            {
                require_once __DIR__ . '/../buffs.php';
            }

            $entityType = (string) ($snapshot['entity_type'] ?? 'animal');
            $entityId = (int) ($snapshot['entity_id'] ?? 0);
            $idUserIg = array_key_exists('id_user_ig', $snapshot) ? $snapshot['id_user_ig'] : null;
            $baseStats = CombatantSnapshot::statsFromSnapshot($snapshot);
            $activeBuffs = BUFFS::fetchCombatDisplay(
                $conn,
                $battleType,
                (int) $idBattle,
                $entityType,
                $entityId,
                $idUserIg,
                $lang
            );
            $statSheet = BUFFS::fetchCombatStatSheet(
                $conn,
                $battleType,
                (int) $idBattle,
                $entityType,
                $entityId,
                $idUserIg,
                $baseStats,
                $lang
            );

            return CombatantSnapshot::toClient($snapshot, $activeBuffs, $statSheet);
        }

        return CombatantSnapshot::toClient($snapshot);
    }

    /**
     * Decrement turn-based buff durations once per resolved round.
     */
    public static function tickRoundBuffs($conn, $battleType, $idBattle)
    {
        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/../buffs.php';
        }

        BUFFS::tickBattleTurnBuffs($conn, (string) $battleType, (int) $idBattle);
    }

    /**
     * Clear all battle-scoped turn buff rows when a fight ends.
     */
    public static function onBattleEnd($conn, $battleType, $idBattle)
    {
        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/../buffs.php';
        }

        BUFFS::clearBattleTurnBuffs($conn, (string) $battleType, (int) $idBattle);
    }

    /**
     * Merge canonical combatants[] into an existing mode meta envelope.
     *
     * @param array<string, mixed> $modeMeta
     * @param array<int, array<string, mixed>> $snapshots
     * @param array<string, mixed> $options battle_type, id_battle, lang
     * @return array<string, mixed>
     */
    public static function attachCombatants(array $modeMeta, array $snapshots, $conn = null, array $options = [])
    {
        $battleType = (string) ($options['battle_type'] ?? $modeMeta['battle_type'] ?? '');
        $idBattle = (int) ($options['id_battle'] ?? 0);
        $lang = (string) ($options['lang'] ?? '');

        $modeMeta['combatants'] = self::combatantsToClient($snapshots, $conn, $battleType, $idBattle, $lang);

        return $modeMeta;
    }
}
