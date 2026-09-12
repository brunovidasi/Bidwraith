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

        if ($isNew) {
            chmod($dbPath, 0640);
        }
    }

    return $db;
}
