<?php

/**
 * Resolution-phase queue: build speed-sorted execution slots.
 * Planning (staging/confirm) lives in Permissions + mode controllers.
 */
class TurnQueue
{
    const SLOT_PARTY = 'party';
    const SLOT_WILD = 'wild';
    const SLOT_PVP = 'pvp';
    const SLOT_FIGHTER = 'fighter';

    /**
     * Sort execution slots by speed descending; ties by original insertion index.
     *
     * Each slot must have 'spd' (float|int) and 'idx' (int).
     *
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, array<string, mixed>>
     */
    public static function sortBySpeedDesc(array $slots)
    {
        usort($slots, function ($a, $b)
        {
            $spd_a = (float) ($a['spd'] ?? 0);
            $spd_b = (float) ($b['spd'] ?? 0);

            if ($spd_a !== $spd_b)
            {
                return $spd_b <=> $spd_a;
            }

            return (int) ($a['idx'] ?? 0) <=> (int) ($b['idx'] ?? 0);
        });

        return $slots;
    }

    /**
     * Party PvE execution queue: one slot per confirmed party choice plus one
     * wild slot per currently-alive party member (wild scales with party size).
     *
     * @param array<int, array<string, mixed>> $confirmedByUser id_user_ig => choice row
     * @param array<int, array<string, mixed>> $partyByUser id_user_ig => participant row
     * @param array<string, mixed> $wild Wild participant snapshot (needs spd)
     * @param int $alivePartyCount
     * @return array<int, array<string, mixed>>
     */
    public static function buildPartyPveExecutionSlots(
        array $confirmedByUser,
        array $partyByUser,
        array $wild,
        $alivePartyCount
    )
    {
        $slots = [];
        $idx = 0;
        $wild_spd = (float) ($wild['spd'] ?? 0);

        foreach ($confirmedByUser as $id_user_ig => $choice)
        {
            if (!isset($partyByUser[$id_user_ig]))
            {
                continue;
            }

            $slots[] = [
                'kind' => self::SLOT_PARTY,
                'id_user_ig' => (int) $id_user_ig,
                'spd' => (float) $partyByUser[$id_user_ig]['spd'],
                'choice' => $choice,
                'idx' => $idx++
            ];
        }

        $wild_action_count = max(0, (int) $alivePartyCount);

        for ($i = 0; $i < $wild_action_count; $i++)
        {
            $slots[] = [
                'kind' => self::SLOT_WILD,
                'spd' => $wild_spd,
                'idx' => $idx++
            ];
        }

        return self::sortBySpeedDesc($slots);
    }

    /**
     * PvP: order the two actor user ids by active animal speed (faster first).
     *
     * @return array{0: int, 1: int}
     */
    public static function orderPvpActorUserIds($firstUserId, $secondUserId, $firstSpd, $secondSpd)
    {
        if ((int) $firstSpd >= (int) $secondSpd)
        {
            return [(int) $firstUserId, (int) $secondUserId];
        }

        return [(int) $secondUserId, (int) $firstUserId];
    }

    /**
     * Party vs party: one slot per confirmed fighter on either side (no wilds).
     *
     * @param array<int, array<string, mixed>> $confirmedByUser id_user_ig => choice
     * @param array<int, array<string, mixed>> $fightersByUser id_user_ig => participant
     * @return array<int, array<string, mixed>>
     */
    public static function buildPartyVsPartyExecutionSlots(array $confirmedByUser, array $fightersByUser)
    {
        $slots = [];
        $idx = 0;

        foreach ($confirmedByUser as $id_user_ig => $choice)
        {
            if (!isset($fightersByUser[$id_user_ig]))
            {
                continue;
            }

            $slots[] = [
                'kind' => self::SLOT_FIGHTER,
                'id_user_ig' => (int) $id_user_ig,
                'spd' => (float) $fightersByUser[$id_user_ig]['spd'],
                'choice' => $choice,
                'idx' => $idx++
            ];
        }

        return self::sortBySpeedDesc($slots);
    }

    /**
     * Solo PvE: two slots per turn (player action + one wild action).
     * Non-ability player actions (item/switch/flee) always go first; abilities use speed.
     *
     * @return array<int, string> 'player' | 'wild'
     */
    public static function orderSoloTurnSlots($playerSpd, $wildSpd, $playerActionType)
    {
        if ((string) $playerActionType !== 'ability')
        {
            return ['player', 'wild'];
        }

        if ((float) $playerSpd > (float) $wildSpd)
        {
            return ['player', 'wild'];
        }

        return ['wild', 'player'];
    }
}
