<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cubelite_debug.log');

set_exception_handler(function($e) {
    error_log("CRITICAL EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " Zeile " . $e->getLine());
    die("<div style='padding:20px; font-family:Arial; color:red;'><b>Ein schwerwiegender Fehler ist aufgetreten.</b><br>Die genaue Ursache wurde in die Datei <i>cubelite_debug.log</i> geschrieben. Bitte öffne diese Datei, um den Fehler zu sehen.</div>");
});

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['user']);
    $pass = $_POST['pass'];
    
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $providers = [
        'gmail.com' => ['imap.gmail.com', 'smtp.gmail.com'],
        'googlemail.com' => ['imap.gmail.com', 'smtp.gmail.com'],
        'outlook.com' => ['imap-mail.outlook.com', 'smtp-mail.outlook.com'],
        'hotmail.com' => ['imap-mail.outlook.com', 'smtp-mail.outlook.com'],
        'live.com' => ['imap-mail.outlook.com', 'smtp-mail.outlook.com'],
        'yahoo.com' => ['imap.mail.yahoo.com', 'smtp.mail.yahoo.com'],
        'yahoo.de' => ['imap.mail.yahoo.com', 'smtp.mail.yahoo.com'],
        'gmx.de' => ['imap.gmx.net', 'mail.gmx.net'],
        'gmx.net' => ['imap.gmx.net', 'mail.gmx.net'],
        'web.de' => ['imap.web.de', 'smtp.web.de'],
        'icloud.com' => ['imap.mail.me.com', 'smtp.mail.me.com'],
        'me.com' => ['imap.mail.me.com', 'smtp.mail.me.com'],
        'mac.com' => ['imap.mail.me.com', 'smtp.mail.me.com']
    ];
    
    $imap_host = isset($providers[$domain]) ? $providers[$domain][0] : 'imap.' . $domain;
    $smtp_host = isset($providers[$domain]) ? $providers[$domain][1] : 'smtp.' . $domain;
    
    $_SESSION['imap_host'] = $imap_host;
    $_SESSION['smtp_host'] = $smtp_host;
    $_SESSION['imap_user'] = $email;
    $_SESSION['imap_pass'] = $pass;
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['imap_user'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>CubeLite Login</title>
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23006bb3'><path d='M12 2L2 7l10 5 10-5-10-5zm0 11.5l-10-5v10l10 5 10-5v-10l-10 5z'/></svg>">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="login-body">
        <div class="login-box">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="#006bb3"><path d="M12 2L2 7l10 5 10-5-10-5zm0 11.5l-10-5v10l10 5 10-5v-10l-10 5z"/></svg>
                CubeLite
            </h2>
            <form method="POST">
                <input type="email" name="user" required placeholder="Email / E-Mail">
                <input type="password" name="pass" required placeholder="Password / Passwort">
                <button type="submit" name="login">Login / Anmelden</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'db.php';

$imap_host = $_SESSION['imap_host'];
$imap_user = $_SESSION['imap_user'];
$imap_pass = $_SESSION['imap_pass'];

$stmt = $db->prepare("SELECT * FROM settings WHERE user_email = ?");
$stmt->execute([$imap_user]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $stmt = $db->prepare("INSERT INTO settings (user_email, per_page, signature, archive_folder, language) VALUES (?, 25, '', '', 'de')");
    $stmt->execute([$imap_user]);
    $settings = ['per_page' => 25, 'signature' => '', 'archive_folder' => '', 'language' => 'de', 'hidden_folders' => '[]'];
}

$per_page = (int)$settings['per_page'];
if ($per_page < 1) {
    $per_page = 25;
    $db->prepare("UPDATE settings SET per_page = 25 WHERE user_email = ?")->execute([$imap_user]);
}

$user_lang = isset($settings['language']) && $settings['language'] == 'en' ? 'en' : 'de';

$hidden_folders = [];
if (!empty($settings['hidden_folders'])) {
    $decoded_hidden = json_decode($settings['hidden_folders'], true);
    if (is_array($decoded_hidden)) {
        $hidden_folders = $decoded_hidden;
    }
}

$i18n = [
    'de' => [
        'logout' => 'Abmelden', 'mail' => 'E-Mail', 'contacts' => 'Kontakte', 'settings' => 'Einstellungen',
        'refresh' => 'Aktualisieren', 'compose' => 'Schreiben', 'reply' => 'Antworten', 'reply_all' => 'Allen antwo...',
        'forward' => 'Weiterleiten', 'delete' => 'Löschen', 'archive' => 'Archivieren', 'spam' => 'Spam',
        'mark' => 'Markieren', 'more' => 'Mehr', 'mark_read' => 'Als gelesen', 'mark_unread' => 'Als ungelesen',
        'mark_flagged' => 'Mit Stern markieren', 'print' => 'Drucken', 'export' => 'Exportieren',
        'source' => 'Quelltext anzeigen', 'move_to' => 'Verschieben nach ...', 'search_all' => 'Alle',
        'search_subj' => 'Betreff', 'search_from' => 'Von', 'search_to' => 'An', 'search_placeholder' => 'Suche...',
        'labels' => 'Labels', 'col_from' => 'Von', 'col_subj' => 'Betreff', 'col_date' => 'Datum', 'col_size' => 'Größe',
        'empty_folder' => 'Keine Nachrichten in diesem Ordner.', 'selection' => 'Auswahl...', 
        'sel_all' => 'Alle', 'sel_none' => 'Keine', 'sel_read' => 'Gelesene', 'sel_unread' => 'Ungelesene',
        'msg_count' => 'Nachrichten %d bis %d von %d', 'no_msg_sel' => 'Keine Nachricht ausgewählt',
        'comp_title' => 'Neue Nachricht verfassen', 'comp_send' => 'Senden', 'comp_save' => 'Speichern',
        'comp_attach' => 'Anhängen', 'comp_cancel' => 'Abbrechen', 'comp_to' => 'An:', 'comp_subj' => 'Betreff:',
        'set_archive' => 'Archiv-Ordner', 'set_auto' => '-- Automatisch erkennen --', 'set_per_page' => 'E-Mails pro Seite',
        'set_sig' => 'Signatur', 'set_lang' => 'Sprache / Language', 'set_cache' => '⟳ Ordner-Cache erzwingen', 'set_save' => 'Speichern',
        'js_del_multi' => 'Ausgewählte Nachrichten löschen?', 'js_del_single' => 'Löschen?',
        'js_sel_req' => 'Bitte wählen Sie etwas aus.', 'js_no_archive' => 'Bitte legen Sie den Archiv-Ordner in Einstellungen fest.',
        'js_no_spam' => 'Spam-Ordner nicht gefunden.', 'js_files' => ' Datei(en) ausgewählt', 'date_today' => 'Heute',
        'fwd_header' => '<br><br><hr><b>Weitergeleitete Nachricht:</b><br>Von: %s &lt;%s&gt;<br>Datum: %s<br>Betreff: %s<br>An: %s<br><br>',
        'reply_header' => '<br><br><hr>Am %s schrieb %s &lt;%s&gt;:<br><br>',
        'folder_inbox' => 'Posteingang', 'folder_drafts' => 'Entwürfe', 'folder_sent' => 'Gesendet', 'folder_spam' => 'Spam', 'folder_trash' => 'Papierkorb',
        'view_layout' => 'Layout', 'view_cols' => 'Spalten', 'view_sort_by' => 'Sortieren nach', 'view_sort_order' => 'Sortierung',
        'lay_wide' => 'Breitbildschirm', 'lay_desk' => 'Schreibtisch', 'lay_list' => 'Liste',
        'col_conv' => 'Konversationen', 'col_from_to' => 'Von/An', 'col_reply_to' => 'Antwort an', 'col_cc' => 'Kopie', 'col_read' => 'Gelesen/Ungelesen', 'col_attach' => 'Anhang', 'col_flag' => 'Markierung', 'col_prio' => 'Priorität',
        'sort_none' => 'Keine', 'sort_recv' => 'Empfangsdatum', 'sort_sent' => 'Sendedatum', 
        'ord_asc' => 'aufsteigend', 'ord_desc' => 'absteigend', 'btn_save' => 'Speichern', 'btn_cancel' => 'Abbrechen',
        
        'contact_title' => 'Adressbuch', 'contact_name' => 'Name', 'contact_email' => 'E-Mail', 'contact_phone' => 'Telefon', 'contact_address' => 'Adresse', 'contact_import' => 'Importieren', 'contact_no_data' => 'Keine Kontakte vorhanden.', 'contact_add' => 'Neuer Kontakt', 'contact_no_email' => 'Fehlt', 'contact_groups' => 'Gruppen', 'contact_personal' => 'Persönliches Adressbuch', 'contact_edit' => 'Kontakt bearbeiten', 'contact_props' => 'Eigenschaften',
        
        'set_menu_title' => 'Einstellungen', 'set_cat_settings' => 'Einstellungen', 'set_cat_folders' => 'Ordner', 'set_cat_identities' => 'Identitäten', 'set_cat_responses' => 'Schnellantworten', 'set_cat_userinfo' => 'Benutzerinformation',
        'set_area_ui' => 'Benutzeroberfläche', 'set_area_mailbox' => 'Postfachansicht', 'set_area_compose' => 'Nachrichtenerstellung', 'set_area_server' => 'Servereinstellungen',
        'set_ident_edit' => 'Identität bearbeiten', 'set_ident_name' => 'Angezeigter Name', 'set_ident_org' => 'Organisation', 'set_ident_replyto' => 'Antwort an', 'set_ident_bcc' => 'Blindkopie', 'set_ident_default' => 'Als Standard', 'set_ident_sig' => 'Signatur', 'set_ident_html' => 'HTML-Signatur',
        'set_resp_edit' => 'Antwort hinzufügen', 'set_resp_name' => 'Name', 'set_resp_text' => 'Text der Antwort', 'set_user_info' => 'Info für', 'set_user_name' => 'Benutzername', 'set_user_server' => 'Server',
        'dialog_ok' => 'OK', 'dialog_cancel' => 'Abbrechen', 'dialog_info' => 'Hinweis', 'dialog_confirm' => 'Bestätigung'
    ],
    'en' => [
        'logout' => 'Logout', 'mail' => 'Mail', 'contacts' => 'Contacts', 'settings' => 'Settings',
        'refresh' => 'Refresh', 'compose' => 'Compose', 'reply' => 'Reply', 'reply_all' => 'Reply all',
        'forward' => 'Forward', 'delete' => 'Delete', 'archive' => 'Archive', 'spam' => 'Spam',
        'mark' => 'Mark', 'more' => 'More', 'mark_read' => 'As read', 'mark_unread' => 'As unread',
        'mark_flagged' => 'Add star', 'print' => 'Print', 'export' => 'Export',
        'source' => 'Show source', 'move_to' => 'Move to...', 'search_all' => 'All',
        'search_subj' => 'Subject', 'search_from' => 'From', 'search_to' => 'To', 'search_placeholder' => 'Search...',
        'labels' => 'Labels', 'col_from' => 'From', 'col_subj' => 'Subject', 'col_date' => 'Date', 'col_size' => 'Size',
        'empty_folder' => 'No messages in this folder.', 'selection' => 'Select...', 
        'sel_all' => 'All', 'sel_none' => 'None', 'sel_read' => 'Read', 'sel_unread' => 'Unread',
        'msg_count' => 'Messages %d to %d of %d', 'no_msg_sel' => 'No message selected',
        'comp_title' => 'Compose new message', 'comp_send' => 'Send', 'comp_save' => 'Save',
        'comp_attach' => 'Attach', 'comp_cancel' => 'Cancel', 'comp_to' => 'To:', 'comp_subj' => 'Subject:',
        'set_archive' => 'Archive Folder', 'set_auto' => '-- Auto detect --', 'set_per_page' => 'Emails per page',
        'set_sig' => 'Signature', 'set_lang' => 'Language / Sprache', 'set_cache' => '⟳ Force folder cache refresh', 'set_save' => 'Save',
        'js_del_multi' => 'Delete selected messages?', 'js_del_single' => 'Delete?',
        'js_sel_req' => 'Please select something.', 'js_no_archive' => 'Please set the archive folder in the settings.',
        'js_no_spam' => 'Spam folder not found.', 'js_files' => ' file(s) selected', 'date_today' => 'Today',
        'fwd_header' => '<br><br><hr><b>Forwarded message:</b><br>From: %s &lt;%s&gt;<br>Date: %s<br>Subject: %s<br>To: %s<br><br>',
        'reply_header' => '<br><br><hr>On %s, %s &lt;%s&gt; wrote:<br><br>',
        'folder_inbox' => 'Inbox', 'folder_drafts' => 'Drafts', 'folder_sent' => 'Sent', 'folder_spam' => 'Spam', 'folder_trash' => 'Trash',
        'view_layout' => 'Layout', 'view_cols' => 'Columns', 'view_sort_by' => 'Sort by', 'view_sort_order' => 'Sorting',
        'lay_wide' => 'Widescreen', 'lay_desk' => 'Desktop', 'lay_list' => 'List',
        'col_conv' => 'Conversations', 'col_from_to' => 'From/To', 'col_reply_to' => 'Reply to', 'col_cc' => 'Copy', 'col_read' => 'Read/Unread', 'col_attach' => 'Attachment', 'col_flag' => 'Flag', 'col_prio' => 'Priority',
        'sort_none' => 'None', 'sort_recv' => 'Receive date', 'sort_sent' => 'Sent date', 
        'ord_asc' => 'Ascending', 'ord_desc' => 'Descending', 'btn_save' => 'Save', 'btn_cancel' => 'Cancel',
        
        'contact_title' => 'Address Book', 'contact_name' => 'Name', 'contact_email' => 'Email', 'contact_phone' => 'Phone', 'contact_address' => 'Address', 'contact_import' => 'Import', 'contact_no_data' => 'No contacts available.', 'contact_add' => 'Add Contact', 'contact_no_email' => 'Missing', 'contact_groups' => 'Groups', 'contact_personal' => 'Personal Address Book', 'contact_edit' => 'Edit contact', 'contact_props' => 'Properties',
        
        'set_menu_title' => 'Settings', 'set_cat_settings' => 'Preferences', 'set_cat_folders' => 'Folders', 'set_cat_identities' => 'Identities', 'set_cat_responses' => 'Responses', 'set_cat_userinfo' => 'User info',
        'set_area_ui' => 'User Interface', 'set_area_mailbox' => 'Mailbox View', 'set_area_compose' => 'Composing Messages', 'set_area_server' => 'Server Settings',
        'set_ident_edit' => 'Edit identity', 'set_ident_name' => 'Display Name', 'set_ident_org' => 'Organization', 'set_ident_replyto' => 'Reply-to', 'set_ident_bcc' => 'Bcc', 'set_ident_default' => 'Set default', 'set_ident_sig' => 'Signature', 'set_ident_html' => 'HTML signature',
        'set_resp_edit' => 'Add response', 'set_resp_name' => 'Name', 'set_resp_text' => 'Response text', 'set_user_info' => 'Info for', 'set_user_name' => 'Username', 'set_user_server' => 'Server',
        'dialog_ok' => 'OK', 'dialog_cancel' => 'Cancel', 'dialog_info' => 'Information', 'dialog_confirm' => 'Confirmation'
    ]
];

$l = $i18n[$user_lang];

function decode_imap_utf7($str) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', 'UTF7-IMAP');
    }
    return imap_utf7_decode($str);
}

$sort_cookie = isset($_COOKIE['rc_sort']) ? $_COOKIE['rc_sort'] : 'recv';
$dir_cookie  = isset($_COOKIE['rc_dir']) ? $_COOKIE['rc_dir'] : 'DESC';

$sort_map = [
    'date' => SORTDATE,
    'recv' => SORTARRIVAL,
    'sent' => SORTDATE,
    'from' => SORTFROM,
    'subj' => SORTSUBJECT,
    'size' => SORTSIZE,
    'none' => SORTARRIVAL
];
$sort_criteria = isset($sort_map[$sort_cookie]) ? $sort_map[$sort_cookie] : SORTARRIVAL;
$reverse = ($dir_cookie === 'ASC') ? 0 : 1;

$current_folder = isset($_GET['folder']) ? $_GET['folder'] : 'INBOX';
$search_query   = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_scope   = isset($_GET['scope']) ? $_GET['scope'] : 'ALL';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$emails = array();
$folders_raw = array();
$total_msgs = 0;

$server_base = "{" . $imap_host . ":993/imap/ssl}";
$server = $server_base . $current_folder;

$inbox = @imap_open($server, $imap_user, $imap_pass);

if ($inbox) {
    $stmt = $db->prepare("SELECT folder_raw FROM cache_folders WHERE user_email = ?");
    $stmt->execute([$imap_user]);
    $folders_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($folders_raw)) {
        $mailboxes = imap_getmailboxes($inbox, $server_base, "*");
        if ($mailboxes) {
            $db->beginTransaction();
            $insertStmt = $db->prepare("INSERT OR IGNORE INTO cache_folders (user_email, folder_raw) VALUES (?, ?)");
            foreach ($mailboxes as $box) {
                $raw = str_replace($server_base, "", $box->name);
                $folders_raw[] = $raw;
                $insertStmt->execute([$imap_user, $raw]);
            }
            $db->commit();
        }
    }

    $active_inbox = $inbox;
    $target_folder = $current_folder;
    $search_inbox_conn = null;

    if (!empty($search_query)) {
        foreach ($folders_raw as $raw) {
            $dec = decode_imap_utf7($raw);
            if (stripos($dec, 'Alle Nachrichten') !== false || stripos($dec, 'All Mail') !== false || stripos($dec, 'Alle E-Mails') !== false) {
                $target_folder = $raw; 
                break;
            }
        }
        if ($target_folder !== $current_folder) {
            $search_inbox_conn = @imap_open($server_base . $target_folder, $imap_user, $imap_pass);
            if ($search_inbox_conn) {
                $active_inbox = $search_inbox_conn;
            } else {
                $target_folder = $current_folder;
            }
        }

        $q = addslashes($search_query);
        if ($search_scope === 'SUBJECT') $search_criteria = 'SUBJECT "' . $q . '"';
        elseif ($search_scope === 'FROM') $search_criteria = 'FROM "' . $q . '"';
        elseif ($search_scope === 'TO') $search_criteria = 'TO "' . $q . '"';
        else $search_criteria = 'TEXT "' . $q . '"';

        $msg_ids = @imap_sort($active_inbox, $sort_criteria, $reverse, 0, $search_criteria, "UTF-8");
        if ($msg_ids === false) {
            $msg_ids = @imap_sort($active_inbox, $sort_criteria, $reverse, 0, $search_criteria);
        }
        if ($msg_ids === false) {
            $msg_ids = [];
        }
        
        $total_msgs = count($msg_ids);
        $offset = ($page - 1) * $per_page;
        $slice_ids = array_slice($msg_ids, $offset, $per_page);
    } else {
        $msg_ids = @imap_sort($active_inbox, $sort_criteria, $reverse, 0);
        if ($msg_ids === false) {
            $msg_ids = [];
        }
        
        $total_msgs = count($msg_ids);
        $offset = ($page - 1) * $per_page;
        $slice_ids = array_slice($msg_ids, $offset, $per_page);
    }
    
    if ($total_msgs > 0) {
        $sequence = !empty($slice_ids) ? implode(',', $slice_ids) : "";
        if (!empty($sequence)) {
            $overview = imap_fetch_overview($active_inbox, $sequence, 0);
            if ($overview) {
                $order_map = array_flip($slice_ids);
                usort($overview, function($a, $b) use ($order_map) {
                    return $order_map[$a->msgno] <=> $order_map[$b->msgno];
                });
                
                foreach ($overview as $head) {
                    $from = 'Unbekannt';
                    if (isset($head->from)) {
                        $addr = imap_rfc822_parse_adrlist($head->from, "localhost");
                        if (!empty($addr[0])) {
                            $from = !empty($addr[0]->personal) ? imap_utf8($addr[0]->personal) : $addr[0]->mailbox . '@' . $addr[0]->host;
                        }
                    }
                    $subj = !empty($head->subject) ? imap_utf8($head->subject) : '(kein Betreff)';
                    
                    $date_time = strtotime($head->date);
                    if (date('Y-m-d', $date_time) == date('Y-m-d')) {
                        $date = $l['date_today'] . " " . date("H:i", $date_time);
                    } else {
                        $date = date("d.m.Y H:i", $date_time);
                    }

                    $emails[] = [
                        'id' => $head->msgno,
                        'folder' => $target_folder,
                        'from' => $from,
                        'subj' => $subj,
                        'date' => $date,
                        'size' => isset($head->size) ? round($head->size / 1024) . ' KB' : '',
                        'unread' => empty($head->seen),
                        'flagged' => !empty($head->flagged),
                        'attached' => !empty($head->recent)
                    ];
                }
            }
        }
    }
    
    if ($search_inbox_conn) {
        imap_close($search_inbox_conn);
    }
    imap_close($inbox);
}

$gmail_order = [
    'INBOX' => ['name' => $l['folder_inbox'], 'icon' => 'inbox'],
    '[Gmail]/Entwürfe' => ['name' => $l['folder_drafts'], 'icon' => 'drafts'],
    '[Gmail]/Gesendet' => ['name' => $l['folder_sent'], 'icon' => 'send'],
    '[Gmail]/Spam' => ['name' => $l['folder_spam'], 'icon' => 'report'],
    '[Gmail]/Papierkorb' => ['name' => $l['folder_trash'], 'icon' => 'delete']
];

$top_folders = [];
$decoded_folders = [];
$visible_decoded_folders = [];
$sys_spam_folder = '';
$sys_archive_folder_auto = '';

foreach ($folders_raw as $raw) {
    $dec = decode_imap_utf7($raw);
    $decoded_folders[$raw] = $dec;
    
    if (!in_array($raw, $hidden_folders)) {
        $visible_decoded_folders[$raw] = $dec;
    }
    
    if (stripos($dec, 'Spam') !== false) {
        $sys_spam_folder = urlencode($raw);
    }
    if (stripos($dec, 'Alle Nachrichten') !== false || stripos($dec, 'All Mail') !== false || stripos($dec, 'Alle E-Mails') !== false || stripos($dec, 'Archive') !== false) {
        $sys_archive_folder_auto = urlencode($raw);
    }
}

foreach ($gmail_order as $key => $data) {
    if (in_array($key, $hidden_folders)) continue;
    
    $found_raw = null;
    foreach ($visible_decoded_folders as $raw => $dec) {
        if ($dec === $key || $raw === $key) { 
            $found_raw = $raw; 
            break; 
        }
    }
    if ($found_raw !== null) {
        $top_folders[$found_raw] = $data;
    }
}

$folder_tree = [];
$delim = '/';
foreach ($visible_decoded_folders as $dec) {
    if (strpos($dec, '/') !== false) { $delim = '/'; break; }
    if (strpos($dec, '.') !== false) { $delim = '.'; break; }
}

foreach ($visible_decoded_folders as $raw => $dec) {
    $parts = explode($delim, $dec);
    $current = &$folder_tree;
    foreach ($parts as $i => $part) {
        if (!isset($current[$part])) {
            $current[$part] = ['raw' => '', 'children' => []];
        }
        if ($i == count($parts) - 1) {
            $current[$part]['raw'] = $raw;
        }
        $current = &$current[$part]['children'];
    }
}

function renderFolderTree($tree, $current_folder, $level = 0) {
    $html = '<ul class="' . ($level === 0 ? 'tree-root' : 'tree-nested') . '">';
    foreach ($tree as $name => $node) {
        $has_children = !empty($node['children']);
        $is_active = ($node['raw'] === $current_folder) ? 'active' : '';
        $is_open = isNodeActive($node, $current_folder) ? 'open' : '';
        $pad = 10 + ($level * 15);
        $icon = $has_children ? 'folder' : 'folder_open';
        
        $html .= '<li class="' . $is_open . '">';
        $html .= '<div class="tree-row ' . $is_active . '" style="padding-left: ' . $pad . 'px;">';
        
        if ($has_children) {
            $arrow = $is_open ? 'arrow_drop_down' : 'arrow_right';
            $html .= '<span class="material-symbols-outlined tree-toggle" onclick="toggleFolder(this, event)">' . $arrow . '</span>';
        } else {
            $html .= '<span class="tree-toggle-placeholder" style="width:20px; display:inline-block;"></span>';
        }
        
        if ($node['raw']) { 
            $html .= '<a href="?folder=' . urlencode($node['raw']) . '" class="tree-link">'; 
        } else { 
            $html .= '<span class="tree-link" onclick="toggleFolder(this.previousElementSibling, event)">'; 
        }
        
        $html .= '<span class="material-symbols-outlined folder-icon">' . $icon . '</span>' . htmlspecialchars($name);
        $html .= ($node['raw']) ? '</a>' : '</span>';
        $html .= '</div>';
        
        if ($has_children) {
            $html .= renderFolderTree($node['children'], $current_folder, $level + 1);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function isNodeActive($node, $current_folder) {
    if ($node['raw'] === $current_folder) return true;
    foreach ($node['children'] as $child) {
        if (isNodeActive($child, $current_folder)) return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($user_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CubeLite Webmail</title>
    
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23006bb3'><path d='M12 2L2 7l10 5 10-5-10-5zm0 11.5l-10-5v10l10 5 10-5v-10l-10 5z'/></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
    
    <style>
        .preview-loader { position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); display:none; flex-direction:column; align-items:center; color:#777; z-index:3; font-weight:bold; }
        .spinner { border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--rc-blue); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin-bottom:10px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    
    <script>
        var l = <?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var currentImapUser = <?= json_encode($imap_user, JSON_HEX_TAG) ?>;
        var currentImapHost = <?= json_encode($imap_host, JSON_HEX_TAG) ?>;
        var currentFolderRaw = <?= json_encode($current_folder, JSON_HEX_TAG) ?>;
        
        var sysArchiveFolder = <?= json_encode(!empty($settings['archive_folder']) ? $settings['archive_folder'] : urldecode($sys_archive_folder_auto), JSON_HEX_TAG) ?>;
        var sysSpamFolder = <?= json_encode(urldecode($sys_spam_folder), JSON_HEX_TAG) ?>;
        var hiddenFolders = <?= json_encode($hidden_folders, JSON_HEX_TAG) ?>;
        var allFoldersMap = <?= json_encode($decoded_folders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        
        var userSettings = {
            language: <?= json_encode($user_lang, JSON_HEX_TAG) ?>,
            per_page: <?= json_encode($per_page, JSON_HEX_TAG) ?>,
            archive_folder: <?= json_encode($settings['archive_folder'], JSON_HEX_TAG) ?>
        };

        var currentLayout = localStorage.getItem('rc_split_layout') || 'bottom';
        var selectedMailId = null;
        var selectedMailFolder = null;
        
        var allContacts = [];
        var contactGroups = [];
        var activeContactId = null;
        var activeGroupId = null;
        var lastContactIndex = -1;

        document.addEventListener("DOMContentLoaded", function() {
            tinymce.init({
                selector: '#compose-editor',
                menubar: false,
                statusbar: false,
                plugins: 'lists link',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link',
                content_style: 'body { font-family: Arial, sans-serif; font-size: 13px; margin: 8px; }'
            });

            applyLayout(currentLayout);
            applyColumns();
            initResizers();
            initTableResizers();
            
            document.addEventListener('click', function(e) {
                if(!e.target.closest('.tb-btn') && !e.target.closest('.tb-dropdown-menu')) {
                    document.querySelectorAll('.tb-dropdown-menu').forEach(function(menu) {
                        menu.style.display = 'none';
                    });
                }
            });
            
            fetch('action.php?action=get_contacts')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var datalist = document.getElementById('contacts-datalist');
                if(datalist){
                    datalist.innerHTML = '';
                    data.forEach(function(c) {
                        if(c.email) {
                            var op = document.createElement('option');
                            op.value = c.name ? '"' + c.name + '" <' + c.email + '>' : c.email;
                            datalist.appendChild(op);
                        }
                    });
                }
            });

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'import_success'): ?>
                customAlert(<?= json_encode((int)$_GET['count'] . ' Kontakte importiert!') ?>);
                window.history.replaceState(null, null, window.location.pathname);
            <?php endif; ?>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'smtp_error'): ?>
                customAlert('SMTP Error. Server: ' + <?= json_encode(htmlspecialchars($_GET['host'] ?? '')) ?>);
                window.history.replaceState(null, null, window.location.pathname);
            <?php endif; ?>
        });

        // --- CUSTOM DIALOGS ---
        function customAlert(msg) {
            document.getElementById('custom-dialog-title').innerText = l.dialog_info;
            document.getElementById('custom-dialog-msg').innerHTML = msg;
            document.getElementById('custom-dialog-input-container').style.display = 'none';
            document.getElementById('custom-dialog-btn-no').style.display = 'none';
            document.getElementById('custom-dialog-btn-yes').onclick = function() {
                document.getElementById('custom-dialog-backdrop').style.display = 'none';
            };
            document.getElementById('custom-dialog-backdrop').style.display = 'flex';
        }

        function customConfirm(msg, onConfirm) {
            document.getElementById('custom-dialog-title').innerText = l.dialog_confirm;
            document.getElementById('custom-dialog-msg').innerHTML = msg;
            document.getElementById('custom-dialog-input-container').style.display = 'none';
            document.getElementById('custom-dialog-btn-no').style.display = 'block';
            
            document.getElementById('custom-dialog-btn-yes').onclick = function() {
                document.getElementById('custom-dialog-backdrop').style.display = 'none';
                if(onConfirm) onConfirm();
            };
            document.getElementById('custom-dialog-btn-no').onclick = function() {
                document.getElementById('custom-dialog-backdrop').style.display = 'none';
            };
            document.getElementById('custom-dialog-backdrop').style.display = 'flex';
        }
        
        function customPrompt(msg, onConfirm) {
            document.getElementById('custom-dialog-title').innerText = "Eingabe erforderlich";
            document.getElementById('custom-dialog-msg').innerHTML = msg;
            document.getElementById('custom-dialog-input-container').style.display = 'block';
            var input = document.getElementById('custom-dialog-input');
            input.value = '';
            
            document.getElementById('custom-dialog-btn-no').style.display = 'block';
            
            document.getElementById('custom-dialog-btn-yes').onclick = function() {
                document.getElementById('custom-dialog-backdrop').style.display = 'none';
                if(onConfirm) onConfirm(input.value);
            };
            document.getElementById('custom-dialog-btn-no').onclick = function() {
                document.getElementById('custom-dialog-backdrop').style.display = 'none';
            };
            document.getElementById('custom-dialog-backdrop').style.display = 'flex';
            input.focus();
        }

        function switchAppView(viewName) {
            document.querySelectorAll('.header-actions a').forEach(a => a.classList.remove('active-app'));
            document.getElementById('nav-btn-' + viewName).classList.add('active-app');

            ['mail', 'contacts', 'settings'].forEach(v => {
                document.getElementById('toolbar-' + v).style.display = (v === viewName) ? 'flex' : 'none';
                document.getElementById('view-' + v).style.display = (v === viewName) ? 'flex' : 'none';
            });

            if (viewName === 'contacts') {
                loadContactGroups();
            }
            if (viewName === 'settings') {
                openSettingsCategory('settings', document.getElementById('menu-cat-settings'));
            }
        }

        // --- KONTAKTE APP FUNKTIONEN (Mit Gruppen-Logik) ---
        function loadContactGroups() {
            fetch('action.php?action=get_contact_groups')
            .then(res => res.json())
            .then(data => {
                contactGroups = data;
                if(data.length > 0 && !activeGroupId) {
                    activeGroupId = data[0].id;
                }
                renderContactGroups();
                loadContacts();
            });
        }
        
        function renderContactGroups() {
            var ul = document.getElementById('contact-groups-list');
            ul.innerHTML = '';
            var firstGroupId = contactGroups.length > 0 ? contactGroups[0].id : null;
            
            contactGroups.forEach(g => {
                var li = document.createElement('li');
                li.className = (g.id == activeGroupId) ? 'active' : '';
                li.innerHTML = `<span class="material-symbols-outlined">person_book</span> ${g.name}`;
                li.onclick = function() {
                    activeGroupId = g.id;
                    
                    var hiddenGroupId = document.getElementById('import-group-id');
                    if(hiddenGroupId) hiddenGroupId.value = activeGroupId;

                    renderContactGroups();
                    renderContactsList();
                };
                ul.appendChild(li);
            });
            
            var btnDelGroup = document.getElementById('btn-del-group');
            if (btnDelGroup) {
                // Das erste Adressbuch darf nicht gelöscht werden
                if (activeGroupId && activeGroupId != firstGroupId) {
                    btnDelGroup.style.display = 'block';
                } else {
                    btnDelGroup.style.display = 'none';
                }
            }

            var hiddenGroupId = document.getElementById('import-group-id');
            if(hiddenGroupId && activeGroupId) {
                hiddenGroupId.value = activeGroupId;
            }
        }

        function loadContacts() {
            fetch('action.php?action=get_contacts')
            .then(res => res.json())
            .then(data => {
                allContacts = data;
                renderContactsList();
            });
        }
        
        function renderContactsList() {
            var list = document.getElementById('contacts-col-2-list');
            list.innerHTML = '';
            
            var filtered = allContacts.filter(c => c.group_id == activeGroupId);
            
            if(filtered.length === 0) {
                document.getElementById('contacts-col-3').innerHTML = `<div style="text-align:center; padding:50px; color:#aaa;"><span class="material-symbols-outlined" style="font-size:50px;">person_off</span><br><br>${l.contact_no_data}</div>`;
                document.getElementById('contact-select-all').checked = false;
                checkContactSelection();
                return;
            }

            filtered.forEach((c, index) => {
                var div = document.createElement('div');
                div.className = 'set-list-item contact-row' + (index === 0 ? ' active' : '');
                div.innerHTML = `<input type="checkbox" class="contact-cb" value="${c.id}" style="margin-right:8px; pointer-events:none;"><span class="material-symbols-outlined" style="font-size:16px; margin-right:8px; vertical-align:middle; color:#999;">person</span>${c.name || c.email}`;
                
                div.onclick = function(e) { 
                    handleContactRowClick(e, c, div, index, filtered); 
                };
                
                list.appendChild(div);
                if(index === 0) {
                    showContactDetails(c, div);
                }
            });
            
            document.getElementById('contact-select-all').checked = false;
            checkContactSelection();
        }

        function handleContactRowClick(e, c, div, index, filteredList) {
            var cb = div.querySelector('.contact-cb');
            var isCheckboxClick = e.target.tagName.toLowerCase() === 'input' && e.target.type === 'checkbox';

            if (e.ctrlKey || e.metaKey || isCheckboxClick) {
                cb.checked = !cb.checked;
                if (cb.checked) div.classList.add('active'); 
                else div.classList.remove('active');
                lastContactIndex = index;
            } else if (e.shiftKey && lastContactIndex !== -1) {
                var start = Math.min(lastContactIndex, index);
                var end = Math.max(lastContactIndex, index);
                var rows = document.querySelectorAll('.contact-row');
                for (var i = 0; i < rows.length; i++) {
                    var rcb = rows[i].querySelector('.contact-cb');
                    if (i >= start && i <= end) {
                        rcb.checked = true;
                        rows[i].classList.add('active');
                    } else if (!e.ctrlKey) {
                        rcb.checked = false;
                        rows[i].classList.remove('active');
                    }
                }
            } else {
                document.querySelectorAll('.contact-row').forEach(r => {
                    r.classList.remove('active');
                    r.querySelector('.contact-cb').checked = false;
                });
                cb.checked = true;
                div.classList.add('active');
                lastContactIndex = index;
            }
            
            var checkedBoxes = document.querySelectorAll('.contact-cb:checked');
            if (checkedBoxes.length <= 1) {
                var targetC = checkedBoxes.length === 1 ? allContacts.find(x => x.id == checkedBoxes[0].value) : c;
                showContactDetails(targetC, checkedBoxes.length === 1 ? checkedBoxes[0].closest('.contact-row') : div);
            } else {
                document.getElementById('contacts-col-3').innerHTML = `<div style="text-align:center; padding:50px; color:#aaa;"><span class="material-symbols-outlined" style="font-size:50px;">group</span><br><br>${checkedBoxes.length} Kontakte ausgewählt</div>`;
                activeContactId = null;
                document.getElementById('btn-comp-contact').classList.add('disabled');
            }
            
            document.getElementById('contact-select-all').checked = (checkedBoxes.length === filteredList.length);
            checkContactSelection();
        }

        function toggleAllContacts(cb) {
            document.querySelectorAll('.contact-cb').forEach(c => {
                c.checked = cb.checked;
                if(cb.checked) c.closest('.contact-row').classList.add('active');
                else c.closest('.contact-row').classList.remove('active');
            });
            checkContactSelection();
        }

        function checkContactSelection() {
            var checked = document.querySelectorAll('.contact-cb:checked');
            var btnDel = document.getElementById('btn-del-contact');
            if(checked.length > 0 || activeContactId) {
                btnDel.classList.remove('disabled');
            } else {
                btnDel.classList.add('disabled');
            }
        }

        function showContactDetails(c, element) {
            if(element) setActiveItem(element);
            activeContactId = c.id;
            
            var addr = {street:'', zip:'', city:'', country:'', region:''};
            if(c.address) { 
                try { addr = JSON.parse(c.address); } catch(e) { addr.street = c.address; } 
            }
            var addrHtml = [addr.street, (addr.zip + ' ' + addr.city).trim(), addr.region, addr.country].filter(Boolean).join('<br>');
            
            var avatarStyle = c.avatar ? `background-image:url(${c.avatar}); background-size:cover; background-position:center;` : '';
            var avatarIcon = c.avatar ? '' : '<span class="material-symbols-outlined" style="font-size:60px;">person</span>';
            var safeName = c.name || c.email;
            var safeEmail = c.email || l.contact_no_email;
            var groupName = contactGroups.find(g => g.id == c.group_id)?.name || 'Persönliches Adressbuch';
            
            document.getElementById('contacts-col-3').innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="display:flex;">
                        <div style="width:130px; text-align:center; margin-right:20px;">
                            <div class="contact-avatar" style="${avatarStyle}">${avatarIcon}</div>
                        </div>
                        <div style="padding-top:20px;">
                            <div style="color:#777; font-size:11px; margin-bottom:5px;">Adressbuch: ${groupName}</div>
                            <h2 style="margin:0; color:#333; font-size:24px;">${safeName}</h2>
                        </div>
                    </div>
                    <button class="btn-dark" onclick="contactEditMode(${c.id})">${l.contact_edit}</button>
                </div>
                
                <div class="contact-tabs" style="margin-top:20px;">
                    <div class="contact-tab active" onclick="switchContactTabV('props', this)">${l.contact_props}</div>
                    <div class="contact-tab" onclick="switchContactTabV('personal', this)">Persönliche Informationen</div>
                    <div class="contact-tab" onclick="switchContactTabV('notes', this)">Notizen</div>
                </div>
                
                <div id="cv-tab-props">
                    <div class="contact-field-group">
                        <div class="contact-field-title">E-Mail</div>
                        <div class="contact-field-row">
                            <div class="contact-field-label">Privat</div>
                            <div class="contact-field-value" style="color:${c.email ? 'var(--rc-blue)' : '#aaa'};">${safeEmail}</div>
                        </div>
                    </div>
                    ${c.phone ? `<div class="contact-field-group"><div class="contact-field-title">Telefon</div><div class="contact-field-row"><div class="contact-field-label">Mobil</div><div class="contact-field-value">${c.phone}</div></div></div>` : ''}
                    ${addrHtml ? `<div class="contact-field-group"><div class="contact-field-title">Adresse</div><div class="contact-field-row"><div class="contact-field-label">Privat</div><div class="contact-field-value" style="color:#333; line-height:1.5;">${addrHtml}</div></div></div>` : ''}
                    ${c.website ? `<div class="contact-field-group"><div class="contact-field-title">Webseite</div><div class="contact-field-row"><div class="contact-field-label">Privat</div><div class="contact-field-value"><a href="${c.website}" target="_blank">${c.website}</a></div></div></div>` : ''}
                    ${c.im_address ? `<div class="contact-field-group"><div class="contact-field-title">IM-Adresse</div><div class="contact-field-row"><div class="contact-field-label">Privat</div><div class="contact-field-value">${c.im_address}</div></div></div>` : ''}
                </div>
                
                <div id="cv-tab-personal" style="display:none; padding:10px; color:#333; line-height:1.5;">
                    ${c.gender ? `<div class="contact-field-row"><div class="contact-field-label">Geschlecht</div><div class="contact-field-value">${c.gender}</div></div>` : ''}
                    ${c.birthday ? `<div class="contact-field-row"><div class="contact-field-label">Geburtstag</div><div class="contact-field-value">${c.birthday}</div></div>` : ''}
                    ${!c.gender && !c.birthday ? '<span style="color:#aaa;">Keine persönlichen Informationen hinterlegt</span>' : ''}
                </div>

                <div id="cv-tab-notes" style="display:none; padding:10px; color:#333; line-height:1.5;">
                    ${c.notes ? c.notes.replace(/\n/g, '<br>') : '<span style="color:#aaa;">Keine Notizen</span>'}
                </div>
            `;
            
            document.getElementById('btn-del-contact').classList.remove('disabled');
            document.getElementById('btn-comp-contact').classList.remove('disabled');
        }
        
        function switchContactTabV(tabId, el) {
            document.querySelectorAll('.contact-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('cv-tab-props').style.display = (tabId === 'props') ? 'block' : 'none';
            document.getElementById('cv-tab-personal').style.display = (tabId === 'personal') ? 'block' : 'none';
            document.getElementById('cv-tab-notes').style.display = (tabId === 'notes') ? 'block' : 'none';
        }

        function handleAvatarUpload(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatar-input').value = e.target.result;
                    document.getElementById('avatar-preview').style.backgroundImage = `url(${e.target.result})`;
                    document.getElementById('avatar-preview').style.backgroundSize = 'cover';
                    document.getElementById('avatar-preview').style.backgroundPosition = 'center';
                    var icon = document.getElementById('avatar-icon');
                    if(icon) icon.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeAvatar() {
            document.getElementById('avatar-input').value = '';
            document.getElementById('avatar-preview').style.backgroundImage = 'none';
            var icon = document.getElementById('avatar-icon');
            if(icon) {
                icon.style.display = 'block';
            } else {
                document.getElementById('avatar-preview').innerHTML = '<span class="material-symbols-outlined" id="avatar-icon" style="font-size:60px;">person</span>';
            }
        }

        function addFieldOption(sel) {
            if(sel.value) {
                document.getElementById(sel.value).style.display = 'block';
                sel.value = '';
            }
        }

        function hideFieldGroup(id, inputNames) {
            document.getElementById(id).style.display = 'none';
            inputNames.forEach(name => {
                var el = document.querySelector(`#${id} [name="${name}"]`);
                if(el) el.value = '';
            });
        }

        function contactEditMode(id) {
            var c = allContacts.find(x => x.id === id) || {
                id:0, name:'', email:'', phone:'', address:'', notes:'', avatar:'', gender:'', birthday:'', website:'', im_address:'', group_id: activeGroupId
            };
            activeContactId = c.id;
            
            var nameParts = c.name ? c.name.split(' ') : [''];
            var firstName = nameParts.shift();
            var lastName = nameParts.join(' ');
            
            var addr = {street:'', zip:'', city:'', country:'', region:''};
            if(c.address) { 
                try { addr = JSON.parse(c.address); } catch(e) { addr.street = c.address; } 
            }

            var avatarStyle = c.avatar ? `background-image:url(${c.avatar}); background-size:cover; background-position:center;` : '';
            var avatarIcon = c.avatar ? '<span class="material-symbols-outlined" id="avatar-icon" style="font-size:60px; display:none;">person</span>' : '<span class="material-symbols-outlined" id="avatar-icon" style="font-size:60px;">person</span>';

            var groupOptions = contactGroups.map(g => `<option value="${g.id}" ${(c.group_id || activeGroupId) == g.id ? 'selected':''}>${g.name}</option>`).join('');

            var col3 = document.getElementById('contacts-col-3');
            col3.innerHTML = `
                <form id="contact-form" onsubmit="event.preventDefault(); saveContact();">
                    <input type="hidden" name="id" value="${c.id}">
                    <div style="display:flex; justify-content:flex-start; align-items:flex-start; margin-bottom:20px;">
                        <div style="width:130px; text-align:center; margin-right:20px;">
                            <div class="contact-avatar" id="avatar-preview" style="${avatarStyle}">${avatarIcon}</div>
                            <input type="hidden" name="avatar" id="avatar-input" value="${c.avatar || ''}">
                            <input type="file" id="avatar-file" accept="image/png, image/jpeg, image/gif" style="display:none" onchange="handleAvatarUpload(this)">
                            
                            <div style="font-size:11px; color:#555; cursor:pointer; margin-bottom:4px;" onclick="document.getElementById('avatar-file').click()">
                                <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">upload</span> Bild ändern
                            </div>
                            <div style="font-size:11px; color:#555; cursor:pointer;" onclick="removeAvatar()">
                                <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">delete</span> Löschen
                            </div>
                        </div>
                        <div style="flex:1;">
                            <div style="color:#777; font-size:11px; margin-bottom:5px; margin-top:20px;">
                                Adressbuch: 
                                <select name="group_id" class="form-input" style="display:inline-block; width:auto; padding:2px; height:auto; margin-left:5px;">
                                    ${groupOptions}
                                </select>
                            </div>
                            <div style="display:flex; gap:10px; margin-bottom:15px;">
                                <input type="text" name="name" value="${firstName}" placeholder="Vorname" class="form-input" style="font-size:20px; font-weight:bold; width:200px;">
                                <input type="text" name="lastname" value="${lastName}" placeholder="Nachname" class="form-input" style="font-size:20px; width:200px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-tabs">
                        <div class="contact-tab active" onclick="switchContactTabE('props', this)">${l.contact_props}</div>
                        <div class="contact-tab" onclick="switchContactTabE('personal', this)">Persönliche Informationen</div>
                        <div class="contact-tab" onclick="switchContactTabE('notes', this)">Notizen</div>
                    </div>
                    
                    <div id="ce-tab-props">
                        <div class="contact-field-group" id="fg-email">
                            <div class="contact-field-title">E-Mail</div>
                            <div style="display:flex; padding:5px 10px; gap:10px; align-items:center;">
                                <select class="form-input" style="width:100px; flex:none;"><option>Privat</option><option>Geschäftlich</option></select>
                                <input type="email" name="email" value="${c.email || ''}" class="form-input">
                                <span class="material-symbols-outlined" style="color:#aaa; cursor:pointer;" onclick="hideFieldGroup('fg-email', ['email'])">remove_circle</span>
                            </div>
                        </div>
                        
                        <div class="contact-field-group" id="fg-phone" style="display:${c.phone || c.id === 0 ? 'block' : 'none'};">
                            <div class="contact-field-title">Telefon</div>
                            <div style="display:flex; padding:5px 10px; gap:10px; align-items:center;">
                                <select class="form-input" style="width:100px; flex:none;"><option>Mobil</option><option>Privat</option><option>Geschäftlich</option></select>
                                <input type="text" name="phone" value="${c.phone || ''}" class="form-input">
                                <span class="material-symbols-outlined" style="color:#aaa; cursor:pointer;" onclick="hideFieldGroup('fg-phone', ['phone'])">remove_circle</span>
                            </div>
                        </div>
                        
                        <div class="contact-field-group" id="fg-address" style="display:${c.address || c.id === 0 ? 'block' : 'none'};">
                            <div class="contact-field-title">Adresse</div>
                            <div style="display:flex; padding:5px 10px; gap:10px;">
                                <select class="form-input" style="width:100px; flex:none;"><option>Privat</option><option>Geschäftlich</option></select>
                                <div style="flex:1; display:flex; flex-direction:column; gap:5px;">
                                    <input type="text" name="street" value="${addr.street || ''}" placeholder="Straße" class="form-input">
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" name="city" value="${addr.city || ''}" placeholder="Ort" class="form-input" style="flex:2;">
                                        <input type="text" name="zip" value="${addr.zip || ''}" placeholder="PLZ" class="form-input" style="flex:1;">
                                    </div>
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" name="country" value="${addr.country || ''}" placeholder="Land" class="form-input" style="flex:2;">
                                        <input type="text" name="region" value="${addr.region || ''}" placeholder="Region" class="form-input" style="flex:1;">
                                    </div>
                                </div>
                                <span class="material-symbols-outlined" style="color:#aaa; cursor:pointer; align-self:flex-end; margin-bottom:10px;" onclick="hideFieldGroup('fg-address', ['street', 'city', 'zip', 'country', 'region'])">remove_circle</span>
                            </div>
                        </div>

                        <div class="contact-field-group" id="fg-website" style="display:${c.website ? 'block' : 'none'};">
                            <div class="contact-field-title">Webseite</div>
                            <div style="display:flex; padding:5px 10px; gap:10px; align-items:center;">
                                <select class="form-input" style="width:100px; flex:none;"><option>Privat</option><option>Geschäftlich</option></select>
                                <input type="text" name="website" value="${c.website || ''}" class="form-input">
                                <span class="material-symbols-outlined" style="color:#aaa; cursor:pointer;" onclick="hideFieldGroup('fg-website', ['website'])">remove_circle</span>
                            </div>
                        </div>

                        <div class="contact-field-group" id="fg-im" style="display:${c.im_address ? 'block' : 'none'};">
                            <div class="contact-field-title">IM-Adresse</div>
                            <div style="display:flex; padding:5px 10px; gap:10px; align-items:center;">
                                <select class="form-input" style="width:100px; flex:none;"><option>Privat</option><option>Geschäftlich</option></select>
                                <input type="text" name="im_address" value="${c.im_address || ''}" class="form-input">
                                <span class="material-symbols-outlined" style="color:#aaa; cursor:pointer;" onclick="hideFieldGroup('fg-im', ['im_address'])">remove_circle</span>
                            </div>
                        </div>

                        <div style="padding:10px;">
                            <select class="form-input" style="width:150px;" onchange="addFieldOption(this)">
                                <option value="">Feld hinzufügen ...</option>
                                <option value="fg-email">E-Mail</option>
                                <option value="fg-phone">Telefon</option>
                                <option value="fg-address">Adresse</option>
                                <option value="fg-website">Webseite</option>
                                <option value="fg-im">IM-Adresse</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="ce-tab-personal" style="display:none; padding:10px;">
                        <div class="contact-field-row" style="align-items:center; border:none;">
                            <div class="contact-field-label" style="font-weight:bold; color:#333;">Geschlecht</div>
                            <select name="gender" class="form-input" style="max-width:200px;">
                                <option value="" ${c.gender==='' ? 'selected':''}>---</option>
                                <option value="männlich" ${c.gender==='männlich' ? 'selected':''}>männlich</option>
                                <option value="weiblich" ${c.gender==='weiblich' ? 'selected':''}>weiblich</option>
                                <option value="nicht binär" ${c.gender==='nicht binär' ? 'selected':''}>nicht binär</option>
                                <option value="andere" ${c.gender==='andere' ? 'selected':''}>andere</option>
                            </select>
                        </div>
                        <div class="contact-field-row" style="align-items:center; border:none; margin-top:10px;">
                            <div class="contact-field-label" style="font-weight:bold; color:#333;">Geburtstag</div>
                            <input type="date" name="birthday" value="${c.birthday || ''}" class="form-input" style="max-width:200px;">
                        </div>
                    </div>
                    
                    <div id="ce-tab-notes" style="display:none; padding:10px;">
                        <textarea name="notes" class="form-input" style="width:100%; height:200px; resize:none;" placeholder="Notizen...">${c.notes || ''}</textarea>
                    </div>
                    
                    <div style="margin-top:20px; padding:10px; border-top:1px solid #eee; background:#f9f9f9;">
                        <button type="submit" class="btn-dark" style="margin-right:10px;">${l.btn_save}</button>
                        <button type="button" class="btn-grey" onclick="loadContacts()">${l.btn_cancel}</button>
                    </div>
                </form>
            `;
        }

        function switchContactTabE(tabId, el) {
            document.querySelectorAll('.contact-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('ce-tab-props').style.display = (tabId === 'props') ? 'block' : 'none';
            document.getElementById('ce-tab-personal').style.display = (tabId === 'personal') ? 'block' : 'none';
            document.getElementById('ce-tab-notes').style.display = (tabId === 'notes') ? 'block' : 'none';
        }

        function saveContact() {
            var form = document.getElementById('contact-form');
            fetch('action.php?action=edit_contact', { 
                method: 'POST', 
                body: new FormData(form) 
            }).then(() => {
                loadContacts();
            });
        }

        function addAddressBook() {
            customPrompt("Bitte gib einen Namen für das neue Adressbuch ein:", function(name) {
                if (name && name.trim() !== '') {
                    var fd = new FormData();
                    fd.append('name', name.trim());
                    fetch('action.php?action=add_contact_group', { method: 'POST', body: fd })
                    .then(() => loadContactGroups());
                }
            });
        }

        function deleteAddressBook() {
            if (!activeGroupId) return;
            var firstGroupId = contactGroups.length > 0 ? contactGroups[0].id : null;
            if (activeGroupId == firstGroupId) {
                customAlert("Das Standard-Adressbuch kann nicht gelöscht werden.");
                return;
            }
            
            var group = contactGroups.find(g => g.id == activeGroupId);
            var groupName = group ? group.name : 'dieses Adressbuch';
            
            customConfirm(`Möchtest du das Adressbuch "${groupName}" wirklich löschen?<br><br><i>Hinweis: Die darin enthaltenen Kontakte gehen nicht verloren, sondern werden in dein Standard-Adressbuch verschoben.</i>`, function() {
                var fd = new FormData();
                fd.append('group_id', activeGroupId);
                fetch('action.php?action=delete_contact_group', { method: 'POST', body: fd })
                .then(() => {
                    activeGroupId = firstGroupId; // Setze Ansicht auf Standard zurück
                    loadContactGroups();
                });
            });
        }

        function deleteActiveContact() {
            var checked = document.querySelectorAll('.contact-cb:checked');
            if(checked.length > 0) {
                customConfirm(l.js_del_multi, function() {
                    var formData = new FormData();
                    Array.from(checked).forEach(c => formData.append('contact_ids[]', c.value));
                    fetch('action.php?action=bulk_delete_contacts', { method: 'POST', body: formData })
                    .then(() => loadContacts());
                });
            } else if(activeContactId) {
                customConfirm(l.js_del_single, function() {
                    fetch('action.php?action=delete_contact&id=' + activeContactId)
                    .then(() => loadContacts());
                });
            }
        }
        
        function composeToContact() {
            if(!activeContactId) return;
            var c = allContacts.find(x => x.id === activeContactId);
            if(c && c.email) {
                switchAppView('mail');
                document.querySelector('input[name="to"]').value = c.name ? `"${c.name}" <${c.email}>` : c.email;
                document.querySelector('input[name="subject"]').value = '';
                document.getElementById('compose-modal').style.display = 'flex';
            } else {
                customAlert("Dieser Kontakt hat keine E-Mail-Adresse.");
            }
        }

        // --- SETTINGS APP FUNKTIONEN ---
        function openSettingsCategory(cat, element) {
            document.querySelectorAll('.set-col-1 .set-menu li').forEach(li => li.classList.remove('active'));
            if(element) element.classList.add('active');
            
            var col2 = document.getElementById('set-col-2');
            var col3 = document.getElementById('set-col-3');
            col2.style.display = 'flex'; 
            col3.innerHTML = '';
            
            var col2Title = document.getElementById('set-col-2-title');
            var col2List = document.getElementById('set-col-2-list');
            var col2Toolbar = document.getElementById('set-col-2-toolbar');
            
            if (cat === 'settings') {
                col2Title.innerText = 'Bereich';
                col2Toolbar.style.display = 'none';
                
                col2List.innerHTML = `
                    <div class="set-list-item active" onclick="loadSettingsForm('ui', this)"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; margin-right:5px;">desktop_windows</span> ${l.set_area_ui}</div>
                    <div class="set-list-item" onclick="loadSettingsForm('mailbox', this)"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; margin-right:5px;">view_list</span> ${l.set_area_mailbox}</div>
                `;
                
                loadSettingsForm('ui', col2List.firstElementChild);
            } else if (cat === 'folders') {
                col2Title.innerText = l.set_cat_folders;
                col2Toolbar.style.display = 'none';
                col2List.innerHTML = '<div class="set-list-item active"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; margin-right:5px;">inventory_2</span> Abonnements</div>';
                
                var fHtml = Object.keys(allFoldersMap).map(f => {
                    var isHidden = hiddenFolders.includes(f);
                    var dec = allFoldersMap[f];
                    return `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; color:#777; margin-right:8px;">folder</span>${dec}</td>
                            <td style="padding:10px; text-align:right;"><input type="checkbox" name="visible_folders[]" value="${f}" ${isHidden ? '' : 'checked'}></td>
                        </tr>
                    `;
                }).join('');
                
                col3.innerHTML = `
                    <form action="action.php?action=save_folders" method="POST">
                        <div class="set-content-box">
                            <h3 class="set-content-title">Ordner auf der Hauptseite ein- / ausblenden</h3>
                            <table style="width:100%; border-collapse:collapse;">${fHtml}</table>
                        </div>
                        <button type="submit" class="btn-dark">${l.btn_save}</button>
                    </form>
                `;
            } else if (cat === 'identities') {
                col2Title.innerText = l.set_cat_identities;
                col2Toolbar.style.display = 'flex';
                col2Toolbar.innerHTML = '<span class="material-symbols-outlined" onclick="editIdentity(0)">add</span>';
                fetch('action.php?action=get_identities')
                .then(res => res.json())
                .then(data => {
                    col2List.innerHTML = '';
                    data.forEach((id, index) => {
                        var div = document.createElement('div'); 
                        div.className = 'set-list-item' + (index===0 ? ' active' : '');
                        div.innerText = id.display_name + ' <' + id.email + '>';
                        div.onclick = function() { editIdentity(id, this); };
                        col2List.appendChild(div); 
                        if(index===0) editIdentity(id, div);
                    });
                });
            } else if (cat === 'responses') {
                col2Title.innerText = l.set_cat_responses;
                col2Toolbar.style.display = 'flex';
                col2Toolbar.innerHTML = '<span class="material-symbols-outlined" onclick="editResponse(0)">add</span>';
                fetch('action.php?action=get_quick_replies')
                .then(res => res.json())
                .then(data => {
                    col2List.innerHTML = '';
                    if(data.length === 0) {
                        col3.innerHTML = `<div style="text-align:center; padding:50px; color:#aaa;"><span class="material-symbols-outlined" style="font-size:50px;">note_add</span><br>Keine Antworten</div>`;
                    }
                    data.forEach((r, index) => {
                        var div = document.createElement('div'); 
                        div.className = 'set-list-item' + (index===0 ? ' active' : '');
                        div.innerText = r.name; 
                        div.onclick = function() { editResponse(r, this); };
                        col2List.appendChild(div); 
                        if(index===0) editResponse(r, div);
                    });
                });
            } else if (cat === 'userinfo') {
                col2.style.display = 'none';
                
                col3.innerHTML = `
                    <div class="set-content-title">${l.set_user_info} ${currentImapUser}</div>
                    <table style="width:100%; border:1px solid #ccc; border-collapse:collapse; background:#f9f9f9;">
                        <tr><td style="padding:8px; border-bottom:1px solid #eee; width:200px;">${l.set_user_name}</td><td style="padding:8px; border-bottom:1px solid #eee; font-weight:bold;">${currentImapUser}</td></tr>
                        <tr><td style="padding:8px; border-bottom:1px solid #eee;">${l.set_user_server}</td><td style="padding:8px; border-bottom:1px solid #eee; font-weight:bold;">${currentImapHost}</td></tr>
                    </table>
                `;
            }
        }

        function setActiveItem(element) {
            if(!element) return;
            var siblings = element.parentNode.children;
            for(var i=0; i<siblings.length; i++) {
                siblings[i].classList.remove('active');
            }
            element.classList.add('active');
        }

        function loadSettingsForm(type, element) {
            setActiveItem(element);
            var col3 = document.getElementById('set-col-3');
            if (type === 'ui') {
                col3.innerHTML = `
                    <form action="action.php?action=save_settings" method="POST">
                        <div class="set-content-box">
                            <h3 class="set-content-title">Haupteinstellungen</h3>
                            <div class="set-form-row">
                                <label>${l.set_lang}</label>
                                <select name="language" style="max-width:200px;" class="form-input">
                                    <option value="de" ${userSettings.language === 'de' ? 'selected' : ''}>Deutsch</option>
                                    <option value="en" ${userSettings.language === 'en' ? 'selected' : ''}>English</option>
                                </select>
                            </div>
                            <div class="set-form-row">
                                <label>Cache</label>
                                <a href="action.php?action=refresh_cache" style="color: red; text-decoration: none; font-size: 12px;">${l.set_cache}</a>
                            </div>
                        </div>
                        <button type="submit" class="btn-dark">${l.btn_save}</button>
                    </form>
                `;
            } else if (type === 'mailbox') {
                var folderOptions = `<option value="">${l.set_auto}</option>`;
                for (var raw in allFoldersMap) {
                    var selected = (userSettings.archive_folder === raw) ? 'selected' : '';
                    folderOptions += `<option value="${raw}" ${selected}>${allFoldersMap[raw]}</option>`;
                }
                
                col3.innerHTML = `
                    <form action="action.php?action=save_settings" method="POST">
                        <div class="set-content-box">
                            <h3 class="set-content-title">Postfachansicht</h3>
                            <div class="set-form-row">
                                <label>${l.set_per_page}</label>
                                <select name="per_page" style="max-width:100px;" class="form-input">
                                    <option value="15" ${userSettings.per_page === 15 ? 'selected' : ''}>15</option>
                                    <option value="25" ${userSettings.per_page === 25 ? 'selected' : ''}>25</option>
                                    <option value="50" ${userSettings.per_page === 50 ? 'selected' : ''}>50</option>
                                    <option value="100" ${userSettings.per_page === 100 ? 'selected' : ''}>100</option>
                                </select>
                            </div>
                            <div class="set-form-row">
                                <label>${l.set_archive}</label>
                                <select name="archive_folder" style="max-width:300px;" class="form-input">
                                    ${folderOptions}
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-dark">${l.btn_save}</button>
                    </form>
                `;
            }
        }

        var activeIdentityId = null;
        function editIdentity(idObj, element) {
            if(element) setActiveItem(element);
            var isNew = (idObj === 0); 
            activeIdentityId = isNew ? 0 : idObj.id;
            var dname = isNew ? '' : idObj.display_name; 
            var mail = isNew ? '' : idObj.email; 
            var org = isNew ? '' : (idObj.organization || '');
            var reply = isNew ? '' : (idObj.reply_to || ''); 
            var bcc = isNew ? '' : (idObj.bcc || ''); 
            var sig = isNew ? '' : (idObj.signature || '');
            var isDef = isNew ? '' : (idObj.is_default == 1 ? 'checked' : ''); 
            var isHtml = isNew ? '' : (idObj.is_html_sig == 1 ? 'checked' : '');

            col3.innerHTML = `
                <form id="ident-form" onsubmit="event.preventDefault(); saveIdentity();">
                    <input type="hidden" name="id" value="${activeIdentityId}">
                    <div class="set-content-box">
                        <h3 class="set-content-title">Einstellungen</h3>
                        <div class="set-form-row"><label>${l.set_ident_name}</label><input type="text" name="display_name" value="${dname}" required class="form-input"></div>
                        <div class="set-form-row"><label>E-Mail</label><input type="email" name="email" value="${mail}" required class="form-input"></div>
                        <div class="set-form-row"><label>${l.set_ident_org}</label><input type="text" name="organization" value="${org}" class="form-input"></div>
                        <div class="set-form-row"><label>${l.set_ident_replyto}</label><input type="email" name="reply_to" value="${reply}" class="form-input"></div>
                        <div class="set-form-row"><label>${l.set_ident_bcc}</label><input type="email" name="bcc" value="${bcc}" class="form-input"></div>
                        <div class="set-form-row"><label>${l.set_ident_default}</label><input type="checkbox" name="is_default" value="1" ${isDef} style="flex:none; margin:0;"></div>
                    </div>
                    <div class="set-content-box">
                        <h3 class="set-content-title">${l.set_ident_sig}</h3>
                        <textarea name="signature" class="form-input" style="width:100%; height:100px; resize:none; margin-bottom:10px;">${sig}</textarea>
                        <div class="set-form-row"><label>${l.set_ident_html}</label><input type="checkbox" name="is_html_sig" value="1" ${isHtml} style="flex:none; margin:0;"></div>
                    </div>
                    <button type="submit" class="btn-dark">${l.btn_save}</button>
                </form>
            `;
        }

        function saveIdentity() {
            var form = document.getElementById('ident-form');
            fetch('action.php?action=save_identity', { method: 'POST', body: new FormData(form) })
            .then(() => openSettingsCategory('identities', document.getElementById('menu-cat-identities')));
        }

        var activeResponseId = null;
        function editResponse(resObj, element) {
            if(element) setActiveItem(element);
            var isNew = (resObj === 0); 
            activeResponseId = isNew ? 0 : resObj.id;
            var name = isNew ? '' : resObj.name; 
            var body = isNew ? '' : resObj.body;

            document.getElementById('set-col-3').innerHTML = `
                <form id="resp-form" onsubmit="event.preventDefault(); saveResponse();">
                    <input type="hidden" name="id" value="${activeResponseId}">
                    <div class="set-content-box">
                        <h3 class="set-content-title">${l.set_resp_edit}</h3>
                        <div class="set-form-row"><label>${l.set_resp_name}</label><input type="text" name="name" value="${name}" required class="form-input"></div>
                        <div class="set-form-row" style="align-items:flex-start;"><label>${l.set_resp_text}</label><textarea name="body" class="form-input" style="flex:1; height:200px; resize:none;" required>${body}</textarea></div>
                    </div>
                    <button type="submit" class="btn-dark">${l.btn_save}</button>
                </form>
            `;
        }

        function saveResponse() {
            var form = document.getElementById('resp-form');
            fetch('action.php?action=save_quick_reply', { method: 'POST', body: new FormData(form) })
            .then(() => openSettingsCategory('responses', document.getElementById('menu-cat-responses')));
        }

        // --- MAIL APP FUNKTIONEN ---
        function updateToolbarState() {
            var checked = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            var enableAny = checked.length > 0;
            var enableSingleOnly = checked.length === 1;

            document.querySelectorAll('#toolbar-mail .requires-selection').forEach(btn => {
                if (btn.classList.contains('single-only')) {
                    if (enableSingleOnly) btn.classList.remove('disabled'); 
                    else btn.classList.add('disabled');
                } else {
                    if (enableAny) btn.classList.remove('disabled'); 
                    else btn.classList.add('disabled');
                }
            });

            document.querySelectorAll('.single-only-menu').forEach(item => {
                if(enableSingleOnly) item.classList.remove('disabled-item'); 
                else item.classList.add('disabled-item');
            });
            
            var allCheckboxes = document.querySelectorAll('input[name="mail_ids[]"]');
            var allChecked = allCheckboxes.length > 0 && allCheckboxes.length === checked.length;
            var headerCb = document.getElementById('header-select-all');
            var footerCb = document.getElementById('footer-select-all');
            if(headerCb) headerCb.checked = allChecked;
            if(footerCb) footerCb.checked = allChecked;
        }

        function selectMailRow(row, event) {
            if (event && event.target.closest('.td-star')) return;
            var cb = row.querySelector('input[type="checkbox"]');
            var isCheckboxClick = event && event.target.tagName.toLowerCase() === 'input' && event.target.type === 'checkbox';

            if (isCheckboxClick) {
                if (cb.checked) row.classList.add('selected'); 
                else row.classList.remove('selected');
            } else {
                if (event && (event.ctrlKey || event.metaKey)) {
                    if (row.classList.contains('selected')) {
                        row.classList.remove('selected'); 
                        if (cb) cb.checked = false;
                    } else {
                        row.classList.add('selected'); 
                        if (cb) cb.checked = true;
                    }
                } else {
                    document.querySelectorAll('tr.mail-row').forEach(r => { 
                        r.classList.remove('selected'); 
                        var innerCb = r.querySelector('input[type="checkbox"]'); 
                        if (innerCb) innerCb.checked = false; 
                    });
                    row.classList.add('selected'); 
                    if (cb) cb.checked = true;
                }
            }

            var checkedBoxes = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            
            if (checkedBoxes.length === 1) {
                var singleRow = checkedBoxes[0].closest('tr');
                var id = singleRow.getAttribute('data-id');
                var folder = encodeURIComponent(singleRow.getAttribute('data-folder'));
                var subj = singleRow.querySelector('.td-subj').innerText;
                
                selectedMailId = id; 
                selectedMailFolder = folder;
                var iframe = document.getElementById('content');
                if (iframe) { 
                    // SPINNER ANZEIGEN, IFRAME VERSTECKEN
                    document.getElementById('preview-loader').style.display = 'flex';
                    iframe.style.display = 'none';
                    iframe.onload = function() {
                        document.getElementById('preview-loader').style.display = 'none';
                        iframe.style.display = 'block';
                    };
                    iframe.src = 'body.php?id=' + id + '&folder=' + folder; 
                }
                var ps = document.getElementById('preview-subject'); 
                if (ps) ps.innerText = subj;
                
                var container = document.getElementById('split-container');
                if (container && container.classList.contains('mode-none')) {
                    container.classList.add('reading-fullscreen');
                }

                if (singleRow.classList.contains('unread')) {
                    fetch('action.php?action=mark_read&id=' + id + '&folder=' + folder, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    singleRow.classList.remove('unread'); 
                    singleRow.classList.add('read');
                }
            } else if (checkedBoxes.length > 1) {
                selectedMailId = null;
                var iframe = document.getElementById('content'); 
                if (iframe) { iframe.src = 'about:blank'; iframe.style.display = 'none'; }
                var ps = document.getElementById('preview-subject'); 
                if (ps) ps.innerText = checkedBoxes.length + " Nachrichten ausgewählt";
            } else {
                selectedMailId = null;
                var iframe = document.getElementById('content'); 
                if (iframe) { iframe.src = 'about:blank'; iframe.style.display = 'none'; }
                var ps = document.getElementById('preview-subject'); 
                if (ps) ps.innerText = l.no_msg_sel;
            }
            updateToolbarState();
        }

        function toggleSelectAll(source) {
            var isChecked = (typeof source === 'boolean') ? source : source.checked;
            var checkboxes = document.getElementsByName('mail_ids[]');
            
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = isChecked;
                var row = checkboxes[i].closest('tr');
                if(isChecked) row.classList.add('selected'); 
                else row.classList.remove('selected');
            }
            
            var ps = document.getElementById('preview-subject');
            var iframe = document.getElementById('content');
            selectedMailId = null;
            if (iframe) { iframe.src = 'about:blank'; iframe.style.display = 'none'; }
            
            if(isChecked && checkboxes.length > 0) { 
                if (ps) ps.innerText = checkboxes.length + " Nachrichten ausgewählt"; 
            } else { 
                if (ps) ps.innerText = l.no_msg_sel; 
            }
            
            updateToolbarState();
        }

        function handleFooterSelection(selectObj) {
            var val = selectObj.value; 
            if (!val) return;
            
            var checkboxes = document.getElementsByName('mail_ids[]');
            for (var i = 0; i < checkboxes.length; i++) {
                var tr = checkboxes[i].closest('tr');
                if (val === 'all') checkboxes[i].checked = true;
                else if (val === 'none') checkboxes[i].checked = false;
                else if (val === 'read') checkboxes[i].checked = tr.classList.contains('read');
                else if (val === 'unread') checkboxes[i].checked = tr.classList.contains('unread');
                
                if(checkboxes[i].checked) tr.classList.add('selected'); 
                else tr.classList.remove('selected');
            }
            
            selectObj.value = ""; 
            var checked = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            var ps = document.getElementById('preview-subject');
            var iframe = document.getElementById('content');
            selectedMailId = null;
            if (iframe) { iframe.src = 'about:blank'; iframe.style.display = 'none'; }
            
            if(checked.length > 0) { 
                if (ps) ps.innerText = checked.length + " Nachrichten ausgewählt"; 
            } else { 
                if (ps) ps.innerText = l.no_msg_sel; 
            }
            updateToolbarState();
        }

        // OPTIMISTIC UI: Markierung passiert nun sofort visuell
        function actionMark(markType) {
            var checked = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            if (checked.length === 0) return;
            var action = (markType === 'read') ? 'mark_read' : (markType === 'unread' ? 'mark_unread' : 'mark_flagged');
            
            checked.forEach(cb => {
                var id = cb.value; 
                var tr = cb.closest('tr');
                var folder = tr.getAttribute('data-folder');
                
                // Visuelles Feedback
                if (markType === 'read') {
                    tr.classList.remove('unread');
                    tr.classList.add('read');
                } else if (markType === 'unread') {
                    tr.classList.remove('read');
                    tr.classList.add('unread');
                } else if (markType === 'flagged') {
                    var star = tr.querySelector('.td-star');
                    star.classList.add('active');
                    star.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">star</span>';
                }

                // Echter Aufruf im Hintergrund
                fetch('action.php?action=' + action + '&id=' + id + '&folder=' + encodeURIComponent(folder), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            });
        }

        function applyLayout(mode) {
            var container = document.getElementById('split-container');
            if(container) container.className = 'split-view-container mode-' + mode;
            var listArea = document.getElementById('list-area');
            if(listArea) { listArea.style.width = ''; listArea.style.height = ''; }
            
            var rWide = document.getElementById('rb_wide');
            var rDesk = document.getElementById('rb_desk');
            var rList = document.getElementById('rb_list');
            if(rWide) rWide.checked = (mode === 'right');
            if(rDesk) rDesk.checked = (mode === 'bottom');
            if(rList) rList.checked = (mode === 'none');
        }

        function switchLayout(mode) {
            currentLayout = mode; 
            localStorage.setItem('rc_split_layout', mode); 
            applyLayout(mode);
        }

        function applyColumns() {
            var cols; 
            try { cols = JSON.parse(localStorage.getItem('rc_cols')); } catch(e) {}
            if (!Array.isArray(cols)) cols = ['fromto', 'subj', 'date', 'size', 'attach', 'flag'];
            var b = document.body;
            b.classList.toggle('hide-col-fromto', !cols.includes('fromto'));
            b.classList.toggle('hide-col-subj', !cols.includes('subj'));
            b.classList.toggle('hide-col-date', !cols.includes('date'));
            b.classList.toggle('hide-col-size', !cols.includes('size'));
            b.classList.toggle('hide-col-attach', !cols.includes('attach'));
            b.classList.toggle('hide-col-flag', !cols.includes('flag'));
        }

        function openViewSettings() {
            var cols; 
            try { cols = JSON.parse(localStorage.getItem('rc_cols')); } catch(e) {}
            if (!Array.isArray(cols)) cols = ['fromto', 'subj', 'date', 'size', 'attach', 'flag'];
            
            if(document.getElementById('cb_col_fromto')) document.getElementById('cb_col_fromto').checked = cols.includes('fromto');
            if(document.getElementById('cb_col_subj')) document.getElementById('cb_col_subj').checked = cols.includes('subj');
            if(document.getElementById('cb_col_date')) document.getElementById('cb_col_date').checked = cols.includes('date');
            if(document.getElementById('cb_col_size')) document.getElementById('cb_col_size').checked = cols.includes('size');
            if(document.getElementById('cb_col_attach')) document.getElementById('cb_col_attach').checked = cols.includes('attach');
            if(document.getElementById('cb_col_flag')) document.getElementById('cb_col_flag').checked = cols.includes('flag');
            
            var sort = getCookie('rc_sort') || 'recv';
            var dir = getCookie('rc_dir') || 'DESC';
            var sbEl = document.getElementById('sb_' + sort);
            var soEl = document.getElementById('so_' + dir.toLowerCase());
            if(sbEl) sbEl.checked = true; 
            if(soEl) soEl.checked = true;
            
            document.getElementById('view-settings-modal').style.display = 'flex';
        }

        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)')); 
            return match ? match[2] : null;
        }

        function saveViewSettings() {
            var rWide = document.getElementById('rb_wide').checked;
            var rDesk = document.getElementById('rb_desk').checked;
            var rList = document.getElementById('rb_list').checked;
            if(rWide) switchLayout('right'); 
            else if(rDesk) switchLayout('bottom'); 
            else if(rList) switchLayout('none');

            var cols = [];
            if(document.getElementById('cb_col_fromto').checked) cols.push('fromto');
            if(document.getElementById('cb_col_subj').checked) cols.push('subj');
            if(document.getElementById('cb_col_date').checked) cols.push('date');
            if(document.getElementById('cb_col_size').checked) cols.push('size');
            if(document.getElementById('cb_col_attach').checked) cols.push('attach');
            if(document.getElementById('cb_col_flag').checked) cols.push('flag');
            localStorage.setItem('rc_cols', JSON.stringify(cols));
            applyColumns();

            var sortEl = document.querySelector('input[name="sort_by"]:checked');
            var dirEl = document.querySelector('input[name="sort_ord"]:checked');
            var sort = sortEl ? sortEl.value : 'recv';
            var dir = dirEl ? dirEl.value : 'DESC';
            var oldSort = getCookie('rc_sort') || 'recv';
            var oldDir = getCookie('rc_dir') || 'DESC';
            
            document.cookie = "rc_sort=" + sort + "; path=/; max-age=31536000";
            document.cookie = "rc_dir=" + dir + "; path=/; max-age=31536000";
            document.getElementById('view-settings-modal').style.display = 'none';

            if (sort !== oldSort || dir !== oldDir) {
                window.location.reload();
            }
        }

        function initTableResizers() {
            var thElm; var startOffset;
            var iframe = document.getElementById('content');
            var dragOverlay = document.createElement('div');
            dragOverlay.style.position = 'fixed'; 
            dragOverlay.style.inset = '0'; 
            dragOverlay.style.zIndex = '9999'; 
            dragOverlay.style.cursor = 'col-resize'; 
            dragOverlay.style.display = 'none';
            document.body.appendChild(dragOverlay);

            document.querySelectorAll("th.resizable").forEach(function (th) {
                var grip = document.createElement('div');
                grip.classList.add('col-resizer'); 
                th.appendChild(grip);
                
                grip.addEventListener('mousedown', function (e) {
                    thElm = th; 
                    startOffset = th.offsetWidth - e.pageX; 
                    dragOverlay.style.display = 'block'; 
                    if(iframe) iframe.style.pointerEvents = 'none';
                    e.preventDefault();
                });
            });

            document.addEventListener('mousemove', function (e) {
                if (thElm) thElm.style.width = startOffset + e.pageX + 'px';
            });
            document.addEventListener('mouseup', function () {
                thElm = undefined; 
                dragOverlay.style.display = 'none';
                if(iframe) iframe.style.pointerEvents = 'auto';
            });
        }

        function toggleFolder(btn, event) {
            event.stopPropagation();
            var li = btn.closest('li'); 
            li.classList.toggle('open');
            btn.innerHTML = li.classList.contains('open') ? 'arrow_drop_down' : 'arrow_right';
            var icon = li.querySelector('.folder-icon');
            if(icon && icon.innerHTML === 'folder' || icon.innerHTML === 'folder_open') {
                icon.innerHTML = li.classList.contains('open') ? 'folder_open' : 'folder';
            }
        }

        function toggleDropdown(id, event) {
            event.stopPropagation();
            var menu = document.getElementById(id);
            var isVisible = menu.style.display === 'block';
            document.querySelectorAll('.tb-dropdown-menu').forEach(m => m.style.display = 'none');
            menu.style.display = isVisible ? 'none' : 'block';
        }

        function initResizers() {
            var splitResizer = document.getElementById('resizer');
            var sidebarResizer = document.getElementById('sidebar-resizer');
            var container = document.getElementById('split-container');
            var listArea = document.getElementById('list-area');
            var sidebar = document.getElementById('sidebar');
            var iframe = document.getElementById('content');
            var isSplitResizing = false; 
            var isSidebarResizing = false;

            var dragOverlay = document.createElement('div');
            dragOverlay.style.position = 'fixed'; 
            dragOverlay.style.inset = '0'; 
            dragOverlay.style.zIndex = '9999'; 
            dragOverlay.style.display = 'none';
            document.body.appendChild(dragOverlay);

            if (splitResizer) {
                splitResizer.addEventListener('mousedown', function(e) { 
                    isSplitResizing = true; 
                    dragOverlay.style.display = 'block'; 
                    dragOverlay.style.cursor = container.classList.contains('mode-bottom') ? 'row-resize' : 'col-resize'; 
                    if(iframe) iframe.style.pointerEvents = 'none'; 
                    e.preventDefault(); 
                });
            }
            if (sidebarResizer) {
                sidebarResizer.addEventListener('mousedown', function(e) { 
                    isSidebarResizing = true; 
                    dragOverlay.style.display = 'block'; 
                    dragOverlay.style.cursor = 'col-resize'; 
                    if(iframe) iframe.style.pointerEvents = 'none'; 
                    e.preventDefault(); 
                });
            }

            window.addEventListener('mousemove', function(e) {
                if (isSplitResizing) {
                    var rect = container.getBoundingClientRect();
                    if (container.classList.contains('mode-bottom')) {
                        var newHeight = e.clientY - rect.top;
                        if (newHeight > 100 && newHeight < rect.height - 100) listArea.style.height = newHeight + 'px';
                    } else if (container.classList.contains('mode-right')) {
                        var newWidth = e.clientX - rect.left;
                        if (newWidth > 200 && newWidth < rect.width - 250) listArea.style.width = newWidth + 'px';
                    }
                }
                if (isSidebarResizing && sidebar) {
                    if (e.clientX > 150 && e.clientX < 400) sidebar.style.width = e.clientX + 'px';
                }
            });

            window.addEventListener('mouseup', function() { 
                isSplitResizing = false; 
                isSidebarResizing = false; 
                dragOverlay.style.display = 'none';
                if(iframe) iframe.style.pointerEvents = 'auto';
            });
        }

        function toggleStar(btn, id, folder, event) {
            event.stopPropagation();
            var td = btn.closest('.td-star');
            var isActive = td.classList.contains('active');
            var action = isActive ? 'unmark_flagged' : 'mark_flagged';
            fetch('action.php?action=' + action + '&id=' + id + '&folder=' + encodeURIComponent(folder), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(() => {
                if (isActive) td.classList.remove('active'); 
                else td.classList.add('active');
            });
        }

        function actionRefresh() { window.location.reload(); }
        
        function actionCompose() { 
            document.querySelector('input[name="to"]').value = '';
            document.querySelector('input[name="subject"]').value = '';
            fetch('action.php?action=get_identities').then(res => res.json()).then(data => {
                var sig = "";
                if(data.length > 0) {
                    var def = data.find(i => i.is_default == 1) || data[0];
                    if(def.signature) {
                        sig = def.is_html_sig == 1 ? def.signature : '<br><br>--<br>' + def.signature.replace(/\n/g, '<br>');
                    }
                }
                tinymce.get('compose-editor').setContent(sig);
                document.getElementById('compose-modal').style.display = 'flex'; 
            });
        }
        
        function closeCompose() { document.getElementById('compose-modal').style.display = 'none'; }
        
        function actionReply(type) {
            if(!selectedMailId) return;
            fetch('action.php?action=get_mail_data&id=' + selectedMailId + '&folder=' + selectedMailFolder)
            .then(res => res.json())
            .then(data => {
                var formTo = ''; 
                var formSubject = data.subject; 
                var quoteHeader = '';
                
                if(type === 'reply') {
                    formTo = data.from; 
                    if(!formSubject.toLowerCase().startsWith('re:')) formSubject = 'Re: ' + formSubject;
                    quoteHeader = l.reply_header.replace('%s', data.date).replace('%s', data.from_name).replace('%s', data.from);
                } else if (type === 'reply_all') {
                    formTo = data.from + (data.to ? ', ' + data.to : ''); 
                    if(!formSubject.toLowerCase().startsWith('re:')) formSubject = 'Re: ' + formSubject;
                    quoteHeader = l.reply_header.replace('%s', data.date).replace('%s', data.from_name).replace('%s', data.from);
                } else if (type === 'forward') {
                    formTo = ''; 
                    if(!formSubject.toLowerCase().startsWith('fwd:')) formSubject = 'Fwd: ' + formSubject;
                    quoteHeader = l.fwd_header.replace('%s', data.from_name).replace('%s', data.from).replace('%s', data.date).replace('%s', data.subject).replace('%s', data.to);
                }
                
                document.querySelector('input[name="to"]').value = formTo; 
                document.querySelector('input[name="subject"]').value = formSubject;
                
                fetch('action.php?action=get_identities').then(r => r.json()).then(ids => {
                    var sig = "";
                    if(ids.length > 0) {
                        var def = ids.find(i => i.is_default == 1) || ids[0];
                        if(def.signature) sig = def.is_html_sig == 1 ? def.signature : '<br><br>--<br>' + def.signature.replace(/\n/g, '<br>');
                    }
                    tinymce.get('compose-editor').setContent(sig + quoteHeader + '<blockquote style="border-left: 2px solid #ccc; padding-left: 10px; margin-left: 5px;">' + data.body + '</blockquote>');
                    document.getElementById('compose-modal').style.display = 'flex';
                });
            });
        }

        // OPTIMISTIC UI: Mails sofort optisch löschen
        function actionDelete() {
            var checked = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            if (checked.length > 0) {
                customConfirm(l.js_del_multi, function() {
                    var formData = new FormData(document.getElementById('bulk-form'));
                    
                    // Visuelles sofort-Löschen
                    checked.forEach(cb => cb.closest('tr').style.display = 'none');
                    closeFullscreenView();
                    
                    fetch("action.php?action=bulk_delete&folder=" + encodeURIComponent(currentFolderRaw), {
                        method: 'POST',
                        body: formData
                    });
                });
            } else {
                customAlert(l.js_sel_req);
            }
        }
        
        // OPTIMISTIC UI: Mails sofort optisch verschieben
        function actionMove(targetFolder) {
            if(!targetFolder) return;
            var checked = document.querySelectorAll('input[name="mail_ids[]"]:checked');
            if (checked.length === 0) { 
                customAlert(l.js_sel_req); 
                return; 
            }
            
            var formData = new FormData(document.getElementById('bulk-form'));
            formData.append("target_folder", targetFolder);

            // Visuelles sofort-Verschieben
            checked.forEach(cb => cb.closest('tr').style.display = 'none');
            closeFullscreenView();

            fetch("action.php?action=bulk_move&folder=" + encodeURIComponent(currentFolderRaw), {
                method: 'POST',
                body: formData
            });
        }

        function actionArchive() {
            if(sysArchiveFolder) actionMove(sysArchiveFolder); 
            else customAlert(l.js_no_archive);
        }
        
        function actionSpam() {
            if(sysSpamFolder) actionMove(sysSpamFolder); 
            else customAlert(l.js_no_spam);
        }

        function actionPrint() {
            var iframe = document.getElementById('content');
            if(iframe && iframe.src !== 'about:blank') iframe.contentWindow.print();
        }

        function actionExport() {
            if(selectedMailId) window.location.href = 'action.php?action=export_eml&id=' + selectedMailId + '&folder=' + selectedMailFolder;
        }

        function actionShowSource() {
            if(selectedMailId) window.open('action.php?action=show_source&id=' + selectedMailId + '&folder=' + selectedMailFolder, '_blank');
        }

        function closeFullscreenView() {
            document.getElementById('split-container').classList.remove('reading-fullscreen');
            document.getElementById('content').src = 'about:blank'; 
            document.getElementById('content').style.display = 'none';
            document.getElementById('preview-subject').innerText = l.no_msg_sel;
            selectedMailId = null; 
            updateToolbarState();
            document.querySelectorAll('tr.mail-row').forEach(r => r.classList.remove('selected'));
        }
    </script>
</head>
<body>
    <div class="top-bar-dark">
        <span style="font-weight:bold;"><?= htmlspecialchars($imap_user) ?></span>
        <a href="action.php?action=logout"><span class="material-symbols-outlined" style="font-size:14px;">power_settings_new</span> <?= $l['logout'] ?></a>
    </div>

    <div class="header-nav">
        <a href="index.php" class="logo" style="text-decoration: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="var(--rc-blue)"><path d="M12 2L2 7l10 5 10-5-10-5zm0 11.5l-10-5v10l10 5 10-5v-10l-10 5z"/></svg>
            CubeLite
        </a>
        <div class="header-actions">
            <a id="nav-btn-mail" class="active-app" onclick="switchAppView('mail')">
                <span class="material-symbols-outlined">mail</span> <?= $l['mail'] ?>
            </a>
            <a id="nav-btn-contacts" onclick="switchAppView('contacts')">
                <span class="material-symbols-outlined">person</span> <?= $l['contacts'] ?>
            </a>
            <a id="nav-btn-settings" onclick="switchAppView('settings')">
                <span class="material-symbols-outlined">settings</span> <?= $l['settings'] ?>
            </a>
        </div>
    </div>

    <!-- ==============================================
         APP 1: MAIL ANSICHT (Standard)
         ============================================== -->
    <div class="main-toolbar" id="toolbar-mail">
        <div class="tb-btn" onclick="actionRefresh()" title="<?= $l['refresh'] ?>">
            <span class="material-symbols-outlined">sync</span>
            <span class="label"><?= $l['refresh'] ?></span>
        </div>
        <div class="tb-btn" onclick="actionCompose()" title="<?= $l['compose'] ?>">
            <span class="material-symbols-outlined">edit_square</span>
            <span class="label"><?= $l['compose'] ?></span>
        </div>
        
        <div class="tb-separator"></div>
        
        <div class="tb-btn requires-selection single-only disabled" title="<?= $l['reply'] ?>" onclick="actionReply('reply')">
            <span class="material-symbols-outlined">reply</span>
            <span class="label"><?= $l['reply'] ?></span>
        </div>
        <div class="tb-btn requires-selection single-only disabled" title="<?= $l['reply_all'] ?>" onclick="actionReply('reply_all')">
            <span class="material-symbols-outlined">reply_all</span>
            <span class="label"><?= $l['reply_all'] ?></span>
        </div>
        <div class="tb-btn requires-selection single-only disabled" title="<?= $l['forward'] ?>" onclick="actionReply('forward')">
            <span class="material-symbols-outlined">forward</span>
            <span class="label"><?= $l['forward'] ?></span>
        </div>
        
        <div class="tb-separator"></div>
        
        <div class="tb-btn requires-selection disabled" onclick="actionDelete()" title="<?= $l['delete'] ?>">
            <span class="material-symbols-outlined">delete</span>
            <span class="label"><?= $l['delete'] ?></span>
        </div>
        <div class="tb-btn requires-selection disabled" title="<?= $l['archive'] ?>" onclick="actionArchive()">
            <span class="material-symbols-outlined">archive</span>
            <span class="label"><?= $l['archive'] ?></span>
        </div>
        <div class="tb-btn requires-selection disabled" title="<?= $l['spam'] ?>" onclick="actionSpam()">
            <span class="material-symbols-outlined">report</span>
            <span class="label"><?= $l['spam'] ?></span>
        </div>
        
        <div class="tb-btn requires-selection disabled" style="position:relative;" onclick="toggleDropdown('menu-mark', event)">
            <span class="material-symbols-outlined">brush</span>
            <span class="label"><?= $l['mark'] ?> ▾</span>
            <div class="tb-dropdown-menu" id="menu-mark">
                <div onclick="actionMark('read')"><?= $l['mark_read'] ?></div>
                <div onclick="actionMark('unread')"><?= $l['mark_unread'] ?></div>
                <div onclick="actionMark('flagged')"><?= $l['mark_flagged'] ?></div>
            </div>
        </div>

        <div class="tb-btn requires-selection disabled" style="position:relative;" onclick="toggleDropdown('menu-more', event)">
            <span class="material-symbols-outlined">more_horiz</span>
            <span class="label"><?= $l['more'] ?> ▾</span>
            <div class="tb-dropdown-menu" id="menu-more">
                <div onclick="if(selectedMailId) actionPrint()" class="single-only-menu disabled-item"><span class="material-symbols-outlined">print</span> <?= $l['print'] ?></div>
                <div onclick="if(selectedMailId) actionExport()" class="single-only-menu disabled-item"><span class="material-symbols-outlined">download</span> <?= $l['export'] ?></div>
                <div onclick="if(selectedMailId) actionShowSource()" class="single-only-menu disabled-item"><span class="material-symbols-outlined">code</span> <?= $l['source'] ?></div>
                <hr style="margin: 4px 0; border:none; border-top:1px solid #eee;">
                <div style="font-weight:bold; cursor:default; background:#f9f9f9; color:#777;"><?= $l['move_to'] ?></div>
                <?php foreach ($decoded_folders as $raw => $dec): ?>
                    <div style="padding-left: 30px;" onclick="actionMove('<?= urlencode($raw) ?>')">↳ <?= htmlspecialchars($dec) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="tb-search">
            <form method="GET" action="index.php" style="display:flex; align-items:center; margin:0; height:100%;">
                <input type="hidden" name="folder" value="<?= htmlspecialchars($current_folder) ?>">
                <select name="scope">
                    <option value="ALL" <?= $search_scope == 'ALL' ? 'selected' : '' ?>><?= $l['search_all'] ?></option>
                    <option value="SUBJECT" <?= $search_scope == 'SUBJECT' ? 'selected' : '' ?>><?= $l['search_subj'] ?></option>
                    <option value="FROM" <?= $search_scope == 'FROM' ? 'selected' : '' ?>><?= $l['search_from'] ?></option>
                    <option value="TO" <?= $search_scope == 'TO' ? 'selected' : '' ?>><?= $l['search_to'] ?></option>
                </select>
                <input type="text" name="q" placeholder="<?= $l['search_placeholder'] ?>" value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit"><span class="material-symbols-outlined">search</span></button>
            </form>
        </div>
    </div>

    <div class="main-container" id="view-mail">
        <div class="sidebar" id="sidebar">
            <ul class="folder-list">
                <?php foreach ($top_folders as $raw => $folderData): ?>
                    <li>
                        <a href="?folder=<?= urlencode($raw) ?>" class="<?= ($raw === $current_folder) ? 'active' : '' ?>">
                            <span class="material-symbols-outlined folder-icon"><?= $folderData['icon'] ?></span>
                            <?= htmlspecialchars($folderData['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div style="padding: 10px 15px; font-weight: bold; color: #777; font-size: 11px; text-transform: uppercase;"><?= $l['labels'] ?></div>
            <?= renderFolderTree($folder_tree, $current_folder) ?>
        </div>
        
        <div class="sidebar-resizer" id="sidebar-resizer"></div>

        <div class="workspace-wrap">
            <div class="list-header">
                <div style="font-weight: bold; color: #444; text-transform: uppercase;"><?= htmlspecialchars(decode_imap_utf7($current_folder)) ?></div>
                
                <div style="display:flex; align-items:center; gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:18px; cursor:pointer; color:#777;" onclick="switchLayout('none')">list</span>
                    <span class="material-symbols-outlined" style="font-size:18px; cursor:pointer; color:#777;" onclick="switchLayout('bottom')">horizontal_split</span>
                    <span class="material-symbols-outlined" style="font-size:18px; cursor:pointer; color:#777;" onclick="switchLayout('right')">vertical_split</span>
                </div>
            </div>

            <div id="split-container" class="split-view-container mode-bottom">
                <div class="list-area" id="list-area">
                    <div class="mail-list-panel" id="mail-list-panel">
                        <form id="bulk-form" method="POST" action="">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 25px; padding: 0;" class="th-center">
                                            <div class="header-settings-icon" onclick="openViewSettings()" title="<?= $l['settings'] ?>">
                                                <span class="material-symbols-outlined" style="font-size:16px;">settings</span>
                                            </div>
                                        </th>
                                        <th style="width: 25px;" class="th-center th-flag">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">star</span>
                                        </th>
                                        <th style="width: 25%;" class="resizable th-fromto"><?= $l['col_from'] ?></th>
                                        <th style="width: 45%;" class="resizable th-subj"><?= $l['col_subj'] ?></th>
                                        <th style="width: 120px;" class="resizable th-date"><?= $l['col_date'] ?></th>
                                        <th style="width: 60px; text-align:right;" class="resizable th-size"><?= $l['col_size'] ?></th>
                                        <th style="width: 20px;" class="th-center th-attach">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">attach_file</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($emails)): ?>
                                        <tr><td colspan="7" style="text-align:center; padding: 30px; color: #777;"><?= $l['empty_folder'] ?></td></tr>
                                    <?php else: ?>
                                        <?php foreach ($emails as $mail): ?>
                                            <tr class="mail-row <?= $mail['unread'] ? 'unread' : 'read' ?>" data-id="<?= $mail['id'] ?>" data-folder="<?= htmlspecialchars($mail['folder']) ?>" onclick="selectMailRow(this, event)">
                                                <td class="td-check"><input type="checkbox" name="mail_ids[]" value="<?= $mail['id'] ?>"></td>
                                                <td class="td-star <?= $mail['flagged'] ? 'active' : '' ?>" onclick="toggleStar(this, <?= $mail['id'] ?>, '<?= htmlspecialchars($mail['folder'], ENT_QUOTES) ?>', event)">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">
                                                        <?= $mail['flagged'] ? 'star' : 'star_border' ?>
                                                    </span>
                                                </td>
                                                <td class="td-from"><?= htmlspecialchars($mail['from']) ?></td>
                                                <td class="td-subj"><?= htmlspecialchars($mail['subj']) ?></td>
                                                <td class="td-date"><?= htmlspecialchars($mail['date']) ?></td>
                                                <td class="td-size"><?= htmlspecialchars($mail['size']) ?></td>
                                                <td class="td-attach">
                                                    <?php if($mail['attached']): ?><span class="material-symbols-outlined" style="font-size:14px;">attach_file</span><?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>
                    </div>
                    
                    <div class="list-footer">
                        <div class="footer-left">
                            <input type="checkbox" id="footer-select-all" onclick="toggleSelectAll(this)" style="margin-right: 8px;">
                            <select onchange="handleFooterSelection(this)">
                                <option value=""><?= $l['selection'] ?></option>
                                <option value="all"><?= $l['sel_all'] ?></option>
                                <option value="none"><?= $l['sel_none'] ?></option>
                                <option value="read"><?= $l['sel_read'] ?></option>
                                <option value="unread"><?= $l['sel_unread'] ?></option>
                            </select>
                        </div>
                        
                        <div class="footer-right">
                            <?php 
                            $end_count = min($total_msgs, ($page * $per_page));
                            $start_count = $total_msgs > 0 ? (($page - 1) * $per_page) + 1 : 0;
                            echo "<span>" . sprintf($l['msg_count'], $start_count, $end_count, $total_msgs) . "</span>"; 
                            ?>
                            <div class="footer-nav">
                                <a href="?folder=<?= urlencode($current_folder) ?>&page=1">|&lt;</a>
                                <?php if ($page > 1): ?>
                                    <a href="?folder=<?= urlencode($current_folder) ?>&page=<?= $page - 1 ?>">&lt;</a>
                                <?php else: ?>
                                    <a style="opacity:0.5; cursor:default;">&lt;</a>
                                <?php endif; ?>
                                <input type="text" value="<?= $page ?>" readonly>
                                <?php if ($end_count < $total_msgs): ?>
                                    <a href="?folder=<?= urlencode($current_folder) ?>&page=<?= $page + 1 ?>">&gt;</a>
                                <?php else: ?>
                                    <a style="opacity:0.5; cursor:default;">&gt;</a>
                                <?php endif; ?>
                                <?php $last_page = max(1, ceil($total_msgs / $per_page)); ?>
                                <a href="?folder=<?= urlencode($current_folder) ?>&page=<?= $last_page ?>">&gt;|</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="resizer" id="resizer"></div>

                <div class="mail-preview-panel" id="mail-preview-panel">
                    <div class="watermark">
                        <svg viewBox="0 0 24 24" fill="var(--text-muted)"><path d="M12 2L2 7l10 5 10-5-10-5zm0 11.5l-10-5v10l10 5 10-5v-10l-10 5z"/></svg>
                    </div>
                    <div class="preview-header">
                        <h3 id="preview-subject"><?= $l['no_msg_sel'] ?></h3>
                        <span class="material-symbols-outlined" style="cursor:pointer; color:#777; font-size:18px;" onclick="closeFullscreenView()">close</span>
                    </div>
                    <!-- LADE-SPINNER -->
                    <div class="preview-loader" id="preview-loader">
                        <div class="spinner"></div>
                        Wird geladen...
                    </div>
                    <iframe id="content" src="about:blank"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- ==============================================
         APP 2: KONTAKTE ANSICHT (SPA Modus)
         ============================================== -->
    <div class="main-toolbar" id="toolbar-contacts" style="display:none;">
        <div class="tb-btn" onclick="addContact()" title="<?= $l['contact_add'] ?>">
            <span class="material-symbols-outlined">person_add</span>
            <span class="label"><?= $l['contact_add'] ?></span>
        </div>
        <form action="action.php?action=import_contacts" method="POST" enctype="multipart/form-data" id="import-form" style="margin:0;">
            <!-- HIER IST DAS NEUE FELD: Teilt PHP mit, in welches Adressbuch importiert wird -->
            <input type="hidden" name="group_id" id="import-group-id" value="1">
            <div class="tb-btn" onclick="document.getElementById('contact-upload').click()" title="<?= $l['contact_import'] ?>">
                <span class="material-symbols-outlined">upload_file</span>
                <span class="label"><?= $l['contact_import'] ?></span>
            </div>
            <input type="file" id="contact-upload" name="contact_file" accept=".csv, .vcf" style="display:none;" onchange="document.getElementById('import-form').submit()">
        </form>
        <div class="tb-separator"></div>
        <div class="tb-btn disabled" id="btn-comp-contact" onclick="composeToContact()" title="<?= $l['compose'] ?>">
            <span class="material-symbols-outlined">edit_square</span>
            <span class="label"><?= $l['compose'] ?></span>
        </div>
        <div class="tb-btn disabled" id="btn-del-contact" onclick="deleteActiveContact()" title="<?= $l['delete'] ?>">
            <span class="material-symbols-outlined">delete</span>
            <span class="label"><?= $l['delete'] ?></span>
        </div>
    </div>

    <div class="main-container" id="view-contacts" style="display:none;">
        <div class="set-col-1" style="background:#e6eaed; position:relative;">
            <div class="set-header"><?= $l['contact_groups'] ?></div>
            <!-- DYNAMISCHE ADRESSBUCH-LISTE -->
            <ul class="set-menu" id="contact-groups-list" style="flex:1; overflow-y:auto; margin-bottom:35px;">
                <!-- Wird über fetch() gefüllt -->
            </ul>
            <div class="set-toolbar" style="position:absolute; bottom:0; left:0; right:0; border-top:1px solid var(--border-color); background:#dbe4ea; height:35px; display:flex; justify-content:space-between; align-items:center; padding:0 10px;">
                <span class="material-symbols-outlined" onclick="addAddressBook()" title="Neues Adressbuch">add</span>
                <span class="material-symbols-outlined" id="btn-del-group" onclick="deleteAddressBook()" title="Adressbuch löschen" style="display:none; color:#d9534f;">delete</span>
            </div>
        </div>
        
        <div class="set-col-2" style="background:#e6eaed; width:300px; display:flex;">
            <div class="set-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span>Kontakte</span>
                <input type="checkbox" id="contact-select-all" onclick="toggleAllContacts(this)" title="Alle auswählen" style="margin:0;">
            </div>
            <div id="contacts-col-2-list" style="overflow-y:auto; flex:1;"></div>
        </div>
        
        <div class="set-col-3" id="contacts-col-3" style="background:#fff;">
            <!-- Wird dynamisch durch showContactDetails() gefüllt -->
        </div>
    </div>

    <!-- ==============================================
         APP 3: EINSTELLUNGEN ANSICHT (SPA Modus)
         ============================================== -->
    <div class="main-toolbar" id="toolbar-settings" style="display:none;">
    </div>

    <div class="main-container" id="view-settings" style="display:none;">
        <div class="set-col-1">
            <div class="set-header"><?= $l['set_menu_title'] ?></div>
            <ul class="set-menu">
                <li id="menu-cat-settings" class="active" onclick="openSettingsCategory('settings', this)">
                    <span class="material-symbols-outlined">laptop_mac</span> <?= $l['set_cat_settings'] ?>
                </li>
                <li id="menu-cat-folders" onclick="openSettingsCategory('folders', this)">
                    <span class="material-symbols-outlined">folder</span> <?= $l['set_cat_folders'] ?>
                </li>
                <li id="menu-cat-identities" onclick="openSettingsCategory('identities', this)">
                    <span class="material-symbols-outlined">person</span> <?= $l['set_cat_identities'] ?>
                </li>
                <li id="menu-cat-responses" onclick="openSettingsCategory('responses', this)">
                    <span class="material-symbols-outlined">description</span> <?= $l['set_cat_responses'] ?>
                </li>
                <li id="menu-cat-userinfo" onclick="openSettingsCategory('userinfo', this)">
                    <span class="material-symbols-outlined">settings</span> <?= $l['set_cat_userinfo'] ?>
                </li>
            </ul>
        </div>
        
        <div class="set-col-2" id="set-col-2">
            <div class="set-header" id="set-col-2-title">Bereich</div>
            <div id="set-col-2-list" style="overflow-y:auto; flex:1;"></div>
            <div class="set-toolbar" id="set-col-2-toolbar"></div>
        </div>
        
        <div class="set-col-3" id="set-col-3">
            <!-- Wird dynamisch gefüllt -->
        </div>
    </div>

    <!-- ==============================================
         MODALS (Verfassen, Einstellungen & Eigene Dialoge)
         ============================================== -->
    
    <!-- Custom Alert / Confirm / Prompt Modal -->
    <div id="custom-dialog-backdrop" class="modal-backdrop" style="z-index: 9999;">
        <div class="modal" style="width: 400px; height: auto; min-height: 150px; text-align: center; border-radius: 8px;">
            <div class="modal-header" style="justify-content: center; background: var(--rc-blue); color: white; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <span id="custom-dialog-title"><?= $l['dialog_info'] ?></span>
            </div>
            <div class="compose-body" style="padding: 30px 20px; font-size: 15px; justify-content: center; flex: 1; display: flex; flex-direction:column; align-items: center; color: #333;">
                <span id="custom-dialog-msg"></span>
                <div id="custom-dialog-input-container" style="display:none; margin-top:15px; width:100%;">
                    <input type="text" id="custom-dialog-input" class="form-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>
            <div class="modal-toolbar" style="justify-content: center; gap: 15px; padding: 15px; background: #f4f4f4; border-top: 1px solid #ccc; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <button id="custom-dialog-btn-yes" class="btn-dark" style="min-width: 100px; padding: 8px 15px;"><?= $l['dialog_ok'] ?></button>
                <button id="custom-dialog-btn-no" class="btn-grey" style="min-width: 100px; padding: 8px 15px;"><?= $l['dialog_cancel'] ?></button>
            </div>
        </div>
    </div>

    <div id="compose-modal" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <span><?= $l['comp_title'] ?></span>
                <span class="modal-close" onclick="closeCompose()">✕</span>
            </div>
            <div class="modal-toolbar">
                <button onclick="document.getElementById('compose-form').submit();"><span class="material-symbols-outlined" style="font-size:18px;">send</span> <?= $l['comp_send'] ?></button>
                <button onclick="closeCompose()"><span class="material-symbols-outlined" style="font-size:18px;">save</span> <?= $l['comp_save'] ?></button>
                <button><label for="att-file" style="cursor:pointer; display:flex; align-items:center; gap:5px;"><span class="material-symbols-outlined" style="font-size:18px;">attach_file</span> <?= $l['comp_attach'] ?></label></button>
                <button onclick="closeCompose()"><span class="material-symbols-outlined" style="font-size:18px;">cancel</span> <?= $l['comp_cancel'] ?></button>
            </div>
            <form id="compose-form" action="action.php?action=send&folder=<?= urlencode($current_folder) ?>" method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; flex:1;">
                <div class="compose-body">
                    <div class="form-row">
                        <div class="form-label"><?= $l['comp_to'] ?></div>
                        <input type="text" name="to" class="form-input" required list="contacts-datalist">
                    </div>
                    <div class="form-row">
                        <div class="form-label"><?= $l['comp_subj'] ?></div>
                        <input type="text" name="subject" class="form-input" required>
                    </div>
                    <div class="editor-wrap">
                        <textarea id="compose-editor" name="body"></textarea>
                    </div>
                    <input type="file" id="att-file" name="attachments[]" multiple style="display:none;" onchange="customAlert(this.files.length + ' <?= $l['js_files'] ?>')">
                </div>
            </form>
        </div>
    </div>

    <!-- View Settings Modal -->
    <div id="view-settings-modal" class="modal-backdrop">
        <div class="modal view-settings-modal">
            <span class="modal-close" style="position:absolute; top: 15px; right: 15px; cursor: pointer; z-index: 100;" onclick="document.getElementById('view-settings-modal').style.display='none'">✕</span>
            
            <div class="view-columns">
                <div class="view-col">
                    <div class="view-col-title"><?= $l['view_layout'] ?></div>
                    <ul class="view-col-list">
                        <li><input type="radio" name="layout_rb" id="rb_wide" value="right"> <label for="rb_wide"><?= $l['lay_wide'] ?></label></li>
                        <li><input type="radio" name="layout_rb" id="rb_desk" value="bottom"> <label for="rb_desk"><?= $l['lay_desk'] ?></label></li>
                        <li><input type="radio" name="layout_rb" id="rb_list" value="none"> <label for="rb_list"><?= $l['lay_list'] ?></label></li>
                    </ul>
                </div>
                <div class="view-col">
                    <div class="view-col-title"><?= $l['view_cols'] ?></div>
                    <ul class="view-col-list">
                        <li class="disabled"><input type="checkbox" checked disabled> <label><?= $l['col_conv'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_fromto"> <label for="cb_col_fromto"><?= $l['col_from_to'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_subj"> <label for="cb_col_subj"><?= $l['col_subj'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_date"> <label for="cb_col_date"><?= $l['col_date'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_size"> <label for="cb_col_size"><?= $l['col_size'] ?></label></li>
                        <li class="disabled"><input type="checkbox" checked disabled> <label><?= $l['col_read'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_attach"> <label for="cb_col_attach"><?= $l['col_attach'] ?></label></li>
                        <li><input type="checkbox" id="cb_col_flag"> <label for="cb_col_flag"><?= $l['col_flag'] ?></label></li>
                    </ul>
                </div>
                <div class="view-col">
                    <div class="view-col-title"><?= $l['view_sort_by'] ?></div>
                    <ul class="view-col-list">
                        <li><input type="radio" name="sort_by" id="sb_none" value="none"> <label for="sb_none"><?= $l['sort_none'] ?></label></li>
                        <li><input type="radio" name="sort_by" id="sb_recv" value="recv"> <label for="sb_recv"><?= $l['sort_recv'] ?></label></li>
                        <li><input type="radio" name="sort_by" id="sb_sent" value="sent"> <label for="sb_sent"><?= $l['sort_sent'] ?></label></li>
                        <li><input type="radio" name="sort_by" id="sb_subj" value="subj"> <label for="sb_subj"><?= $l['col_subj'] ?></label></li>
                        <li><input type="radio" name="sort_by" id="sb_from" value="from"> <label for="sb_from"><?= $l['col_from_to'] ?></label></li>
                        <li><input type="radio" name="sort_by" id="sb_size" value="size"> <label for="sb_size"><?= $l['col_size'] ?></label></li>
                    </ul>
                </div>
                <div class="view-col">
                    <div class="view-col-title"><?= $l['view_sort_order'] ?></div>
                    <ul class="view-col-list">
                        <li><input type="radio" name="sort_ord" id="so_asc" value="ASC"> <label for="so_asc"><?= $l['ord_asc'] ?></label></li>
                        <li><input type="radio" name="sort_ord" id="so_desc" value="DESC"> <label for="so_desc"><?= $l['ord_desc'] ?></label></li>
                    </ul>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="btn-dark" onclick="saveViewSettings()"><?= $l['btn_save'] ?></button>
                <button class="btn-grey" onclick="document.getElementById('view-settings-modal').style.display='none'"><?= $l['btn_cancel'] ?></button>
            </div>
        </div>
    </div>
</body>
</html>