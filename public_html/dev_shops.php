<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_shops_content.php';

dev_admin_require_auth();

$flash = '';
$flash_ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $result = dev_shops_handle_post($conn, $_POST);
    $flash_ok = $result['ok'];
    $flash = $result['message'];
    $redirect = isset($result['redirect']) ? $result['redirect'] : dev_admin_page_url('dev_shops.php');
    $sep = (strpos($redirect, '?') !== false) ? '&' : '?';

    header('Location: ' . $redirect . $sep . 'msg=' . rawurlencode($flash) . '&ok=' . ($flash_ok ? '1' : '0'));
    exit;
}

if (isset($_GET['msg']))
{
    $flash = (string) $_GET['msg'];
    $flash_ok = !isset($_GET['ok']) || $_GET['ok'] === '1';
}

$shops = dev_shops_fetch_tree($conn);
$item_types = dev_npc_fetch_item_types($conn);
$transactions = dev_shops_fetch_transactions($conn, 50);

$selected_id_shop = isset($_GET['id_shop']) ? (int) $_GET['id_shop'] : 0;

if ($selected_id_shop <= 0 && $shops)
{
    $selected_id_shop = (int) array_key_first($shops);
}

$edit_type = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit_item = null;

if ($edit_type === 'shop' && isset($shops[$edit_id]))
{
    $edit_item = $shops[$edit_id];
    $selected_id_shop = $edit_id;
}
elseif ($edit_type === 'shop_item' && isset($shops[$selected_id_shop]))
{
    foreach ($shops[$selected_id_shop]['shop_items'] as $si)
    {
        if ((int) $si['id_shop_item'] === $edit_id)
        {
            $edit_item = $si;
            break;
        }
    }
}

$token = dev_admin_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — Shops Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .meta { color: #94a3b8; font-size: .85rem; }
        .schema-box { font-size: .85rem; color: #adb5bd; }
        .shop-row { border-left: 3px solid #4dabf7; margin-bottom: .5rem; padding: .5rem .75rem; border-radius: 4px; }
        .shop-row.is-selected { background: rgba(77, 171, 247, 0.12); }
        strong { color: #4dabf7; }
        label { color: #4dabf7; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Shops Dev</h1>
            <p class="meta mb-0">Token-protected. Manage vendor shops and their catalogs. Attach a shop to NPC dialog via the <code>[open shop]</code> consequence (<code>ref_table = shops</code>, <code>id_ref = id_shop</code>) in the NPC content tool.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php')); ?>">NPC content</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_static_data.php')); ?>">Static data</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>">Refresh</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="alert alert-<?php echo $flash_ok ? 'success' : 'danger'; ?>"><?php echo dev_admin_h($flash); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card dev-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Shops</strong>
                    <span class="badge bg-secondary"><?php echo count($shops); ?> shops</span>
                </div>
                <div class="card-body">
                    <?php if (!$shops): ?>
                    <p class="meta mb-0">No shops in database yet. Create one with the form on the right.</p>
                    <?php else: ?>
                    <?php foreach ($shops as $id_shop => $shop): ?>
                    <div class="shop-row<?php echo $id_shop === $selected_id_shop ? ' is-selected' : ''; ?>">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <strong>#<?php echo (int) $id_shop; ?> <?php echo dev_admin_h($shop['name']); ?></strong>
                            <span class="badge bg-info text-dark"><?php echo dev_admin_h($shop['shop_type']); ?></span>
                            <?php if ($shop['flg_active'] === 'S'): ?>
                            <span class="badge bg-success">active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">inactive</span>
                            <?php endif; ?>
                            <?php if ($shop['flg_buys_from_player'] === 'S'): ?>
                            <span class="badge bg-warning text-dark">buys from player</span>
                            <?php endif; ?>
                            <span class="meta">(<?php echo count($shop['shop_items']); ?> items)</span>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php', ['id_shop' => $id_shop])); ?>">View catalog</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php', ['edit' => 'shop', 'id' => $id_shop])); ?>" title="Edit shop">✎</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_id_shop > 0 && isset($shops[$selected_id_shop])): ?>
            <div class="card dev-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Catalog — #<?php echo (int) $selected_id_shop; ?> <?php echo dev_admin_h($shops[$selected_id_shop]['name']); ?></strong>
                    <span class="badge bg-secondary"><?php echo count($shops[$selected_id_shop]['shop_items']); ?> items</span>
                </div>
                <div class="card-body">
                    <?php if (!$shops[$selected_id_shop]['shop_items']): ?>
                    <p class="meta mb-0">No items in this shop's catalog yet.</p>
                    <?php else: ?>
                    <table class="table table-sm table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Sell price</th>
                                <th>Stock</th>
                                <th>Active</th>
                                <th>Sort</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shops[$selected_id_shop]['shop_items'] as $si): ?>
                            <tr>
                                <td><?php echo dev_admin_h(dev_shops_item_label($si)); ?></td>
                                <td><?php echo $si['price_override'] !== null ? (int) $si['price_override'] : '(' . (int) $si['item_price'] . ')'; ?></td>
                                <td><?php echo $si['sell_price_override'] !== null ? (int) $si['sell_price_override'] : '(' . (int) $si['item_sell_price'] . ')'; ?></td>
                                <td><?php echo $si['stock_qty'] !== null ? (int) $si['stock_qty'] : '∞'; ?></td>
                                <td><?php echo $si['flg_active'] === 'S' ? 'S' : 'N'; ?></td>
                                <td><?php echo (int) $si['sort_order']; ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php', ['id_shop' => $selected_id_shop, 'edit' => 'shop_item', 'id' => (int) $si['id_shop_item']])); ?>" title="Edit">✎</a>
                                    <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>" class="d-inline" onsubmit="return confirm('Remove this item from the shop?');">
                                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                        <input type="hidden" name="action" value="delete_shop_item">
                                        <input type="hidden" name="id_shop_item" value="<?php echo (int) $si['id_shop_item']; ?>">
                                        <input type="hidden" name="id_shop" value="<?php echo (int) $selected_id_shop; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">✕</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card dev-card">
                <div class="card-header"><strong>Recent transactions (last <?php echo count($transactions); ?>)</strong></div>
                <div class="card-body">
                    <?php if (!$transactions): ?>
                    <p class="meta mb-0">No shop transactions logged yet.</p>
                    <?php else: ?>
                    <table class="table table-sm table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>When</th>
                                <th>Shop</th>
                                <th>Player</th>
                                <th>Item</th>
                                <th>Dir</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Total</th>
                                <th>Gold after</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?php echo (int) $tx['id_shop_transaction']; ?></td>
                                <td class="meta"><?php echo dev_admin_h($tx['dt_c']); ?></td>
                                <td><?php echo dev_admin_h($tx['shop_name']); ?></td>
                                <td><?php echo dev_admin_h($tx['display_name'] ?: ('#' . (int) $tx['id_user_ig'])); ?></td>
                                <td><?php echo dev_admin_h($tx['item_nome']); ?></td>
                                <td><span class="badge bg-<?php echo $tx['direction'] === 'BUY' ? 'success' : 'warning text-dark'; ?>"><?php echo dev_admin_h($tx['direction']); ?></span></td>
                                <td><?php echo (int) $tx['quantity']; ?></td>
                                <td><?php echo (int) $tx['unit_price']; ?></td>
                                <td><?php echo (int) $tx['total_gold']; ?></td>
                                <td><?php echo (int) $tx['gold_after']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong><?php echo ($edit_type === 'shop' && $edit_item) ? 'Edit shop' : 'Create shop'; ?></strong></div>
                <div class="card-body">
                    <?php $is_edit_shop = ($edit_type === 'shop' && is_array($edit_item)); ?>
                    <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $is_edit_shop ? 'update_shop' : 'add_shop'; ?>">
                        <?php if ($is_edit_shop): ?>
                        <input type="hidden" name="id_shop" value="<?php echo (int) $edit_item['id_shop']; ?>">
                        <?php endif; ?>
                        <div class="mb-2"><label class="form-label">Name (EN)</label><input class="form-control form-control-sm" name="name" required maxlength="100" value="<?php echo $is_edit_shop ? dev_admin_h($edit_item['name']) : ''; ?>"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Name IT</label><input class="form-control form-control-sm" name="name_it" maxlength="100" value="<?php echo $is_edit_shop ? dev_admin_h((string) ($edit_item['name_it'] ?? '')) : ''; ?>"></div>
                            <div class="col-6"><label class="form-label">Name PT</label><input class="form-control form-control-sm" name="name_pt" maxlength="100" value="<?php echo $is_edit_shop ? dev_admin_h((string) ($edit_item['name_pt'] ?? '')) : ''; ?>"></div>
                        </div>
                        <div class="mb-2"><label class="form-label">shop_key <span class="text-muted">(optional unique dev reference)</span></label><input class="form-control form-control-sm" name="shop_key" maxlength="50" value="<?php echo $is_edit_shop ? dev_admin_h((string) ($edit_item['shop_key'] ?? '')) : ''; ?>"></div>
                        <div class="mb-2"><label class="form-label">shop_type</label><input class="form-control form-control-sm" name="shop_type" maxlength="30" value="<?php echo $is_edit_shop ? dev_admin_h($edit_item['shop_type']) : 'general'; ?>"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Buys from player</label><select class="form-select form-select-sm" name="flg_buys_from_player"><option value="S"<?php echo (!$is_edit_shop || $edit_item['flg_buys_from_player'] === 'S') ? ' selected' : ''; ?>>S</option><option value="N"<?php echo ($is_edit_shop && $edit_item['flg_buys_from_player'] === 'N') ? ' selected' : ''; ?>>N</option></select></div>
                            <div class="col-6"><label class="form-label">Active</label><select class="form-select form-select-sm" name="flg_active"><option value="S"<?php echo (!$is_edit_shop || $edit_item['flg_active'] === 'S') ? ' selected' : ''; ?>>S</option><option value="N"<?php echo ($is_edit_shop && $edit_item['flg_active'] === 'N') ? ' selected' : ''; ?>>N</option></select></div>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_shop ? 'Update shop' : 'Create shop'; ?></button>
                        <?php if ($is_edit_shop): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php', ['id_shop' => (int) $edit_item['id_shop']])); ?>">Cancel edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($selected_id_shop > 0): ?>
            <div class="card dev-card">
                <div class="card-header"><strong><?php echo ($edit_type === 'shop_item' && $edit_item) ? 'Edit catalog item' : 'Add catalog item'; ?> — shop #<?php echo (int) $selected_id_shop; ?></strong></div>
                <div class="card-body">
                    <?php $is_edit_si = ($edit_type === 'shop_item' && is_array($edit_item)); ?>
                    <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $is_edit_si ? 'update_shop_item' : 'add_shop_item'; ?>">
                        <input type="hidden" name="id_shop" value="<?php echo (int) $selected_id_shop; ?>">
                        <?php if ($is_edit_si): ?>
                        <input type="hidden" name="id_shop_item" value="<?php echo (int) $edit_item['id_shop_item']; ?>">
                        <?php endif; ?>
                        <div class="mb-2"><label class="form-label">Item type</label>
                            <select class="form-select form-select-sm" name="id_item_type" <?php echo $is_edit_si ? 'disabled' : 'required'; ?>>
                                <?php foreach ($item_types as $item): ?>
                                <option value="<?php echo (int) $item['id_item_type']; ?>"<?php echo $is_edit_si && (int) $edit_item['id_item_type'] === (int) $item['id_item_type'] ? ' selected' : ''; ?>>
                                    #<?php echo (int) $item['id_item_type']; ?> <?php echo dev_admin_h($item['nome'] ?: $item['item_type']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_edit_si): ?>
                            <input type="hidden" name="id_item_type" value="<?php echo (int) $edit_item['id_item_type']; ?>">
                            <p class="meta mt-1 mb-0">Item type can't be changed after creation — remove and re-add instead.</p>
                            <?php endif; ?>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Price override <span class="text-muted">(blank = item_types.price)</span></label><input class="form-control form-control-sm" name="price_override" type="number" min="0" value="<?php echo $is_edit_si && $edit_item['price_override'] !== null ? (int) $edit_item['price_override'] : ''; ?>"></div>
                            <div class="col-6"><label class="form-label">Sell price override <span class="text-muted">(blank = item_types.sell_price)</span></label><input class="form-control form-control-sm" name="sell_price_override" type="number" min="0" value="<?php echo $is_edit_si && $edit_item['sell_price_override'] !== null ? (int) $edit_item['sell_price_override'] : ''; ?>"></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Stock qty <span class="text-muted">(blank = unlimited)</span></label><input class="form-control form-control-sm" name="stock_qty" type="number" min="0" value="<?php echo $is_edit_si && $edit_item['stock_qty'] !== null ? (int) $edit_item['stock_qty'] : ''; ?>"></div>
                            <div class="col-6"><label class="form-label">Sort order</label><input class="form-control form-control-sm" name="sort_order" type="number" min="0" value="<?php echo $is_edit_si ? (int) $edit_item['sort_order'] : 0; ?>"></div>
                        </div>
                        <div class="mb-2"><label class="form-label">Active</label><select class="form-select form-select-sm" name="flg_active"><option value="S"<?php echo (!$is_edit_si || $edit_item['flg_active'] === 'S') ? ' selected' : ''; ?>>S</option><option value="N"<?php echo ($is_edit_si && $edit_item['flg_active'] === 'N') ? ' selected' : ''; ?>>N</option></select></div>
                        <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_si ? 'Update catalog item' : 'Add to catalog'; ?></button>
                        <?php if ($is_edit_si): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php', ['id_shop' => $selected_id_shop])); ?>">Cancel edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card dev-card mt-3">
                <div class="card-header"><strong>Notes</strong></div>
                <div class="card-body schema-box">
                    <p class="mb-2">Buying requires <code>item_types.flg_buyable = 'S'</code> and an active <code>shop_items</code> row with a resolved price &gt; 0.</p>
                    <p class="mb-2">Selling only requires <code>item_types.flg_sellable = 'S'</code> and <code>sell_price &gt; 0</code> on a shop with <code>flg_buys_from_player = 'S'</code> — a <code>shop_items</code> row is optional and only needed to override the sell price for this shop.</p>
                    <p class="mb-0">Attach a shop to a dialog option via the <strong>NPC content</strong> tool's Consequences tab: consequence type <code>[open shop]</code>, ref_table <code>shops</code>, id_ref = this shop's id. Use a repeatable / non-registering option so the shop reopens on future visits.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
