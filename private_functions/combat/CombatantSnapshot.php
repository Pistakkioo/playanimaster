<?php

/**
 * Canonical in-battle combatant snapshot (base stats at rest in session).
 * Effective stats for MoveResolver are computed on demand in 005b.
 */
class CombatantSnapshot
{
    /**
     * @param array<string, mixed> $partyRow battle_participants row (side 'A' = party, 'B' = wild)
     * @return array<string, mixed>
     */
    public static function fromPartyParticipant(array $partyRow)
    {
        $side = (string) ($partyRow['side'] ?? 'A');
        $isWild = $side === 'B' || $side === 'wild';
        $fainted = trim((string) ($partyRow['flg_fainted'] ?? 'N')) === 'S'
            || (int) ($partyRow['current_hp'] ?? 0) <= 0;

        return self::normalize([
            'battle_role' => $isWild ? 'wild' : 'ally',
            'entity_type' => $isWild ? 'wild' : 'animal',
            'entity_id' => (int) ($partyRow['id_entity'] ?? $partyRow['id_animal'] ?? 0),
            'id_animal' => (int) ($partyRow['id_animal'] ?? $partyRow['id_wild_animal'] ?? $partyRow['id_entity'] ?? 0),
            'id_user_ig' => $partyRow['id_user_ig'] !== null ? (int) $partyRow['id_user_ig'] : null,
            'id_species' => (int) ($partyRow['id_species'] ?? 0),
            'id_element' => (int) ($partyRow['id_element'] ?? 0),
            'lvl' => (int) ($partyRow['lvl'] ?? 0),
            'nickname' => (string) ($partyRow['nickname'] ?? ''),
            'species_name' => (string) ($partyRow['species_name'] ?? ''),
            'current_hp' => (int) ($partyRow['current_hp'] ?? 0),
            'max_hp' => (int) ($partyRow['max_hp'] ?? 0),
            'atk' => (float) ($partyRow['atk'] ?? 0),
            'def' => (float) ($partyRow['def'] ?? 0),
            'matk' => (float) ($partyRow['matk'] ?? 0),
            'mdef' => (float) ($partyRow['mdef'] ?? 0),
            'acc' => (int) ($partyRow['acc'] ?? 0),
            'eva' => (int) ($partyRow['eva'] ?? 0),
            'cr' => (int) ($partyRow['cr'] ?? 0),
            'spd' => (float) ($partyRow['spd'] ?? 0),
            'fainted' => $fainted,
        ]);
    }

    /**
     * @param array<string, mixed> $move battles_*_moves row with p_a_* / w_a_* columns
     * @return array<string, mixed>
     */
    public static function fromSoloMoveSide(array $move, $side)
    {
        $prefix = ($side === 'wild') ? 'w_a' : 'p_a';
        $hp = (int) ($move[$prefix . '_res_hp'] ?? 0);
        $role = ($side === 'wild') ? 'wild' : 'player';

        return self::normalize([
            'battle_role' => $role,
            'entity_type' => ($side === 'wild') ? 'wild' : 'animal',
            'entity_id' => (int) ($move[$prefix . '_id'] ?? 0),
            'id_animal' => (int) ($move[$prefix . '_id'] ?? 0),
            'id_user_ig' => null,
            'id_species' => (int) ($move[$prefix . '_id_species'] ?? 0),
            'id_element' => (int) ($move[$prefix . '_id_element'] ?? 0),
            'lvl' => (int) ($move[$prefix . '_lvl'] ?? 0),
            'nickname' => (string) ($move[$prefix . '_nickname'] ?? $move[$prefix . '_species'] ?? ''),
            'species_name' => (string) ($move[$prefix . '_species'] ?? ''),
            'current_hp' => $hp,
            'max_hp' => (int) ($move[$prefix . '_res_max_hp'] ?? 0),
            'atk' => (float) ($move[$prefix . '_res_atk'] ?? 0),
            'def' => (float) ($move[$prefix . '_res_def'] ?? 0),
            'matk' => (float) ($move[$prefix . '_res_matk'] ?? 0),
            'mdef' => (float) ($move[$prefix . '_res_mdef'] ?? 0),
            'acc' => (int) ($move[$prefix . '_res_acc'] ?? 0),
            'eva' => (int) ($move[$prefix . '_res_eva'] ?? 0),
            'cr' => (int) ($move[$prefix . '_res_cr'] ?? 0),
            'spd' => (float) ($move[$prefix . '_res_spd'] ?? 0),
            'fainted' => $hp <= 0,
        ]);
    }

    /**
     * @param array<string, mixed> $state animaster_pvp_state_from_move shape
     * @return array<string, mixed>
     */
    public static function fromPvpStateSide(array $state, $side)
    {
        $prefix = ($side === 'w_a') ? 'w_a' : 'p_a';
        $hp = (int) ($state[$prefix . '_res_hp'] ?? 0);
        $role = ($side === 'w_a') ? 'opponent' : 'player';

        return self::normalize([
            'battle_role' => $role,
            'entity_type' => 'animal',
            'entity_id' => (int) ($state[$prefix . '_id'] ?? 0),
            'id_animal' => (int) ($state[$prefix . '_id'] ?? 0),
            'id_user_ig' => null,
            'id_species' => (int) ($state[$prefix . '_id_species'] ?? 0),
            'id_element' => (int) ($state[$prefix . '_id_element'] ?? 0),
            'lvl' => (int) ($state[$prefix . '_lvl'] ?? 0),
            'nickname' => (string) ($state[$prefix . '_nickname'] ?? $state[$prefix . '_species'] ?? ''),
            'species_name' => (string) ($state[$prefix . '_species'] ?? ''),
            'current_hp' => $hp,
            'max_hp' => (int) ($state[$prefix . '_res_max_hp'] ?? 0),
            'atk' => (float) ($state[$prefix . '_res_atk'] ?? 0),
            'def' => (float) ($state[$prefix . '_res_def'] ?? 0),
            'matk' => (float) ($state[$prefix . '_res_matk'] ?? 0),
            'mdef' => (float) ($state[$prefix . '_res_mdef'] ?? 0),
            'acc' => (int) ($state[$prefix . '_res_acc'] ?? 0),
            'eva' => (int) ($state[$prefix . '_res_eva'] ?? 0),
            'cr' => (int) ($state[$prefix . '_res_cr'] ?? 0),
            'spd' => (float) ($state[$prefix . '_res_spd'] ?? 0),
            'fainted' => $hp <= 0,
        ]);
    }

    /**
     * MoveResolver fighter array.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function toFighter(array $snapshot)
    {
        return [
            'lvl' => (int) $snapshot['lvl'],
            'acc' => (int) $snapshot['acc'],
            'cr' => (int) $snapshot['cr'],
            'atk' => (float) $snapshot['atk'],
            'def' => (float) $snapshot['def'],
            'matk' => (float) $snapshot['matk'],
            'mdef' => (float) $snapshot['mdef'],
            'eva' => (int) $snapshot['eva'],
            'spd' => (int) $snapshot['spd'],
            'current_hp' => (int) $snapshot['current_hp'],
            'max_hp' => (int) $snapshot['max_hp'],
            'id_element' => (int) $snapshot['id_element'],
            'nickname' => (string) $snapshot['nickname'],
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, int|float>
     */
    public static function statsFromSnapshot(array $snapshot)
    {
        return [
            'hp' => (int) ($snapshot['current_hp'] ?? 0),
            'max_hp' => (int) ($snapshot['max_hp'] ?? 0),
            'atk' => (float) ($snapshot['atk'] ?? 0),
            'def' => (float) ($snapshot['def'] ?? 0),
            'matk' => (float) ($snapshot['matk'] ?? 0),
            'mdef' => (float) ($snapshot['mdef'] ?? 0),
            'acc' => (int) ($snapshot['acc'] ?? 0),
            'eva' => (int) ($snapshot['eva'] ?? 0),
            'cr' => (int) ($snapshot['cr'] ?? 0),
            'spd' => (float) ($snapshot['spd'] ?? 0),
        ];
    }

    /**
     * Client / API combatant block (005b stat panel + buff strip).
     *
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>>|null $activeBuffs
     * @param array<int, array<string, mixed>>|null $combatStatSheet
     * @return array<string, mixed>
     */
    public static function toClient(array $snapshot, array $activeBuffs = null, array $combatStatSheet = null)
    {
        $activeBuffs = $activeBuffs ?? [];
        $combatStatSheet = $combatStatSheet ?? self::buildStatSheet($snapshot, $activeBuffs);
        $effectiveStats = self::effectiveStatsFromSheet($combatStatSheet);

        return [
            'battle_role' => (string) $snapshot['battle_role'],
            'entity_type' => (string) $snapshot['entity_type'],
            'entity_id' => (int) $snapshot['entity_id'],
            'id_animal' => (int) $snapshot['id_animal'],
            'id_user_ig' => $snapshot['id_user_ig'],
            'id_species' => (int) $snapshot['id_species'],
            'id_element' => (int) $snapshot['id_element'],
            'nickname' => (string) $snapshot['nickname'],
            'species_name' => (string) $snapshot['species_name'],
            'lvl' => (int) $snapshot['lvl'],
            'hp' => (int) $snapshot['current_hp'],
            'max_hp' => (int) $snapshot['max_hp'],
            'stats' => [
                'atk' => (float) ($effectiveStats['atk'] ?? $snapshot['atk']),
                'def' => (float) ($effectiveStats['def'] ?? $snapshot['def']),
                'matk' => (float) ($effectiveStats['matk'] ?? $snapshot['matk']),
                'mdef' => (float) ($effectiveStats['mdef'] ?? $snapshot['mdef']),
                'acc' => (int) ($effectiveStats['acc'] ?? $snapshot['acc']),
                'eva' => (int) ($effectiveStats['eva'] ?? $snapshot['eva']),
                'cr' => (int) ($effectiveStats['cr'] ?? $snapshot['cr']),
                'spd' => (float) ($effectiveStats['spd'] ?? $snapshot['spd']),
            ],
            'fainted' => (bool) $snapshot['fainted'],
            'active_combat_buffs' => $activeBuffs,
            'combat_stat_sheet' => $combatStatSheet,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $combatStatSheet
     * @return array<string, int|float>
     */
    private static function effectiveStatsFromSheet(array $combatStatSheet)
    {
        $stats = [];

        foreach ($combatStatSheet as $row)
        {
            $key = (string) ($row['stat_key'] ?? '');

            if ($key !== '')
            {
                $stats[$key] = $row['effective'] ?? $row['base'] ?? 0;
            }
        }

        return $stats;
    }

    /**
     * Per-stat rows for the combat stat inspector (005b).
     *
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $activeBuffs
     * @return array<int, array<string, mixed>>
     */
    public static function buildStatSheet(array $snapshot, array $activeBuffs = [])
    {
        $values = [
            'hp' => (int) ($snapshot['current_hp'] ?? 0),
            'max_hp' => (int) ($snapshot['max_hp'] ?? 0),
            'atk' => (float) ($snapshot['atk'] ?? 0),
            'def' => (float) ($snapshot['def'] ?? 0),
            'matk' => (float) ($snapshot['matk'] ?? 0),
            'mdef' => (float) ($snapshot['mdef'] ?? 0),
            'acc' => (int) ($snapshot['acc'] ?? 0),
            'eva' => (int) ($snapshot['eva'] ?? 0),
            'cr' => (int) ($snapshot['cr'] ?? 0),
            'spd' => (float) ($snapshot['spd'] ?? 0),
        ];

        $integerKeys = ['hp', 'max_hp', 'acc', 'eva', 'cr'];
        $sheet = [];

        foreach (['hp', 'max_hp', 'atk', 'def', 'matk', 'mdef', 'acc', 'eva', 'cr', 'spd'] as $statKey)
        {
            $base = $values[$statKey];
            $effective = $base;
            $buffs = self::filterBuffsForStat($activeBuffs, $statKey);
            $castBase = in_array($statKey, $integerKeys, true) ? (int) $base : (float) $base;
            $castEffective = in_array($statKey, $integerKeys, true) ? (int) $effective : (float) $effective;

            $sheet[] = [
                'stat_key' => $statKey,
                'base' => $castBase,
                'effective' => $castEffective,
                'is_modified' => !empty($buffs) || $castBase !== $castEffective,
                'buffs' => $buffs,
            ];
        }

        return $sheet;
    }

    /**
     * @param array<int, array<string, mixed>> $activeBuffs
     * @return array<int, array<string, mixed>>
     */
    private static function filterBuffsForStat(array $activeBuffs, $statKey)
    {
        $matched = [];

        foreach ($activeBuffs as $buff)
        {
            if (!is_array($buff))
            {
                continue;
            }

            $buffStatKey = (string) ($buff['stat_key'] ?? '');

            if ($buffStatKey === (string) $statKey)
            {
                $matched[] = $buff;
                continue;
            }

            if (class_exists('BUFFS') && BUFFS::buffAffectsStat($buffStatKey, $statKey))
            {
                $matched[] = $buff;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalize(array $fields)
    {
        $fields['id_user_ig'] = array_key_exists('id_user_ig', $fields) && $fields['id_user_ig'] !== null
            ? (int) $fields['id_user_ig']
            : null;

        return $fields;
    }
}
