<?php

/**
 * Wild combat AI: random ability from species unlock pool, random target selection.
 * Used by solo PvE, party PvE, and future dungeon/raid wild slots.
 */
class AiWild
{
    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param callable|null $isEligible fn(array $candidate): bool
     * @return array<string, mixed>|null
     */
    public static function pickRandomTarget(array $candidates, callable $isEligible = null)
    {
        $eligible = [];

        foreach ($candidates as $candidate)
        {
            if ($isEligible !== null && !$isEligible($candidate))
            {
                continue;
            }

            $eligible[] = $candidate;
        }

        if (!$eligible)
        {
            return null;
        }

        return $eligible[array_rand($eligible)];
    }

    /**
     * Party PvE: random alive, active party member.
     *
     * @param array<int, array<string, mixed>> $party_participants
     * @return array<string, mixed>|null
     */
    public static function pickRandomPartyTarget(array $party_participants)
    {
        return self::pickRandomTarget($party_participants, function (array $p)
        {
            if (isset($p['side']) && (string) $p['side'] !== 'party')
            {
                return false;
            }

            if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
            {
                return false;
            }

            if (trim((string) ($p['flg_fainted'] ?? 'N')) === 'S')
            {
                return false;
            }

            return (int) ($p['current_hp'] ?? 0) > 0;
        });
    }

    /**
     * Abilities unlocked for a species at the given level.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetchUnlockedAbilities($conn, $id_species, $lvl, $lang_suffix = '')
    {
        $lang_suffix = self::sanitizeLangSuffix($lang_suffix);
        $ability_col = 'A.ability' . $lang_suffix;

        $sql = '
            SELECT A.id_ability, A.accuracy, A.power, A.m_power, A.id_element,
                   A.effect, A.effect_chance, ' . $ability_col . ' AS ability
            FROM abilities A
            INNER JOIN species_abilities LA ON LA.id_ability = A.id_ability
            WHERE LA.id_species = :id_species
              AND LA.unlock_lvl <= :lvl
        ';

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_species' => (int) $id_species,
            ':lvl' => (int) $lvl,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * One random unlocked ability for wild AI, or null when the pool is empty.
     *
     * @return array<string, mixed>|null
     */
    public static function pickRandomAbility($conn, $id_species, $lvl, $lang_suffix = '')
    {
        $rows = self::fetchUnlockedAbilities($conn, $id_species, $lvl, $lang_suffix);

        if (!$rows)
        {
            return null;
        }

        return $rows[array_rand($rows)];
    }

    private static function sanitizeLangSuffix($lang_suffix)
    {
        return preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);
    }
}
