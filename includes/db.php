<?php

function db(): PDO
{
    static $db = null;

    if ($db === null) {
        $dbPath = __DIR__ . '/../data/app.sqlite';
        $isNew = !file_exists($dbPath);

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA foreign_keys = ON');

        $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
        $db->exec($schema);
        run_migrations($db);

        if ($isNew) {
            chmod($dbPath, 0640);
        }
    }

    return $db;
}

/**
 * Adds columns introduced after a table's initial CREATE TABLE, for databases that
 * already existed before that column was added. schema.sql alone can't do this since
 * CREATE TABLE IF NOT EXISTS is a no-op once the table exists.
 */
function run_migrations(PDO $db): void
{
    $columns = [
        'watched_auctions' => ['current_price' => 'REAL', 'shipping_cost' => 'REAL', 'item_country' => 'TEXT', 'price_checked_at' => 'TEXT'],
    ];

    foreach ($columns as $table => $cols) {
        $existing = array_column($db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($cols as $name => $type) {
            if (!in_array($name, $existing, true)) {
                $db->exec("ALTER TABLE $table ADD COLUMN $name $type");
            }
        }
    }
}
