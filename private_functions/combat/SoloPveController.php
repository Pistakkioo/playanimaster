<?php

require_once __DIR__ . '/../solo_pve.php';

/**
 * Thin adapter kept for the existing open_actions entry point
 * (solo_pve_get_battle_info.php). Turn orchestration lives in solo_pve.php
 * on the unified combat schema (docs/modules/005c_full_combat_unification.md).
 */
class SoloPveController
{
    /**
     * @param array<string, mixed> $post Raw POST (id_user_ig, id_battle, turn, type, id, lang, …)
     * @return array{stato: string, msg: string, response: string}
     */
    public static function handleRequest($conn, array $post)
    {
        return animaster_solo_pve_handle_request($conn, $post);
    }
}
