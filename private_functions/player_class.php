<?php

/**
 * Player classes (gameplay identity). Cosmetic avatar remains users_ig.character_type.
 */
class PLAYER_CLASS
{
  const STARTER_CODES = ['nerd', 'stud'];

  public static function langSuffix()
  {
    global $LANG;

    if (isset($LANG) && $LANG === '_it')
    {
      return '_it';
    }

    if (isset($LANG) && $LANG === '_pt')
    {
      return '_pt';
    }

    return '';
  }

  public static function localizedField(array $row, $base)
  {
    $suffix = self::langSuffix();

    if ($suffix !== '' && !empty($row[$base . $suffix]))
    {
      return (string) $row[$base . $suffix];
    }

    return isset($row[$base]) ? (string) $row[$base] : '';
  }

  public static function isValidStarterCode($code)
  {
    return in_array((string) $code, self::STARTER_CODES, true);
  }

  public static function fetchById($conn, $id_player_class)
  {
    $id_player_class = (int) $id_player_class;

    if ($id_player_class <= 0)
    {
      return null;
    }

    $stmt = $conn->prepare('
      SELECT id_player_class, code, name, name_it, name_pt,
             parent_id_player_class, unlock_level, starter_branch
      FROM player_classes
      WHERE id_player_class = :id
      LIMIT 1
    ');
    $stmt->execute([':id' => $id_player_class]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  public static function fetchByCode($conn, $code)
  {
    $code = (string) $code;

    if ($code === '')
    {
      return null;
    }

    $stmt = $conn->prepare('
      SELECT id_player_class, code, name, name_it, name_pt,
             parent_id_player_class, unlock_level, starter_branch
      FROM player_classes
      WHERE code = :code
      LIMIT 1
    ');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  public static function displayName(array $row)
  {
    return self::localizedField($row, 'name');
  }

  /**
   * @return array<string, mixed>|null
   */
  public static function fetchForUser($conn, $id_user_ig)
  {
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
      return null;
    }

    $stmt = $conn->prepare('
      SELECT PC.id_player_class, PC.code, PC.name, PC.name_it, PC.name_pt,
             PC.parent_id_player_class, PC.unlock_level, PC.starter_branch
      FROM users_ig UI
      INNER JOIN player_classes PC ON PC.id_player_class = UI.id_player_class
      WHERE UI.id_user_ig = :id_user_ig
      LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
      return null;
    }

    $row['display_name'] = self::displayName($row);

    return $row;
  }

  public static function appendToProfile(array $profile, $conn, $id_user_ig)
  {
    $class = self::fetchForUser($conn, $id_user_ig);

    if (!$class)
    {
      $profile['id_player_class'] = isset($profile['id_player_class']) ? (int) $profile['id_player_class'] : 0;
      $profile['player_class_code'] = '';
      $profile['player_class_name'] = '';

      return $profile;
    }

    $profile['id_player_class'] = (int) $class['id_player_class'];
    $profile['player_class_code'] = (string) $class['code'];
    $profile['player_class_name'] = (string) $class['display_name'];

    return $profile;
  }

  public static function unlockAbilitiesForClass($conn, $id_user_ig, $id_player_class, $max_unlock_level = 1)
  {
    $id_user_ig = (int) $id_user_ig;
    $id_player_class = (int) $id_player_class;
    $max_unlock_level = max(1, (int) $max_unlock_level);

    if ($id_user_ig <= 0 || $id_player_class <= 0)
    {
      return;
    }

    $stmt = $conn->prepare('
      INSERT INTO user_player_class_abilities (id_user_ig, id_player_class_ability, flg_unlocked)
      SELECT :id_user_ig, PCA.id_player_class_ability, \'S\'
      FROM player_class_abilities PCA
      WHERE PCA.id_player_class = :id_player_class
        AND PCA.flg_active = \'S\'
        AND PCA.unlock_level <= :max_unlock_level
      ON DUPLICATE KEY UPDATE flg_unlocked = VALUES(flg_unlocked)
    ');
    $stmt->execute([
      ':id_user_ig' => $id_user_ig,
      ':id_player_class' => $id_player_class,
      ':max_unlock_level' => $max_unlock_level
    ]);
  }

  public static function unlockStarterAbilities($conn, $id_user_ig, $id_player_class)
  {
    self::unlockAbilitiesForClass($conn, $id_user_ig, $id_player_class, 1);
  }

  public static function assignClass($conn, $id_user_ig, $id_player_class, $max_unlock_level = 1)
  {
    $id_user_ig = (int) $id_user_ig;
    $id_player_class = (int) $id_player_class;

    if ($id_user_ig <= 0 || $id_player_class <= 0)
    {
      return false;
    }

    $class = self::fetchById($conn, $id_player_class);

    if (!$class)
    {
      return false;
    }

    $stmt = $conn->prepare('
      UPDATE users_ig
      SET id_player_class = :id_player_class,
          dt_modifica = NOW()
      WHERE id_user_ig = :id_user_ig
    ');
    $stmt->execute([
      ':id_player_class' => $id_player_class,
      ':id_user_ig' => $id_user_ig
    ]);

    if ($stmt->rowCount() < 1)
    {
      return false;
    }

    self::unlockAbilitiesForClass($conn, $id_user_ig, $id_player_class, $max_unlock_level);

    return true;
  }

  public static function assignStarterByCode($conn, $id_user_ig, $code)
  {
    if (!self::isValidStarterCode($code))
    {
      return false;
    }

    $class = self::fetchByCode($conn, $code);

    if (!$class)
    {
      return false;
    }

    return self::assignClass($conn, $id_user_ig, (int) $class['id_player_class']);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public static function fetchUserAbilities($conn, $id_user_ig)
  {
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
      return [];
    }

    $stmt = $conn->prepare('
      SELECT PCA.id_player_class_ability, PCA.id_player_class, PCA.code, PCA.name, PCA.name_it, PCA.name_pt,
             PCA.description, PCA.description_it, PCA.description_pt,
             PCA.use_context, PCA.cooldown_turns, PCA.cooldown_seconds,
             PCA.effect_json, PCA.unlock_level,
             CASE WHEN UPCA.flg_unlocked = \'S\' THEN \'S\' ELSE \'N\' END AS flg_unlocked
      FROM users_ig UI
      INNER JOIN player_class_abilities PCA ON PCA.flg_active = \'S\'
      LEFT JOIN user_player_class_abilities UPCA
        ON UPCA.id_player_class_ability = PCA.id_player_class_ability
       AND UPCA.id_user_ig = UI.id_user_ig
      WHERE UI.id_user_ig = :id_user_ig
        AND (
          UPCA.flg_unlocked = \'S\'
          OR PCA.id_player_class = UI.id_player_class
        )
      ORDER BY PCA.id_player_class ASC, PCA.use_context ASC, PCA.code ASC
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  public static function formatAbilityRow(array $row)
  {
    return [
      'id_player_class_ability' => (int) $row['id_player_class_ability'],
      'code' => (string) $row['code'],
      'name' => self::localizedField($row, 'name'),
      'description' => self::localizedField($row, 'description'),
      'use_context' => (string) $row['use_context'],
      'cooldown_turns' => (int) $row['cooldown_turns'],
      'cooldown_seconds' => (int) $row['cooldown_seconds'],
      'unlock_level' => (int) $row['unlock_level'],
      'flg_unlocked' => ((string) $row['flg_unlocked'] === 'S') ? 'S' : 'N'
    ];
  }

  /**
   * @param array<int, array{id_item_type:int, quantity:int}> $items
   */
  public static function consumeItems($conn, $id_user_ig, array $items)
  {
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
      return false;
    }

    foreach ($items as $item)
    {
      $id_item_type = (int) ($item['id_item_type'] ?? 0);
      $quantity = (int) ($item['quantity'] ?? 0);

      if ($id_item_type <= 0 || $quantity <= 0)
      {
        continue;
      }

      $stmt_count = $conn->prepare('
        SELECT COUNT(*) FROM items
        WHERE id_user_ig = :id_user_ig
          AND id_item_type = :id_item_type
          AND dt_used IS NULL
      ');
      $stmt_count->execute([
        ':id_user_ig' => $id_user_ig,
        ':id_item_type' => $id_item_type
      ]);

      if ((int) $stmt_count->fetchColumn() < $quantity)
      {
        return false;
      }

      $stmt_ids = $conn->prepare('
        SELECT id_item FROM items
        WHERE id_user_ig = :id_user_ig
          AND id_item_type = :id_item_type
          AND dt_used IS NULL
        ORDER BY id_item ASC
        LIMIT ' . (int) $quantity . '
      ');
      $stmt_ids->execute([
        ':id_user_ig' => $id_user_ig,
        ':id_item_type' => $id_item_type
      ]);
      $ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);

      if (count($ids) < $quantity)
      {
        return false;
      }

      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $stmt_use = $conn->prepare('
        UPDATE items
        SET dt_used = NOW(), dt_modifica = NOW()
        WHERE id_item IN (' . $placeholders . ')
          AND dt_used IS NULL
      ');
      $stmt_use->execute($ids);

      if ($stmt_use->rowCount() < $quantity)
      {
        return false;
      }
    }

    return true;
  }

  /**
   * Promote to a tier-2/3 class (validates parent chain, level, optional item turn-in).
   *
   * @param array<string, mixed> $params
   */
  public static function promoteTo($conn, $id_user_ig, $target_id_player_class, $LANG, array $params = [])
  {
    $id_user_ig = (int) $id_user_ig;
    $target_id_player_class = (int) $target_id_player_class;

    if ($id_user_ig <= 0 || $target_id_player_class <= 0)
    {
      return false;
    }

    if (!class_exists('FUNZIONI'))
    {
      require_once __DIR__ . '/f.php';
    }

    $target = self::fetchById($conn, $target_id_player_class);

    if (!$target)
    {
      return false;
    }

    $stmt_user = $conn->prepare('
      SELECT id_player_class, level
      FROM users_ig
      WHERE id_user_ig = :id_user_ig
      LIMIT 1
    ');
    $stmt_user->execute([':id_user_ig' => $id_user_ig]);
    $user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_row)
    {
      return false;
    }

    $current_id = (int) $user_row['id_player_class'];
    $user_level = (int) $user_row['level'];
    $required_parent = (int) ($target['parent_id_player_class'] ?? 0);
    $unlock_level = (int) ($target['unlock_level'] ?? 1);

    if ($required_parent <= 0 || $current_id !== $required_parent)
    {
      return false;
    }

    if ($user_level < $unlock_level)
    {
      return false;
    }

    if ($current_id === $target_id_player_class)
    {
      return false;
    }

    $consume_items = [];

    if (!empty($params['consume_items']) && is_array($params['consume_items']))
    {
      $consume_items = $params['consume_items'];
    }

    try
    {
      $conn->beginTransaction();

      if ($consume_items && !self::consumeItems($conn, $id_user_ig, $consume_items))
      {
        $conn->rollBack();
        return false;
      }

      $stmt_update = $conn->prepare('
        UPDATE users_ig
        SET id_player_class = :id_player_class,
            dt_modifica = NOW()
        WHERE id_user_ig = :id_user_ig
      ');
      $stmt_update->execute([
        ':id_player_class' => $target_id_player_class,
        ':id_user_ig' => $id_user_ig
      ]);

      if ($stmt_update->rowCount() < 1)
      {
        $conn->rollBack();
        return false;
      }

      self::unlockAbilitiesForClass($conn, $id_user_ig, $target_id_player_class, $unlock_level);

      $class_name = self::displayName($target);
      $notification = 'You specialized as ' . $class_name . '.';
      $notification_type = 'class_promotion';

      if ($LANG === '_it')
      {
        $notification = 'Ti sei specializzato come ' . $class_name . '.';
      }
      elseif ($LANG === '_pt')
      {
        $notification = 'Especializaste-te como ' . $class_name . '.';
      }

      FUNZIONI::AddNotification($conn, $id_user_ig, $notification, $notification_type);

      $conn->commit();

      return true;
    }
    catch (Exception $e)
    {
      if ($conn->inTransaction())
      {
        $conn->rollBack();
      }

      error_log('[PLAYER_CLASS] promoteTo failed: ' . $e->getMessage());

      return false;
    }
  }
}
