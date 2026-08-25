<?php
// cfspeed shared dish resolution — www/dishlib.php
// Included by the API endpoints; mirrors dishes.py so PHP and Python agree on
// which dishes exist and what their ids are.
if (!defined('CFSPEED_BASE')) {
    // Direct request: this file is a library, not an endpoint.
    http_response_code(404);
    exit;
}

const CFSPEED_DEFAULT_DISH = 'default';
const CFSPEED_MAX_DISHES = 8;

/**
 * Configured dishes as [['id','name'], ...].
 *
 * A config with no "dishes" list yields the single default dish, so an install
 * that predates multi-dish support keeps working and its NULL-tagged rows keep
 * belonging to it.
 */
function cfspeed_dishes(array $cfg): array {
    $raw = $cfg['dishes'] ?? null;
    if (!is_array($raw) || !$raw) {
        $legacy = $cfg['dish'] ?? [];
        return [['id' => CFSPEED_DEFAULT_DISH,
                 'name' => (string) ($legacy['name'] ?? 'Dish')]];
    }
    $out = [];
    $seen = [];
    foreach (array_slice($raw, 0, CFSPEED_MAX_DISHES) as $i => $d) {
        if (!is_array($d)) continue;
        $id = strtolower(str_replace(' ', '-', trim((string) ($d['id'] ?? ''))));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $id)) $id = 'dish' . ($i + 1);
        while (isset($seen[$id])) $id .= '-' . ($i + 1);
        $seen[$id] = true;
        $out[] = ['id' => $id,
                  'name' => substr((string) ($d['name'] ?? ucfirst($id)), 0, 40)];
    }
    return $out ?: [['id' => CFSPEED_DEFAULT_DISH, 'name' => 'Dish']];
}

/** The dish a request is asking about; falls back to the first configured. */
function cfspeed_pick_dish(array $list, ?string $want): string {
    $want = trim((string) $want);
    foreach ($list as $d) if ($d['id'] === $want) return $d['id'];
    return $list[0]['id'] ?? CFSPEED_DEFAULT_DISH;
}

/**
 * SQL fragment restricting a query to one dish.
 *
 * Rows written before multi-dish support carry a NULL dish_id and belong to
 * the default dish, so that case has to be matched explicitly rather than
 * silently dropping all the history on upgrade.
 */
function cfspeed_dish_where(string $alias, string $dishId, bool $hasColumn): string {
    if (!$hasColumn) return '';
    $a = $alias === '' ? '' : $alias . '.';
    $q = "'" . SQLite3::escapeString($dishId) . "'";
    if ($dishId === CFSPEED_DEFAULT_DISH)
        return " AND ({$a}dish_id = $q OR {$a}dish_id IS NULL)";
    return " AND {$a}dish_id = $q";
}

/** Does this table have a dish_id column yet? */
function cfspeed_has_dish_col(SQLite3 $db, string $table): bool {
    $r = $db->query("PRAGMA table_info(" . preg_replace('/[^a-z_]/', '', $table) . ")");
    while ($c = $r->fetchArray(SQLITE3_ASSOC)) if ($c['name'] === 'dish_id') return true;
    return false;
}
