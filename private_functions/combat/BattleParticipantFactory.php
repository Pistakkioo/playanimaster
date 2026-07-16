<?php

/**
 * Builds battle_participants insert rows from animal/wild stat snapshots.
 * Snapshot shape matches animaster_pvp_fetch_animal_snapshot_buffed() /
 * animaster_party_pve_fetch_wild_snapshot(): id_animal, id_user_ig, lvl,
 * current_hp, max_hp, experience, nickname, id_element, id_species, species,
 * atk, def, matk, mdef, acc, eva, cr, spd.
 */
class BattleParticipantFactory
{
    /**
     * @param array<string, mixed> $snap
     */
    public static function playerAnimal(array $snap, $side, $id_user_ig, $team_position = null)
    {
        return [
            'side' => (string) $side,
            'participant_kind' => 'player_animal',
            'id_user_ig' => (int) $id_user_ig,
            'id_animal' => (int) $snap['id_animal'],
            'id_wild_animal' => null,
            'id_species' => (int) $snap['id_species'],
            'id_element' => (int) $snap['id_element'],
            'entity_type' => 'animal',
            'id_entity' => (int) $snap['id_animal'],
            'team_position' => $team_position,
            'slot_label' => 'active',
            'flg_active' => 'S',
            'flg_fainted' => (int) $snap['current_hp'] <= 0 ? 'S' : 'N',
            'current_hp' => (int) $snap['current_hp'],
            'max_hp' => (int) $snap['max_hp'],
            'atk' => (int) $snap['atk'],
            'def' => (int) $snap['def'],
            'matk' => (int) $snap['matk'],
            'mdef' => (int) $snap['mdef'],
            'acc' => (int) $snap['acc'],
            'eva' => (int) $snap['eva'],
            'cr' => (int) $snap['cr'],
            'spd' => (int) $snap['spd'],
            'lvl' => (int) $snap['lvl'],
            'nickname' => (string) ($snap['nickname'] ?: $snap['species']),
            'species_name' => (string) $snap['species'],
            'experience' => (int) ($snap['experience'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $snap Wild snapshot; snap['id_animal'] holds id_wild_animal.
     */
    public static function wild(array $snap, $side, $team_position = null)
    {
        $id_wild_animal = (int) $snap['id_animal'];

        return [
            'side' => (string) $side,
            'participant_kind' => 'wild',
            'id_user_ig' => null,
            'id_animal' => null,
            'id_wild_animal' => $id_wild_animal,
            'id_species' => (int) $snap['id_species'],
            'id_element' => (int) $snap['id_element'],
            'entity_type' => 'wild',
            'id_entity' => $id_wild_animal,
            'team_position' => $team_position,
            'slot_label' => 'active',
            'flg_active' => 'S',
            'flg_fainted' => (int) $snap['current_hp'] <= 0 ? 'S' : 'N',
            'current_hp' => (int) $snap['current_hp'],
            'max_hp' => (int) $snap['max_hp'],
            'atk' => (int) $snap['atk'],
            'def' => (int) $snap['def'],
            'matk' => (int) $snap['matk'],
            'mdef' => (int) $snap['mdef'],
            'acc' => (int) $snap['acc'],
            'eva' => (int) $snap['eva'],
            'cr' => (int) $snap['cr'],
            'spd' => (int) $snap['spd'],
            'lvl' => (int) $snap['lvl'],
            'nickname' => (string) ($snap['nickname'] ?: $snap['species']),
            'species_name' => (string) $snap['species'],
            'experience' => 0,
        ];
    }
}
