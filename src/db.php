<?php
// Stelle sicher, dass der Ordner existiert
$db_dir = __DIR__ . '/database';
if (!is_dir($db_dir)) {
    @mkdir($db_dir, 0777, true);
}

// Speicherort in den Unterordner verlegen
$db_file = $db_dir . '/cubelite.sqlite';
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS settings (
    user_email TEXT PRIMARY KEY,
    per_page INTEGER,
    signature TEXT,
    archive_folder TEXT,
    language TEXT,
    hidden_folders TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_email TEXT,
    name TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    notes TEXT,
    avatar TEXT,
    gender TEXT,
    birthday TEXT,
    website TEXT,
    im_address TEXT,
    group_id INTEGER DEFAULT 1
)");

try {
    $db->exec("ALTER TABLE contacts ADD COLUMN group_id INTEGER DEFAULT 1");
} catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS contact_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_email TEXT,
    name TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS identities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_email TEXT,
    display_name TEXT,
    email TEXT,
    organization TEXT,
    reply_to TEXT,
    bcc TEXT,
    signature TEXT,
    is_html_sig INTEGER DEFAULT 0,
    is_default INTEGER DEFAULT 0
)");

$db->exec("CREATE TABLE IF NOT EXISTS quick_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_email TEXT,
    name TEXT,
    body TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS cache_folders (
    user_email TEXT,
    folder_raw TEXT,
    UNIQUE(user_email, folder_raw)
)");
?>