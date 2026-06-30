<?php

require_once __DIR__ . '/requirement_range.php';

function dev_npc_fetch_zones(PDO $conn)
{
    $stmt = $conn->query('SELECT id_zone, scene_name FROM zones ORDER BY id_zone');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_requirements(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_requirement, requirement_type
        FROM requirements
        ORDER BY requirement_type, id_requirement
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int, string>
 */
function dev_npc_requirement_types()
{
    return [
        'user lvl',
        'number of animals',
        'item',
        'conversation finished',
        'conversation not finished',
        'player class'
    ];
}

function dev_npc_fetch_consequences(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_consequence, consequence_type
        FROM consequences
        ORDER BY consequence_type, id_consequence
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_item_types(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_item_type, item_type, nome
        FROM item_types
        ORDER BY id_item_type
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_player_classes(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_player_class, code, name, unlock_level, parent_id_player_class
        FROM player_classes
        ORDER BY unlock_level ASC, id_player_class ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_buff_definitions(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_buff_definition, buff_code, name, name_it, target_entity, stat_key, is_debuff
        FROM buff_definitions
        WHERE flg_active = \'S\'
        ORDER BY id_buff_definition ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * ref_table values used by requirements (labels for the dev form).
 *
 * @return array<int, array{value: string, label: string}>
 */
function dev_npc_requirement_ref_tables()
{
    return [
        ['value' => '', 'label' => '— none —'],
        ['value' => 'POTION', 'label' => 'POTION (item type)'],
        ['value' => 'PLAYER_CLASS', 'label' => 'PLAYER_CLASS (gameplay class)'],
        ['value' => 'CONVERSATION', 'label' => 'CONVERSATION'],
        ['value' => 'ZERO', 'label' => 'ZERO (no animals)'],
        ['value' => 'HAS_ANIMALS', 'label' => 'HAS_ANIMALS'],
        ['value' => 'LT_2', 'label' => 'LT_2 (fewer than 2 animals)'],
        ['value' => 'LT_3', 'label' => 'LT_3 (fewer than 3 animals)'],
        ['value' => 'NOT', 'label' => 'NOT (negate player class match)'],
    ];
}

/**
 * ref_table values for consequences (entity type, not instance label).
 *
 * @return array<int, array{value: string, label: string}>
 */
function dev_npc_consequence_ref_tables()
{
    return [
        ['value' => '', 'label' => '— none —'],
        ['value' => 'item_types', 'label' => 'item_types'],
        ['value' => 'POTION', 'label' => 'POTION (item type alias)'],
        ['value' => 'PLAYER_CLASS', 'label' => 'PLAYER_CLASS'],
        ['value' => 'buff_definitions', 'label' => 'buff_definitions'],
    ];
}

/**
 * @return array<int, array{value: string, label: string}>
 */
function dev_npc_consequence_types()
{
    return [
        ['value' => '[obtain item]', 'label' => '[obtain item]'],
        ['value' => 'receive_random_animal', 'label' => 'receive_random_animal'],
        ['value' => '[set player_class]', 'label' => '[set player_class]'],
        ['value' => 'grant_team_buff', 'label' => 'grant_team_buff'],
    ];
}

/**
 * @return array<string, mixed>
 */
function dev_npc_parse_attach_prefill(array $get)
{
    $attach = isset($get['attach']) ? strtolower(trim((string) $get['attach'])) : '';

    if ($attach !== 'req' && $attach !== 'cons')
    {
        $attach = '';
    }

    $target = isset($get['target']) ? strtolower(trim((string) $get['target'])) : '';

    return [
        'attach' => $attach,
        'target' => $target,
        'id_npc' => isset($get['id_npc']) ? (int) $get['id_npc'] : 0,
        'id_conversation' => isset($get['id_conversation']) ? (int) $get['id_conversation'] : 0,
        'id_dialog' => isset($get['id_dialog']) ? (int) $get['id_dialog'] : 0,
        'id_option' => isset($get['id_option']) ? (int) $get['id_option'] : 0,
        'id_quest' => isset($get['id_quest']) ? (int) $get['id_quest'] : 0,
    ];
}

function dev_npc_requirement_label(array $row)
{
    $parts = [
        '#' . (int) $row['id_requirement'],
        (string) $row['requirement_type'],
    ];

    if (!empty($row['ref_table']))
    {
        $parts[] = (string) $row['ref_table'];
    }

    if (!empty($row['ref_description']))
    {
        $parts[] = (string) $row['ref_description'];
    }
    elseif ((int) $row['id_ref'] > 0)
    {
        $parts[] = 'id=' . (int) $row['id_ref'];
    }

    if (!empty($row['descrizione']))
    {
        $parts[] = (string) $row['descrizione'];
    }

    if (array_key_exists('min', $row) || array_key_exists('max', $row))
    {
        $parts[] = '[' . requirement_range_label($row['min'] ?? 0, $row['max'] ?? null) . ']';
    }

    return implode(' — ', $parts);
}

/**
 * Catalog row only (no per-link min/max/description).
 */
function dev_npc_requirement_catalog_label(array $row)
{
    return '#' . (int) $row['id_requirement'] . ' · ' . (string) $row['requirement_type'];
}

/**
 * @param 'npc'|'conversation'|'quest' $kind
 * @return array{kind:string,link:array,id_npc:int,npc:array,id_conversation?:int,conversation?:array,id_quest?:int,quest?:array}|null
 */
function dev_npc_find_requirement_link(array $tree, $kind, $link_id)
{
    $link_id = (int) $link_id;

    foreach ($tree as $id_npc => $npc)
    {
        if ($kind === 'npc')
        {
            foreach ($npc['requirements'] as $row)
            {
                if ((int) $row['id_npc_requirement'] === $link_id)
                {
                    return [
                        'kind' => 'npc',
                        'link' => $row,
                        'id_npc' => (int) $id_npc,
                        'npc' => $npc
                    ];
                }
            }
        }

        foreach ($npc['quests'] as $id_quest => $quest)
        {
            if ($kind === 'quest')
            {
                foreach ($quest['requirements'] as $row)
                {
                    if ((int) $row['id_quest_requirement'] === $link_id)
                    {
                        return [
                            'kind' => 'quest',
                            'link' => $row,
                            'id_npc' => (int) $id_npc,
                            'npc' => $npc,
                            'id_quest' => (int) $id_quest,
                            'quest' => $quest
                        ];
                    }
                }
            }
        }

        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            if ($kind === 'conversation')
            {
                foreach ($conv['requirements'] as $row)
                {
                    if ((int) $row['id_conversation_requirement'] === $link_id)
                    {
                        return [
                            'kind' => 'conversation',
                            'link' => $row,
                            'id_npc' => (int) $id_npc,
                            'npc' => $npc,
                            'id_conversation' => (int) $id_conv,
                            'conversation' => $conv
                        ];
                    }
                }
            }
        }
    }

    return null;
}

function dev_npc_requirement_link_context_title(array $ctx, $preview_lang = 'en')
{
    if ($ctx['kind'] === 'npc')
    {
        return 'NPC #' . (int) $ctx['id_npc'] . ' ' . (string) $ctx['npc']['npc'];
    }

    if ($ctx['kind'] === 'conversation')
    {
        $title = dev_npc_localized_field($ctx['conversation'], 'title', $preview_lang);

        return 'Conversation #' . (int) $ctx['id_conversation'] . ' ' . $title;
    }

    if ($ctx['kind'] === 'quest')
    {
        return 'Quest #' . (int) $ctx['id_quest'] . ' ' . (string) $ctx['quest']['quest'];
    }

    return '';
}

/**
 * @param 'npc'|'conversation'|'quest' $link_kind
 */
function dev_npc_requirement_link_tree_item(array $req, $link_kind)
{
    $link_map = [
        'npc' => ['id_npc_requirement', 'npc_requirement'],
        'conversation' => ['id_conversation_requirement', 'conversation_requirement'],
        'quest' => ['id_quest_requirement', 'quest_requirement'],
    ];

    if (!isset($link_map[$link_kind]))
    {
        return '';
    }

    list($id_field, $edit_type) = $link_map[$link_kind];
    $link_id = (int) $req[$id_field];
    $catalog = (string) ($req['requirement_type'] ?? '');
    $ref_bits = [];

    if (!empty($req['ref_table']))
    {
        $ref_bits[] = (string) $req['ref_table'];
    }

    if (!empty($req['ref_description']))
    {
        $ref_bits[] = (string) $req['ref_description'];
    }
    elseif ((int) ($req['id_ref'] ?? 0) > 0)
    {
        $ref_bits[] = 'id=' . (int) $req['id_ref'];
    }

    if ($ref_bits)
    {
        $catalog .= ' · ' . implode(' · ', $ref_bits);
    }

    $range = requirement_range_label($req['min'] ?? 0, $req['max'] ?? null);
    $note = !empty($req['descrizione']) ? (string) $req['descrizione'] : '';
    $edit_url = dev_admin_url(['tab' => 'req', 'edit' => $edit_type, 'id' => $link_id]);
    $catalog_url = dev_admin_url(['tab' => 'req', 'edit' => 'requirement', 'id' => (int) $req['id_requirement']]);

    $html = '<li class="dev-req-item">';
    $html .= '<span class="badge badge-req">req</span>';
    $html .= '<span class="dev-req-catalog">' . dev_admin_h($catalog) . '</span>';
    $html .= '<span class="dev-req-range" title="min–max">' . dev_admin_h($range) . '</span>';

    if ($note !== '')
    {
        $html .= '<span class="dev-req-note">' . dev_admin_h($note) . '</span>';
    }

    $html .= '<span class="dev-req-actions">';
    $html .= '<a class="btn btn-outline-secondary btn-sm dev-btn-mini" href="' . dev_admin_h($edit_url) . '" title="Edit link (min/max, description)">✎</a>';
    $html .= '<a class="btn btn-outline-secondary btn-sm dev-btn-mini ms-1" href="' . dev_admin_h($catalog_url) . '" title="Edit catalog requirement">cat</a>';
    $html .= '</span>';
    $html .= '</li>';

    return $html;
}

function dev_npc_consequence_catalog_label(array $row)
{
    return '#' . (int) $row['id_consequence'] . ' · ' . (string) $row['consequence_type'];
}

function dev_npc_consequence_label(array $row)
{
    $parts = [
        '#' . (int) ($row['id_consequence'] ?? 0),
        (string) ($row['consequence_type'] ?? ''),
    ];

    if (!empty($row['ref_table']))
    {
        $parts[] = (string) $row['ref_table'];
    }

    if (!empty($row['ref_description']))
    {
        $parts[] = (string) $row['ref_description'];
    }
    elseif ((int) ($row['id_ref'] ?? 0) > 0)
    {
        $parts[] = 'id=' . (int) $row['id_ref'];
    }

    if (array_key_exists('num', $row))
    {
        $parts[] = '×' . (int) $row['num'];
    }

    return implode(' — ', $parts);
}

/**
 * @return array{kind:string,link:array,id_npc:int,npc:array,id_conversation:int,conversation:array,id_dialog:int,dialog:array}|null
 */
function dev_npc_find_consequence_link(array $tree, $link_id)
{
    $link_id = (int) $link_id;

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            foreach ($conv['dialogues'] as $id_dialog => $dlg)
            {
                foreach ($dlg['consequences'] ?? [] as $row)
                {
                    if ((int) $row['id_conversation_consequence'] === $link_id)
                    {
                        return [
                            'kind' => 'conversation_option',
                            'link' => $row,
                            'id_npc' => (int) $id_npc,
                            'npc' => $npc,
                            'id_conversation' => (int) $id_conv,
                            'conversation' => $conv,
                            'id_dialog' => (int) $id_dialog,
                            'dialog' => $dlg
                        ];
                    }
                }
            }
        }
    }

    return null;
}

function dev_npc_consequence_link_context_title(array $ctx, $preview_lang = 'en')
{
    $title = dev_npc_localized_field($ctx['conversation'], 'title', $preview_lang);

    return 'Conversation #' . (int) $ctx['id_conversation'] . ' ' . $title
        . ' · dialog #' . (int) $ctx['id_dialog']
        . ' · option #' . (int) $ctx['link']['id_option'];
}

function dev_npc_consequence_link_tree_item(array $cons)
{
    $link_id = (int) $cons['id_conversation_consequence'];
    $catalog = (string) ($cons['consequence_type'] ?? '');
    $ref_bits = [];

    if (!empty($cons['ref_table']))
    {
        $ref_bits[] = (string) $cons['ref_table'];
    }

    if (!empty($cons['ref_description']))
    {
        $ref_bits[] = (string) $cons['ref_description'];
    }
    elseif ((int) ($cons['id_ref'] ?? 0) > 0)
    {
        $ref_bits[] = 'id=' . (int) $cons['id_ref'];
    }

    if ($ref_bits)
    {
        $catalog .= ' · ' . implode(' · ', $ref_bits);
    }

    $num = (int) ($cons['num'] ?? 1);
    $edit_url = dev_admin_url(['tab' => 'cons', 'edit' => 'conversation_consequence', 'id' => $link_id]);
    $catalog_url = dev_admin_url(['tab' => 'cons', 'edit' => 'consequence', 'id' => (int) $cons['id_consequence']]);

    $html = '<li class="dev-req-item">';
    $html .= '<span class="badge badge-cons">cons</span>';
    $html .= '<span class="dev-req-catalog">' . dev_admin_h($catalog) . '</span>';
    $html .= '<span class="dev-req-range" title="num">×' . $num . '</span>';

    if (!empty($cons['params_json']))
    {
        $html .= '<span class="dev-req-note">' . dev_admin_h((string) $cons['params_json']) . '</span>';
    }

    $html .= '<span class="dev-req-actions">';
    $html .= '<a class="btn btn-outline-secondary btn-sm dev-btn-mini" href="' . dev_admin_h($edit_url) . '" title="Edit link (refs, num, params)">✎</a>';
    $html .= '<a class="btn btn-outline-secondary btn-sm dev-btn-mini ms-1" href="' . dev_admin_h($catalog_url) . '" title="Edit catalog consequence">cat</a>';
    $html .= '</span>';
    $html .= '</li>';

    return $html;
}

/**
 * @return array{id_ref:int,ref_table:?string,ref_description:?string,min:int,max:?int,descrizione:?string}
 */
function dev_npc_link_params_from_post(array $post)
{
    $ref_table = dev_npc_post_str($post, 'ref_table', 100);

    return [
        'id_ref' => dev_npc_post_int($post, 'id_ref'),
        'ref_table' => $ref_table !== '' ? $ref_table : null,
        'ref_description' => dev_npc_post_str($post, 'ref_description', 200) ?: null,
        'min' => dev_npc_post_int($post, 'min'),
        'max' => requirement_max_from_post($post),
        'descrizione' => dev_npc_post_str($post, 'descrizione', 100) ?: null
    ];
}

/**
 * Min/max fields for requirement link forms (attach + edit link).
 *
 * @param int|null $max NULL = no upper bound
 */
function dev_npc_requirement_link_range_fields($min_input_id, $max_input_id, $checkbox_id, $min, $max)
{
    $unbounded = requirement_max_is_unbounded($max);
    $min = (int) $min;
    $max_display = $unbounded ? '' : (int) $max;
    ?>
    <div class="row g-2 mb-2 dev-req-range-fields">
        <div class="col-6">
            <label class="form-label">min</label>
            <input class="form-control form-control-sm" name="min" type="number" value="<?php echo $min; ?>" id="<?php echo dev_admin_h($min_input_id); ?>">
        </div>
        <div class="col-6">
            <label class="form-label">max</label>
            <input class="form-control form-control-sm dev-req-max-input" name="max" type="number" value="<?php echo dev_admin_h((string) $max_display); ?>" id="<?php echo dev_admin_h($max_input_id); ?>"<?php echo $unbounded ? ' disabled' : ''; ?>>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input dev-req-max-unbounded" type="checkbox" name="max_unbounded" value="S" id="<?php echo dev_admin_h($checkbox_id); ?>"<?php echo $unbounded ? ' checked' : ''; ?>>
                <label class="form-check-label small" for="<?php echo dev_admin_h($checkbox_id); ?>">No maximum (∞)</label>
            </div>
        </div>
    </div>
    <?php
}

/**
 * ref_table / id_ref / ref_description on requirement link rows.
 *
 * @param array{
 *   prefix:string,
 *   row?:array,
 *   requirement_ref_tables:array,
 *   item_types:array,
 *   flat_conversations:array,
 *   player_classes:array,
 *   preview_lang:string
 * } $ctx
 */
function dev_npc_requirement_link_ref_fields(array $ctx)
{
    $prefix = (string) $ctx['prefix'];
    $row = isset($ctx['row']) && is_array($ctx['row']) ? $ctx['row'] : [];
    $ref_table = isset($row['ref_table']) ? (string) $row['ref_table'] : '';
    $id_ref = isset($row['id_ref']) ? (int) $row['id_ref'] : 0;
    $ref_description = isset($row['ref_description']) ? (string) $row['ref_description'] : '';
    $ref_table_id = $prefix . '-ref-table';
    $id_ref_id = $prefix . '-id-ref';
    $ref_desc_id = $prefix . '-ref-description';
    $req_type = isset($row['requirement_type']) ? (string) $row['requirement_type'] : '';
    ?>
    <div class="dev-req-ref-fields mb-2" data-req-type="<?php echo dev_admin_h($req_type); ?>">
        <div class="mb-2">
            <label class="form-label">ref_table</label>
            <select class="form-select form-select-sm dev-req-ref-table" name="ref_table" id="<?php echo dev_admin_h($ref_table_id); ?>">
                <?php foreach ($ctx['requirement_ref_tables'] as $ref_row): ?>
                <option value="<?php echo dev_admin_h($ref_row['value']); ?>"<?php echo $ref_table === $ref_row['value'] ? ' selected' : ''; ?>><?php echo dev_admin_h($ref_row['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">id_ref</label>
            <select class="form-select form-select-sm dev-req-id-ref" name="id_ref" id="<?php echo dev_admin_h($id_ref_id); ?>">
                <option value="0" data-ref-table="">0 — not used</option>
                <?php foreach ($ctx['item_types'] as $item): ?>
                <option value="<?php echo (int) $item['id_item_type']; ?>" data-ref-table="POTION" data-ref-table-alt="item_types"<?php echo in_array($ref_table, ['POTION', 'item_types'], true) && $id_ref === (int) $item['id_item_type'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $item['id_item_type']; ?> <?php echo dev_admin_h($item['nome'] ?: $item['item_type']); ?>
                </option>
                <?php endforeach; ?>
                <?php foreach ($ctx['flat_conversations'] as $c):
                    $conv_labels = dev_npc_conversation_select_labels($c); ?>
                <option value="<?php echo (int) $c['id_conversation']; ?>" class="dev-loc-option" data-ref-table="CONVERSATION" <?php echo dev_npc_loc_data_attrs_from_map($conv_labels); ?><?php echo $ref_table === 'CONVERSATION' && $id_ref === (int) $c['id_conversation'] ? ' selected' : ''; ?>>
                    <?php echo dev_admin_h($conv_labels[$ctx['preview_lang']]); ?>
                </option>
                <?php endforeach; ?>
                <?php foreach ($ctx['player_classes'] as $pc): ?>
                <option value="<?php echo (int) $pc['id_player_class']; ?>" data-ref-table="PLAYER_CLASS" data-ref-description="<?php echo dev_admin_h($pc['code']); ?>"<?php echo $ref_table === 'PLAYER_CLASS' && $id_ref === (int) $pc['id_player_class'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $pc['id_player_class']; ?> <?php echo dev_admin_h($pc['code']); ?> — <?php echo dev_admin_h($pc['name']); ?>
                </option>
                <?php endforeach; ?>
                <option value="0" data-ref-table="ZERO"<?php echo $ref_table === 'ZERO' ? ' selected' : ''; ?>>0 — ZERO</option>
                <option value="0" data-ref-table="HAS_ANIMALS"<?php echo in_array($ref_table, ['HAS_ANIMALS', 'animals'], true) ? ' selected' : ''; ?>>0 — HAS_ANIMALS / animals</option>
                <option value="0" data-ref-table="LT_2"<?php echo $ref_table === 'LT_2' ? ' selected' : ''; ?>>0 — LT_2</option>
                <option value="0" data-ref-table="LT_3"<?php echo $ref_table === 'LT_3' ? ' selected' : ''; ?>>0 — LT_3</option>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">ref_description</label>
            <input class="form-control form-control-sm dev-req-ref-description" name="ref_description" id="<?php echo dev_admin_h($ref_desc_id); ?>" maxlength="200" placeholder="Optional label (e.g. nerd)" value="<?php echo dev_admin_h($ref_description); ?>">
        </div>
    </div>
    <?php
}

/**
 * ref_table / id_ref / ref_description / num / params_json on conversation_consequences rows.
 *
 * @param array{
 *   prefix:string,
 *   row?:array,
 *   consequence_ref_tables:array,
 *   item_types:array,
 *   player_classes:array,
 *   buff_definitions:array
 * } $ctx
 */
function dev_npc_consequence_link_ref_fields(array $ctx)
{
    $prefix = (string) $ctx['prefix'];
    $row = isset($ctx['row']) && is_array($ctx['row']) ? $ctx['row'] : [];
    $ref_table = isset($row['ref_table']) ? (string) $row['ref_table'] : '';
    $id_ref = isset($row['id_ref']) ? (int) $row['id_ref'] : 0;
    $ref_description = isset($row['ref_description']) ? (string) $row['ref_description'] : '';
    $num = isset($row['num']) ? (int) $row['num'] : 1;
    $params_json = isset($row['params_json']) ? (string) $row['params_json'] : '';
    $ref_table_id = $prefix . '-ref-table';
    $id_ref_id = $prefix . '-id-ref';
    $ref_desc_id = $prefix . '-ref-description';
    $num_id = $prefix . '-num';
    $params_id = $prefix . '-params-json';
    $cons_type = isset($row['consequence_type']) ? (string) $row['consequence_type'] : '';
    ?>
    <div class="dev-cons-ref-fields mb-2" data-cons-type="<?php echo dev_admin_h($cons_type); ?>">
        <div class="mb-2">
            <label class="form-label">ref_table</label>
            <select class="form-select form-select-sm dev-cons-ref-table" name="ref_table" id="<?php echo dev_admin_h($ref_table_id); ?>">
                <?php foreach ($ctx['consequence_ref_tables'] as $ref_row): ?>
                <option value="<?php echo dev_admin_h($ref_row['value']); ?>"<?php echo $ref_table === $ref_row['value'] ? ' selected' : ''; ?>><?php echo dev_admin_h($ref_row['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">id_ref</label>
            <select class="form-select form-select-sm dev-cons-id-ref" name="id_ref" id="<?php echo dev_admin_h($id_ref_id); ?>">
                <option value="0" data-ref-table="">0 — not used</option>
                <?php foreach ($ctx['item_types'] as $item): ?>
                <option value="<?php echo (int) $item['id_item_type']; ?>" data-ref-table="item_types" data-ref-table-alt="POTION"<?php echo in_array($ref_table, ['item_types', 'POTION'], true) && $id_ref === (int) $item['id_item_type'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $item['id_item_type']; ?> <?php echo dev_admin_h($item['nome'] ?: $item['item_type']); ?>
                </option>
                <?php endforeach; ?>
                <?php foreach ($ctx['player_classes'] as $pc): ?>
                <option value="<?php echo (int) $pc['id_player_class']; ?>" data-ref-table="PLAYER_CLASS" data-ref-description="<?php echo dev_admin_h($pc['code']); ?>"<?php echo $ref_table === 'PLAYER_CLASS' && $id_ref === (int) $pc['id_player_class'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $pc['id_player_class']; ?> <?php echo dev_admin_h($pc['code']); ?> — <?php echo dev_admin_h($pc['name']); ?>
                </option>
                <?php endforeach; ?>
                <?php foreach (($ctx['buff_definitions'] ?? []) as $buff): ?>
                <option value="<?php echo (int) $buff['id_buff_definition']; ?>" data-ref-table="buff_definitions" data-ref-description="<?php echo dev_admin_h($buff['buff_code']); ?>"<?php echo $ref_table === 'buff_definitions' && $id_ref === (int) $buff['id_buff_definition'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $buff['id_buff_definition']; ?> <?php echo dev_admin_h($buff['buff_code']); ?> — <?php echo dev_admin_h($buff['name']); ?> (<?php echo dev_admin_h($buff['target_entity']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">ref_description</label>
            <input class="form-control form-control-sm dev-cons-ref-description" name="ref_description" id="<?php echo dev_admin_h($ref_desc_id); ?>" maxlength="200" placeholder="e.g. scientist (class code label)" value="<?php echo dev_admin_h($ref_description); ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">num <span class="text-muted">(quantity, or duration seconds for grant_team_buff)</span></label>
            <input class="form-control form-control-sm" name="num" type="number" value="<?php echo $num; ?>" min="1" id="<?php echo dev_admin_h($num_id); ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">params_json</label>
            <textarea class="form-control form-control-sm font-monospace dev-cons-params-json" name="params_json" rows="2" id="<?php echo dev_admin_h($params_id); ?>" placeholder='{"duration_seconds":3600,"alive_only":false}'><?php echo dev_admin_h($params_json); ?></textarea>
        </div>
    </div>
    <?php
}

/**
 * @return array{ok:bool,message?:string,id_ref:int,ref_table:?string,ref_description:?string,num:int,params_json:?string}
 */
function dev_npc_consequence_link_params_from_post(array $post)
{
    $params_json = dev_npc_post_str($post, 'params_json', 65535);
    $params_json = trim($params_json) === '' ? null : $params_json;

    if ($params_json !== null)
    {
        json_decode($params_json, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return ['ok' => false, 'message' => 'params_json is not valid JSON.'];
        }
    }

    $ref_table = dev_npc_post_str($post, 'ref_table', 100);

    return [
        'ok' => true,
        'id_ref' => dev_npc_post_int($post, 'id_ref'),
        'ref_table' => $ref_table !== '' ? $ref_table : null,
        'ref_description' => dev_npc_post_str($post, 'ref_description', 200) ?: null,
        'num' => dev_npc_post_int($post, 'num', 1),
        'params_json' => $params_json
    ];
}

/**
 * @return array{ok:bool,message?:string,id_requirement?:int}
 */
function dev_npc_create_requirement_from_post(PDO $conn, array $post)
{
    $requirement_type = dev_npc_post_str($post, 'requirement_type', 100);

    $stmt = $conn->prepare('
        INSERT INTO requirements (requirement_type)
        VALUES (:requirement_type)
    ');
    $stmt->execute([
        ':requirement_type' => $requirement_type
    ]);

    return ['ok' => true, 'id_requirement' => (int) $conn->lastInsertId()];
}

/**
 * @return array{ok:bool,message?:string,id_requirement?:int}
 */
function dev_npc_find_or_create_requirement(PDO $conn, array $post)
{
    $requirement_type = dev_npc_post_str($post, 'requirement_type', 100);

    $stmt = $conn->prepare('
        SELECT id_requirement
        FROM requirements
        WHERE requirement_type = :requirement_type
        LIMIT 1
    ');
    $stmt->execute([
        ':requirement_type' => $requirement_type
    ]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing)
    {
        return ['ok' => true, 'id_requirement' => (int) $existing['id_requirement']];
    }

    return dev_npc_create_requirement_from_post($conn, $post);
}

/**
 * @return array{ok:bool,message?:string,id_consequence?:int}
 */
function dev_npc_create_consequence_from_post(PDO $conn, array $post)
{
    $stmt = $conn->prepare('
        INSERT INTO consequences (consequence_type)
        VALUES (:consequence_type)
    ');
    $stmt->execute([
        ':consequence_type' => dev_npc_post_str($post, 'consequence_type', 100)
    ]);

    return ['ok' => true, 'id_consequence' => (int) $conn->lastInsertId()];
}

/**
 * @return array{ok:bool,message?:string,id_consequence?:int}
 */
function dev_npc_find_or_create_consequence(PDO $conn, array $post)
{
    $consequence_type = dev_npc_post_str($post, 'consequence_type', 100);

    $stmt = $conn->prepare('
        SELECT id_consequence
        FROM consequences
        WHERE consequence_type = :consequence_type
        LIMIT 1
    ');
    $stmt->execute([
        ':consequence_type' => $consequence_type
    ]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing)
    {
        return ['ok' => true, 'id_consequence' => (int) $existing['id_consequence']];
    }

    return dev_npc_create_consequence_from_post($conn, $post);
}

function dev_npc_fetch_tree(PDO $conn)
{
    $tree = [];

    $stmt_npcs = $conn->query('SELECT * FROM npcs ORDER BY id_npc ASC');

    if (!$stmt_npcs)
    {
        return [];
    }

    while ($npc = $stmt_npcs->fetch(PDO::FETCH_ASSOC))
    {
        $id_npc = (int) $npc['id_npc'];
        $tree[$id_npc] = $npc;
        $tree[$id_npc]['requirements'] = [];
        $tree[$id_npc]['conversations'] = [];
        $tree[$id_npc]['quests'] = [];
    }

    if (!$tree)
    {
        return [];
    }

    $stmt_nr = $conn->query('
        SELECT NR.id_npc, NR.id_npc_requirement, NR.id_requirement,
               NR.id_ref, NR.ref_table, NR.ref_description,
               NR.min, NR.max, NR.descrizione,
               R.requirement_type
        FROM npc_requirements NR
        JOIN requirements R ON R.id_requirement = NR.id_requirement
        ORDER BY NR.id_npc, NR.id_npc_requirement
    ');

    if ($stmt_nr)
    {
        while ($row = $stmt_nr->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $row['id_npc'];

            if (isset($tree[$id_npc]))
            {
                $tree[$id_npc]['requirements'][] = $row;
            }
        }
    }

    $stmt_quests = $conn->query('SELECT * FROM quests ORDER BY id_quest ASC');

    if ($stmt_quests)
    {
        while ($quest = $stmt_quests->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $quest['id_starter_npc'];

            if (isset($tree[$id_npc]))
            {
                $quest['requirements'] = [];
                $tree[$id_npc]['quests'][(int) $quest['id_quest']] = $quest;
            }
        }
    }

    $stmt_qr = $conn->query('
        SELECT QR.id_quest, QR.id_quest_requirement, QR.id_requirement,
               QR.id_ref, QR.ref_table, QR.ref_description,
               QR.min, QR.max, QR.descrizione,
               R.requirement_type
        FROM quest_requirements QR
        JOIN requirements R ON R.id_requirement = QR.id_requirement
        ORDER BY QR.id_quest
    ');

    if ($stmt_qr)
    {
        while ($row = $stmt_qr->fetch(PDO::FETCH_ASSOC))
        {
            $id_quest = (int) $row['id_quest'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['quests'][$id_quest]))
                {
                    $tree[$id_npc]['quests'][$id_quest]['requirements'][] = $row;
                    break;
                }
            }
        }
    }

    $stmt_conv = $conn->query('
        SELECT * FROM conversations ORDER BY id_npc ASC, id_conversation ASC
    ');

    if ($stmt_conv)
    {
        while ($conv = $stmt_conv->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $conv['id_npc'];

            if (!isset($tree[$id_npc]))
            {
                continue;
            }

            $id_conv = (int) $conv['id_conversation'];
            $conv['requirements'] = [];
            $conv['dialogues'] = [];
            $tree[$id_npc]['conversations'][$id_conv] = $conv;
        }
    }

    $stmt_cr = $conn->query('
        SELECT CR.id_conversation, CR.id_conversation_requirement, CR.id_requirement,
               CR.id_ref, CR.ref_table, CR.ref_description,
               CR.min, CR.max, CR.descrizione,
               R.requirement_type
        FROM conversation_requirements CR
        JOIN requirements R ON R.id_requirement = CR.id_requirement
        ORDER BY CR.id_conversation
    ');

    if ($stmt_cr)
    {
        while ($row = $stmt_cr->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['conversations'][$id_conv]))
                {
                    $tree[$id_npc]['conversations'][$id_conv]['requirements'][] = $row;
                    break;
                }
            }
        }
    }

    $stmt_dlg = $conn->query('
        SELECT * FROM dialogues ORDER BY id_conversation ASC, `order` ASC, id_dialog ASC
    ');

    if ($stmt_dlg)
    {
        while ($dlg = $stmt_dlg->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $dlg['id_conversation'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['conversations'][$id_conv]))
                {
                    $id_dialog = (int) $dlg['id_dialog'];
                    $dlg['options'] = [];
                    $dlg['consequences'] = [];
                    $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog] = $dlg;
                    break;
                }
            }
        }
    }

    $stmt_opt = $conn->query('
        SELECT * FROM dialogues_options ORDER BY id_dialog ASC, option_n ASC, id_dialog_option ASC
    ');

    if ($stmt_opt)
    {
        while ($opt = $stmt_opt->fetch(PDO::FETCH_ASSOC))
        {
            $id_dialog = (int) $opt['id_dialog'];

            foreach ($tree as $id_npc => $npc_data)
            {
                foreach ($npc_data['conversations'] as $id_conv => $conv)
                {
                    if (isset($conv['dialogues'][$id_dialog]))
                    {
                        $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog]['options'][] = $opt;
                        break 2;
                    }
                }
            }
        }
    }

    $stmt_cc = $conn->query('
        SELECT CC.id_conversation_consequence, CC.id_conversation, CC.id_option, CC.id_consequence,
               CC.id_ref, CC.ref_table, CC.ref_description, CC.num, CC.params_json,
               C.consequence_type
        FROM conversation_consequences CC
        JOIN consequences C ON C.id_consequence = CC.id_consequence
        ORDER BY CC.id_conversation, CC.id_option
    ');

    if ($stmt_cc)
    {
        while ($row = $stmt_cc->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];
            $id_option = (int) $row['id_option'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (!isset($npc_data['conversations'][$id_conv]))
                {
                    continue;
                }

                foreach ($npc_data['conversations'][$id_conv]['dialogues'] as $id_dialog => $dlg)
                {
                    foreach ($dlg['options'] as $opt)
                    {
                        if ((int) $opt['id_dialog_option'] === $id_option)
                        {
                            $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog]['consequences'][] = $row;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    return $tree;
}

function dev_npc_post_int(array $post, $key, $default = 0)
{
    return isset($post[$key]) ? (int) $post[$key] : $default;
}

function dev_npc_post_str(array $post, $key, $max_len = 500)
{
    $value = isset($post[$key]) ? trim((string) $post[$key]) : '';

    return mb_substr($value, 0, $max_len);
}

function dev_npc_post_yn(array $post, $key, $default = 'N')
{
    $value = dev_npc_post_str($post, $key, 1);

    return ($value === 'S') ? 'S' : $default;
}

function dev_npc_handle_post(PDO $conn, array $post)
{
    $action = dev_npc_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'add_npc':
                $stmt = $conn->prepare('
                    INSERT INTO npcs (npc, type, id_zone, posx, posy, posz, wander_range, direction, sight_distance, npc_type_prefab)
                    VALUES (:npc, :type, :id_zone, :posx, :posy, :posz, :wander_range, :direction, :sight_distance, :npc_type_prefab)
                ');
                $stmt->execute([
                    ':npc' => dev_npc_post_str($post, 'npc', 100),
                    ':type' => dev_npc_post_str($post, 'type', 100),
                    ':id_zone' => dev_npc_post_int($post, 'id_zone', 1000),
                    ':posx' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posx', 20)),
                    ':posy' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posy', 20)),
                    ':posz' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posz', 20)),
                    ':wander_range' => dev_npc_post_int($post, 'wander_range'),
                    ':direction' => dev_npc_post_str($post, 'direction', 1) ?: 'D',
                    ':sight_distance' => dev_npc_post_int($post, 'sight_distance'),
                    ':npc_type_prefab' => dev_npc_post_str($post, 'npc_type_prefab', 100) ?: 'trader'
                ]);

                return ['ok' => true, 'message' => 'NPC created (id ' . $conn->lastInsertId() . ').'];

            case 'update_npc':
                $stmt = $conn->prepare('
                    UPDATE npcs
                    SET npc = :npc,
                        type = :type,
                        id_zone = :id_zone,
                        posx = :posx,
                        posy = :posy,
                        posz = :posz,
                        npc_type_prefab = :npc_type_prefab
                    WHERE id_npc = :id_npc
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':npc' => dev_npc_post_str($post, 'npc', 100),
                    ':type' => dev_npc_post_str($post, 'type', 100),
                    ':id_zone' => dev_npc_post_int($post, 'id_zone', 1000),
                    ':posx' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posx', 20)),
                    ':posy' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posy', 20)),
                    ':posz' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posz', 20)),
                    ':npc_type_prefab' => dev_npc_post_str($post, 'npc_type_prefab', 100) ?: 'trader'
                ]);

                return ['ok' => true, 'message' => 'NPC updated (id ' . dev_npc_post_int($post, 'id_npc') . ').'];

            case 'add_conversation':
                $stmt = $conn->prepare('
                    INSERT INTO conversations (id_npc, visible, title, title_it, title_pt, flg_register)
                    VALUES (:id_npc, :visible, :title, :title_it, :title_pt, :flg_register)
                ');
                $stmt->execute([
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':visible' => dev_npc_post_yn($post, 'visible', 'S'),
                    ':title' => dev_npc_post_str($post, 'title', 200),
                    ':title_it' => dev_npc_post_str($post, 'title_it', 200),
                    ':title_pt' => dev_npc_post_str($post, 'title_pt', 200),
                    ':flg_register' => dev_npc_post_yn($post, 'flg_register', 'N')
                ]);

                return ['ok' => true, 'message' => 'Conversation created (id ' . $conn->lastInsertId() . ').'];

            case 'update_conversation':
                $stmt = $conn->prepare('
                    UPDATE conversations
                    SET id_npc = :id_npc,
                        visible = :visible,
                        title = :title,
                        title_it = :title_it,
                        title_pt = :title_pt,
                        flg_register = :flg_register
                    WHERE id_conversation = :id_conversation
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':visible' => dev_npc_post_yn($post, 'visible', 'S'),
                    ':title' => dev_npc_post_str($post, 'title', 200),
                    ':title_it' => dev_npc_post_str($post, 'title_it', 200),
                    ':title_pt' => dev_npc_post_str($post, 'title_pt', 200),
                    ':flg_register' => dev_npc_post_yn($post, 'flg_register', 'N')
                ]);

                return ['ok' => true, 'message' => 'Conversation updated (id ' . dev_npc_post_int($post, 'id_conversation') . ').'];

            case 'add_dialogue':
                $stmt = $conn->prepare('
                    INSERT INTO dialogues (id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt)
                    VALUES (:id_conversation, :ord, :flg_last, :flg_options, :dialog, :dialog_it, :dialog_pt)
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':ord' => dev_npc_post_int($post, 'order', 1),
                    ':flg_last' => dev_npc_post_yn($post, 'flg_last', 'N'),
                    ':flg_options' => dev_npc_post_yn($post, 'flg_options', 'N'),
                    ':dialog' => dev_npc_post_str($post, 'dialog', 500),
                    ':dialog_it' => dev_npc_post_str($post, 'dialog_it', 500),
                    ':dialog_pt' => dev_npc_post_str($post, 'dialog_pt', 500)
                ]);

                return ['ok' => true, 'message' => 'Dialogue created (id ' . $conn->lastInsertId() . ').'];

            case 'update_dialogue':
                $stmt = $conn->prepare('
                    UPDATE dialogues
                    SET id_conversation = :id_conversation,
                        `order` = :ord,
                        flg_last = :flg_last,
                        flg_options = :flg_options,
                        dialog = :dialog,
                        dialog_it = :dialog_it,
                        dialog_pt = :dialog_pt
                    WHERE id_dialog = :id_dialog
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_dialog' => dev_npc_post_int($post, 'id_dialog'),
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':ord' => dev_npc_post_int($post, 'order', 1),
                    ':flg_last' => dev_npc_post_yn($post, 'flg_last', 'N'),
                    ':flg_options' => dev_npc_post_yn($post, 'flg_options', 'N'),
                    ':dialog' => dev_npc_post_str($post, 'dialog', 500),
                    ':dialog_it' => dev_npc_post_str($post, 'dialog_it', 500),
                    ':dialog_pt' => dev_npc_post_str($post, 'dialog_pt', 500)
                ]);

                return ['ok' => true, 'message' => 'Dialogue updated (id ' . dev_npc_post_int($post, 'id_dialog') . ').'];

            case 'add_dialog_option':
                $stmt = $conn->prepare('
                    INSERT INTO dialogues_options (id_dialog, option_n, option_color, option_text, option_text_it, option_text_pt)
                    VALUES (:id_dialog, :option_n, :option_color, :option_text, :option_text_it, :option_text_pt)
                ');
                $stmt->execute([
                    ':id_dialog' => dev_npc_post_int($post, 'id_dialog'),
                    ':option_n' => dev_npc_post_int($post, 'option_n', 1),
                    ':option_color' => dev_npc_post_str($post, 'option_color', 50) ?: 'green',
                    ':option_text' => dev_npc_post_str($post, 'option_text', 200),
                    ':option_text_it' => dev_npc_post_str($post, 'option_text_it', 200),
                    ':option_text_pt' => dev_npc_post_str($post, 'option_text_pt', 200)
                ]);

                return ['ok' => true, 'message' => 'Dialog option created (id ' . $conn->lastInsertId() . ').'];

            case 'update_dialog_option':
                $stmt = $conn->prepare('
                    UPDATE dialogues_options
                    SET id_dialog = :id_dialog,
                        option_n = :option_n,
                        option_color = :option_color,
                        option_text = :option_text,
                        option_text_it = :option_text_it,
                        option_text_pt = :option_text_pt
                    WHERE id_dialog_option = :id_dialog_option
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_dialog_option' => dev_npc_post_int($post, 'id_dialog_option'),
                    ':id_dialog' => dev_npc_post_int($post, 'id_dialog'),
                    ':option_n' => dev_npc_post_int($post, 'option_n', 1),
                    ':option_color' => dev_npc_post_str($post, 'option_color', 50) ?: 'green',
                    ':option_text' => dev_npc_post_str($post, 'option_text', 200),
                    ':option_text_it' => dev_npc_post_str($post, 'option_text_it', 200),
                    ':option_text_pt' => dev_npc_post_str($post, 'option_text_pt', 200)
                ]);

                return ['ok' => true, 'message' => 'Dialog option updated (id ' . dev_npc_post_int($post, 'id_dialog_option') . ').'];

            case 'add_quest':
                $stmt = $conn->prepare('
                    INSERT INTO quests (id_starter_npc, quest, repeatable, quest_type, lvl_min, lvl_max, ids_quests_required)
                    VALUES (:id_starter_npc, :quest, :repeatable, :quest_type, :lvl_min, :lvl_max, :ids_quests_required)
                ');
                $stmt->execute([
                    ':id_starter_npc' => dev_npc_post_int($post, 'id_starter_npc'),
                    ':quest' => dev_npc_post_str($post, 'quest', 200),
                    ':repeatable' => dev_npc_post_yn($post, 'repeatable', 'N'),
                    ':quest_type' => dev_npc_post_str($post, 'quest_type', 100),
                    ':lvl_min' => dev_npc_post_int($post, 'lvl_min'),
                    ':lvl_max' => dev_npc_post_int($post, 'lvl_max', 100),
                    ':ids_quests_required' => dev_npc_post_str($post, 'ids_quests_required', 100) ?: '-1,-1'
                ]);

                return ['ok' => true, 'message' => 'Quest created (id ' . $conn->lastInsertId() . ').'];

            case 'update_quest':
                $stmt = $conn->prepare('
                    UPDATE quests
                    SET id_starter_npc = :id_starter_npc,
                        quest = :quest,
                        repeatable = :repeatable,
                        quest_type = :quest_type,
                        lvl_min = :lvl_min,
                        lvl_max = :lvl_max,
                        ids_quests_required = :ids_quests_required
                    WHERE id_quest = :id_quest
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_quest' => dev_npc_post_int($post, 'id_quest'),
                    ':id_starter_npc' => dev_npc_post_int($post, 'id_starter_npc'),
                    ':quest' => dev_npc_post_str($post, 'quest', 200),
                    ':repeatable' => dev_npc_post_yn($post, 'repeatable', 'N'),
                    ':quest_type' => dev_npc_post_str($post, 'quest_type', 100),
                    ':lvl_min' => dev_npc_post_int($post, 'lvl_min'),
                    ':lvl_max' => dev_npc_post_int($post, 'lvl_max', 100),
                    ':ids_quests_required' => dev_npc_post_str($post, 'ids_quests_required', 100) ?: '-1,-1'
                ]);

                return ['ok' => true, 'message' => 'Quest updated (id ' . dev_npc_post_int($post, 'id_quest') . ').'];

            case 'add_requirement':
                $created = dev_npc_create_requirement_from_post($conn, $post);

                return ['ok' => true, 'message' => 'Requirement created (id ' . $created['id_requirement'] . ').'];

            case 'update_requirement':
                $stmt = $conn->prepare('
                    UPDATE requirements
                    SET requirement_type = :requirement_type
                    WHERE id_requirement = :id_requirement
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':requirement_type' => dev_npc_post_str($post, 'requirement_type', 100)
                ]);

                return ['ok' => true, 'message' => 'Requirement type updated (id ' . dev_npc_post_int($post, 'id_requirement') . ').'];

            case 'attach_requirement':
                $target_type = dev_npc_post_str($post, 'target_type', 20);
                $entity_mode = dev_npc_post_str($post, 'entity_mode', 20);
                $link = dev_npc_link_params_from_post($post);

                if ($entity_mode === 'new')
                {
                    $created = dev_npc_find_or_create_requirement($conn, $post);

                    if (empty($created['ok']))
                    {
                        return ['ok' => false, 'message' => $created['message'] ?? 'Failed to create requirement.'];
                    }

                    $id_requirement = (int) $created['id_requirement'];
                }
                else
                {
                    $id_requirement = dev_npc_post_int($post, 'id_requirement');
                }

                if ($id_requirement <= 0)
                {
                    return ['ok' => false, 'message' => 'Select or create a requirement.'];
                }

                if ($target_type === 'npc')
                {
                    $stmt = $conn->prepare('
                        INSERT INTO npc_requirements (id_npc, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                        VALUES (:id_npc, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                    ');
                    $stmt->execute([
                        ':id_npc' => dev_npc_post_int($post, 'target_id_npc'),
                        ':id_requirement' => $id_requirement,
                        ':id_ref' => $link['id_ref'],
                        ':ref_table' => $link['ref_table'],
                        ':ref_description' => $link['ref_description'],
                        ':min' => $link['min'],
                        ':max' => $link['max'],
                        ':descrizione' => $link['descrizione']
                    ]);

                    return ['ok' => true, 'message' => 'Requirement #' . $id_requirement . ' linked to NPC.'];
                }

                if ($target_type === 'conversation')
                {
                    $stmt = $conn->prepare('
                        INSERT INTO conversation_requirements (id_conversation, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                        VALUES (:id_conversation, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                    ');
                    $stmt->execute([
                        ':id_conversation' => dev_npc_post_int($post, 'target_id_conversation'),
                        ':id_requirement' => $id_requirement,
                        ':id_ref' => $link['id_ref'],
                        ':ref_table' => $link['ref_table'],
                        ':ref_description' => $link['ref_description'],
                        ':min' => $link['min'],
                        ':max' => $link['max'],
                        ':descrizione' => $link['descrizione']
                    ]);

                    return ['ok' => true, 'message' => 'Requirement #' . $id_requirement . ' linked to conversation.'];
                }

                if ($target_type === 'quest')
                {
                    $stmt = $conn->prepare('
                        INSERT INTO quest_requirements (id_quest, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                        VALUES (:id_quest, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                    ');
                    $stmt->execute([
                        ':id_quest' => dev_npc_post_int($post, 'target_id_quest'),
                        ':id_requirement' => $id_requirement,
                        ':id_ref' => $link['id_ref'],
                        ':ref_table' => $link['ref_table'],
                        ':ref_description' => $link['ref_description'],
                        ':min' => $link['min'],
                        ':max' => $link['max'],
                        ':descrizione' => $link['descrizione']
                    ]);

                    return ['ok' => true, 'message' => 'Requirement #' . $id_requirement . ' linked to quest.'];
                }

                return ['ok' => false, 'message' => 'Invalid requirement target type.'];

            case 'link_npc_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    INSERT INTO npc_requirements (id_npc, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                    VALUES (:id_npc, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                ');
                $stmt->execute([
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to NPC.'];

            case 'link_conversation_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    INSERT INTO conversation_requirements (id_conversation, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                    VALUES (:id_conversation, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to conversation.'];

            case 'link_quest_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    INSERT INTO quest_requirements (id_quest, id_requirement, id_ref, ref_table, ref_description, min, max, descrizione)
                    VALUES (:id_quest, :id_requirement, :id_ref, :ref_table, :ref_description, :min, :max, :descrizione)
                ');
                $stmt->execute([
                    ':id_quest' => dev_npc_post_int($post, 'id_quest'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to quest.'];

            case 'update_npc_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    UPDATE npc_requirements
                    SET id_requirement = :id_requirement,
                        id_ref = :id_ref,
                        ref_table = :ref_table,
                        ref_description = :ref_description,
                        min = :min,
                        max = :max,
                        descrizione = :descrizione
                    WHERE id_npc_requirement = :id_npc_requirement
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_npc_requirement' => dev_npc_post_int($post, 'id_npc_requirement'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return [
                    'ok' => true,
                    'message' => 'NPC requirement link updated (#' . dev_npc_post_int($post, 'id_npc_requirement') . ').',
                    'redirect_edit' => 'npc_requirement',
                    'redirect_id' => dev_npc_post_int($post, 'id_npc_requirement')
                ];

            case 'update_conversation_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    UPDATE conversation_requirements
                    SET id_requirement = :id_requirement,
                        id_ref = :id_ref,
                        ref_table = :ref_table,
                        ref_description = :ref_description,
                        min = :min,
                        max = :max,
                        descrizione = :descrizione
                    WHERE id_conversation_requirement = :id_conversation_requirement
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_conversation_requirement' => dev_npc_post_int($post, 'id_conversation_requirement'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return [
                    'ok' => true,
                    'message' => 'Conversation requirement link updated (#' . dev_npc_post_int($post, 'id_conversation_requirement') . ').',
                    'redirect_edit' => 'conversation_requirement',
                    'redirect_id' => dev_npc_post_int($post, 'id_conversation_requirement')
                ];

            case 'update_quest_requirement':
                $link = dev_npc_link_params_from_post($post);
                $stmt = $conn->prepare('
                    UPDATE quest_requirements
                    SET id_requirement = :id_requirement,
                        id_ref = :id_ref,
                        ref_table = :ref_table,
                        ref_description = :ref_description,
                        min = :min,
                        max = :max,
                        descrizione = :descrizione
                    WHERE id_quest_requirement = :id_quest_requirement
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_quest_requirement' => dev_npc_post_int($post, 'id_quest_requirement'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':min' => $link['min'],
                    ':max' => $link['max'],
                    ':descrizione' => $link['descrizione']
                ]);

                return [
                    'ok' => true,
                    'message' => 'Quest requirement link updated (#' . dev_npc_post_int($post, 'id_quest_requirement') . ').',
                    'redirect_edit' => 'quest_requirement',
                    'redirect_id' => dev_npc_post_int($post, 'id_quest_requirement')
                ];

            case 'add_consequence':
                $created = dev_npc_create_consequence_from_post($conn, $post);

                if (empty($created['ok']))
                {
                    return ['ok' => false, 'message' => $created['message'] ?? 'Failed to create consequence.'];
                }

                return ['ok' => true, 'message' => 'Consequence created (id ' . $created['id_consequence'] . ').'];

            case 'update_consequence':
                $stmt = $conn->prepare('
                    UPDATE consequences
                    SET consequence_type = :consequence_type
                    WHERE id_consequence = :id_consequence
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_consequence' => dev_npc_post_int($post, 'id_consequence'),
                    ':consequence_type' => dev_npc_post_str($post, 'consequence_type', 100)
                ]);

                return ['ok' => true, 'message' => 'Consequence updated (id ' . dev_npc_post_int($post, 'id_consequence') . ').'];

            case 'attach_consequence':
                $link = dev_npc_consequence_link_params_from_post($post);

                if (empty($link['ok']))
                {
                    return ['ok' => false, 'message' => $link['message'] ?? 'Invalid consequence link fields.'];
                }

                $entity_mode = dev_npc_post_str($post, 'entity_mode', 20);

                if ($entity_mode === 'new')
                {
                    $created = dev_npc_find_or_create_consequence($conn, $post);

                    if (empty($created['ok']))
                    {
                        return ['ok' => false, 'message' => $created['message'] ?? 'Failed to create consequence.'];
                    }

                    $id_consequence = (int) $created['id_consequence'];
                }
                else
                {
                    $id_consequence = dev_npc_post_int($post, 'id_consequence');
                }

                if ($id_consequence <= 0)
                {
                    return ['ok' => false, 'message' => 'Select or create a consequence.'];
                }

                $stmt = $conn->prepare('
                    INSERT INTO conversation_consequences (
                        id_conversation, id_option, id_consequence,
                        id_ref, ref_table, ref_description, num, params_json
                    )
                    VALUES (
                        :id_conversation, :id_option, :id_consequence,
                        :id_ref, :ref_table, :ref_description, :num, :params_json
                    )
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'target_id_conversation'),
                    ':id_option' => dev_npc_post_int($post, 'target_id_option'),
                    ':id_consequence' => $id_consequence,
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':num' => $link['num'],
                    ':params_json' => $link['params_json']
                ]);

                return ['ok' => true, 'message' => 'Consequence #' . $id_consequence . ' linked to dialog option.'];

            case 'link_conversation_consequence':
                $link = dev_npc_consequence_link_params_from_post($post);

                if (empty($link['ok']))
                {
                    return ['ok' => false, 'message' => $link['message'] ?? 'Invalid consequence link fields.'];
                }

                $stmt = $conn->prepare('
                    INSERT INTO conversation_consequences (
                        id_conversation, id_option, id_consequence,
                        id_ref, ref_table, ref_description, num, params_json
                    )
                    VALUES (
                        :id_conversation, :id_option, :id_consequence,
                        :id_ref, :ref_table, :ref_description, :num, :params_json
                    )
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':id_option' => dev_npc_post_int($post, 'id_option'),
                    ':id_consequence' => dev_npc_post_int($post, 'id_consequence'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':num' => $link['num'],
                    ':params_json' => $link['params_json']
                ]);

                return ['ok' => true, 'message' => 'Consequence linked to conversation option.'];

            case 'update_conversation_consequence':
                $link = dev_npc_consequence_link_params_from_post($post);

                if (empty($link['ok']))
                {
                    return ['ok' => false, 'message' => $link['message'] ?? 'Invalid consequence link fields.'];
                }

                $stmt = $conn->prepare('
                    UPDATE conversation_consequences
                    SET id_consequence = :id_consequence,
                        id_ref = :id_ref,
                        ref_table = :ref_table,
                        ref_description = :ref_description,
                        num = :num,
                        params_json = :params_json
                    WHERE id_conversation_consequence = :id_conversation_consequence
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_conversation_consequence' => dev_npc_post_int($post, 'id_conversation_consequence'),
                    ':id_consequence' => dev_npc_post_int($post, 'id_consequence'),
                    ':id_ref' => $link['id_ref'],
                    ':ref_table' => $link['ref_table'],
                    ':ref_description' => $link['ref_description'],
                    ':num' => $link['num'],
                    ':params_json' => $link['params_json']
                ]);

                return [
                    'ok' => true,
                    'message' => 'Conversation consequence link updated (#' . dev_npc_post_int($post, 'id_conversation_consequence') . ').',
                    'redirect_edit' => 'conversation_consequence',
                    'redirect_id' => dev_npc_post_int($post, 'id_conversation_consequence')
                ];

            default:
                return ['ok' => false, 'message' => 'Unknown action.'];
        }
    }
    catch (PDOException $e)
    {
        error_log('[dev_npc_content] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function dev_npc_flat_conversations(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            $list[] = [
                'id_conversation' => $id_conv,
                'id_npc' => $id_npc,
                'npc' => $npc['npc'],
                'title' => $conv['title'],
                'title_it' => isset($conv['title_it']) ? $conv['title_it'] : '',
                'title_pt' => isset($conv['title_pt']) ? $conv['title_pt'] : ''
            ];
        }
    }

    return $list;
}

function dev_npc_flat_dialogues(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            foreach ($conv['dialogues'] as $id_dialog => $dlg)
            {
                $list[] = [
                    'id_dialog' => $id_dialog,
                    'id_conversation' => $id_conv,
                    'npc' => $npc['npc'],
                    'title' => $conv['title'],
                    'title_it' => isset($conv['title_it']) ? $conv['title_it'] : '',
                    'title_pt' => isset($conv['title_pt']) ? $conv['title_pt'] : '',
                    'order' => $dlg['order'],
                    'dialog' => $dlg['dialog'],
                    'dialog_it' => isset($dlg['dialog_it']) ? $dlg['dialog_it'] : '',
                    'dialog_pt' => isset($dlg['dialog_pt']) ? $dlg['dialog_pt'] : ''
                ];
            }
        }
    }

    return $list;
}

function dev_npc_flat_options(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            foreach ($conv['dialogues'] as $id_dialog => $dlg)
            {
                foreach ($dlg['options'] as $opt)
                {
                    $list[] = [
                        'id_dialog_option' => (int) $opt['id_dialog_option'],
                        'id_dialog' => $id_dialog,
                        'id_conversation' => $id_conv,
                        'id_npc' => $id_npc,
                        'npc' => $npc['npc'],
                        'option_text' => $opt['option_text'],
                        'option_text_it' => isset($opt['option_text_it']) ? $opt['option_text_it'] : '',
                        'option_text_pt' => isset($opt['option_text_pt']) ? $opt['option_text_pt'] : '',
                        'conversation_title' => $conv['title'],
                        'conversation_title_it' => isset($conv['title_it']) ? $conv['title_it'] : '',
                        'conversation_title_pt' => isset($conv['title_pt']) ? $conv['title_pt'] : ''
                    ];
                }
            }
        }
    }

    return $list;
}

function dev_npc_flat_quests(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['quests'] as $id_quest => $quest)
        {
            $list[] = [
                'id_quest' => $id_quest,
                'id_starter_npc' => $id_npc,
                'npc' => $npc['npc'],
                'quest' => $quest['quest']
            ];
        }
    }

    return $list;
}

function dev_npc_preview_languages()
{
    return [
        'en' => 'EN',
        'it' => 'IT',
        'pt' => 'PT'
    ];
}

function dev_npc_resolve_preview_lang($lang)
{
    $lang = strtolower(trim((string) $lang));

    if ($lang === 'it' || $lang === 'pt')
    {
        return $lang;
    }

    return 'en';
}

function dev_npc_get_preview_lang()
{
    if (isset($_GET['lang']))
    {
        $lang = dev_npc_resolve_preview_lang($_GET['lang']);
        $_SESSION['dev_npc_preview_lang'] = $lang;

        return $lang;
    }

    if (isset($_SESSION['dev_npc_preview_lang']))
    {
        return dev_npc_resolve_preview_lang($_SESSION['dev_npc_preview_lang']);
    }

    return 'en';
}

function dev_npc_localized_field(array $row, $base, $lang)
{
    if ($lang === 'it')
    {
        $localized = isset($row[$base . '_it']) ? trim((string) $row[$base . '_it']) : '';

        if ($localized !== '')
        {
            return $localized;
        }
    }

    if ($lang === 'pt')
    {
        $localized = isset($row[$base . '_pt']) ? trim((string) $row[$base . '_pt']) : '';

        if ($localized !== '')
        {
            return $localized;
        }
    }

    return isset($row[$base]) ? (string) $row[$base] : '';
}

function dev_npc_loc_data_attrs(array $row, $base)
{
    $en = isset($row[$base]) ? (string) $row[$base] : '';
    $it = dev_npc_localized_field($row, $base, 'it');
    $pt = dev_npc_localized_field($row, $base, 'pt');

    return 'data-loc-en="' . dev_admin_h($en) . '"'
        . ' data-loc-it="' . dev_admin_h($it) . '"'
        . ' data-loc-pt="' . dev_admin_h($pt) . '"';
}

function dev_npc_loc_data_attrs_from_map(array $labels)
{
    return 'data-loc-en="' . dev_admin_h(isset($labels['en']) ? $labels['en'] : '') . '"'
        . ' data-loc-it="' . dev_admin_h(isset($labels['it']) ? $labels['it'] : '') . '"'
        . ' data-loc-pt="' . dev_admin_h(isset($labels['pt']) ? $labels['pt'] : '') . '"';
}

function dev_npc_conversation_select_labels(array $c)
{
    $labels = [];

    foreach (['en', 'it', 'pt'] as $lang)
    {
        $title = dev_npc_localized_field($c, 'title', $lang);
        $labels[$lang] = '#' . (int) $c['id_conversation'] . ' — ' . $c['npc'] . ': ' . $title;
    }

    return $labels;
}

function dev_npc_dialogue_select_labels(array $d)
{
    $labels = [];

    foreach (['en', 'it', 'pt'] as $lang)
    {
        $dialog = dev_npc_localized_field($d, 'dialog', $lang);
        $labels[$lang] = '#' . (int) $d['id_dialog'] . ' [' . (int) $d['order'] . '] ' . mb_substr($dialog, 0, 60);
    }

    return $labels;
}

function dev_npc_option_select_labels(array $o)
{
    $labels = [];

    foreach (['en', 'it', 'pt'] as $lang)
    {
        $labels[$lang] = '#' . (int) $o['id_dialog_option'] . ' — ' . dev_npc_localized_field($o, 'option_text', $lang);
    }

    return $labels;
}
