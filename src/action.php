<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['imap_user'])) {
    header("Location: index.php");
    exit;
}

require_once 'db.php';

$imap_host = $_SESSION['imap_host'];
$smtp_host = isset($_SESSION['smtp_host']) ? $_SESSION['smtp_host'] : str_replace('imap', 'smtp', $imap_host);
$imap_user = $_SESSION['imap_user'];
$imap_pass = $_SESSION['imap_pass'];

$folder = isset($_GET['folder']) ? $_GET['folder'] : 'INBOX';
$server_base = "{" . $imap_host . ":993/imap/ssl}";

function send_via_smtp($smtp_host, $smtp_user, $smtp_pass, $to, $headers, $message_body) {
    $port = 465;
    $host = "ssl://" . $smtp_host;
    $socket = @stream_socket_client($host . ":" . $port, $errno, $errstr, 5);
    if (!$socket) {
        $port = 587;
        $host = "tcp://" . $smtp_host;
        $socket = @stream_socket_client($host . ":" . $port, $errno, $errstr, 5);
        if (!$socket) return false;
    }
    stream_set_timeout($socket, 5);
    function read_res($socket, $expected) {
        $res = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $res .= $line;
            if (strlen($line) >= 4 && substr($line, 3, 1) == ' ') break;
        }
        return (substr($res, 0, 3) == $expected);
    }
    read_res($socket, "220"); fwrite($socket, "EHLO " . $smtp_host . "\r\n"); read_res($socket, "250");
    if ($port == 587) {
        fwrite($socket, "STARTTLS\r\n");
        if (read_res($socket, "220")) {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO " . $smtp_host . "\r\n"); read_res($socket, "250");
        }
    }
    fwrite($socket, "AUTH LOGIN\r\n"); read_res($socket, "334");
    fwrite($socket, base64_encode($smtp_user) . "\r\n"); read_res($socket, "334");
    fwrite($socket, base64_encode($smtp_pass) . "\r\n");
    if (!read_res($socket, "235")) { fclose($socket); return false; }
    fwrite($socket, "MAIL FROM:<" . $smtp_user . ">\r\n"); read_res($socket, "250");
    $recipients = explode(',', $to);
    foreach($recipients as $recp) {
        $clean_rcpt = preg_replace('/.*<([^>]+)>.*/', '$1', trim($recp));
        fwrite($socket, "RCPT TO:<" . trim($clean_rcpt) . ">\r\n"); read_res($socket, "250");
    }
    fwrite($socket, "DATA\r\n"); read_res($socket, "354");
    $full_email = $headers . "\r\n" . $message_body;
    fwrite($socket, $full_email . "\r\n.\r\n");
    $success = read_res($socket, "250");
    fwrite($socket, "QUIT\r\n"); fclose($socket); return $success;
}

if (isset($_GET['action'])) {
    
    // --- ADRESSBUCH GRUPPEN ---
    if ($_GET['action'] == 'get_contact_groups') {
        $stmt = $db->prepare("SELECT * FROM contact_groups WHERE user_email = ? ORDER BY name ASC");
        $stmt->execute([$imap_user]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($groups)) {
            $db->prepare("INSERT INTO contact_groups (user_email, name) VALUES (?, ?)")->execute([$imap_user, 'Persönliches Adressbuch']);
            $stmt->execute([$imap_user]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        header('Content-Type: application/json'); echo json_encode($groups); exit;
    }

    if ($_GET['action'] == 'add_contact_group' && isset($_POST['name'])) {
        $name = trim($_POST['name']);
        if ($name) {
            $db->prepare("INSERT INTO contact_groups (user_email, name) VALUES (?, ?)")->execute([$imap_user, $name]);
        }
        exit;
    }

    if ($_GET['action'] == 'delete_contact_group' && isset($_POST['group_id'])) {
        $group_id = (int)$_POST['group_id'];
        
        // Finde die ID des Standard-Adressbuchs (die älteste Gruppe dieses Nutzers)
        $stmt = $db->prepare("SELECT id FROM contact_groups WHERE user_email = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$imap_user]);
        $default_group_id = $stmt->fetchColumn();
        
        // Verhindere das Löschen des Haupt-Adressbuchs
        if ($group_id != $default_group_id) {
            // Verschiebe alle Kontakte aus der gelöschten Gruppe in die Standardgruppe
            $stmt = $db->prepare("UPDATE contacts SET group_id = ? WHERE group_id = ? AND user_email = ?");
            $stmt->execute([$default_group_id, $group_id, $imap_user]);
            
            // Lösche die Gruppe
            $stmt = $db->prepare("DELETE FROM contact_groups WHERE id = ? AND user_email = ?");
            $stmt->execute([$group_id, $imap_user]);
        }
        exit;
    }

    // --- KONTAKTE ---
    if ($_GET['action'] == 'get_contacts') {
        $stmt = $db->prepare("SELECT id, name, email, phone, address, notes, avatar, gender, birthday, website, im_address, group_id FROM contacts WHERE user_email = ? ORDER BY name ASC, email ASC");
        $stmt->execute([$imap_user]);
        header('Content-Type: application/json'); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }
    
    if ($_GET['action'] == 'delete_contact' && isset($_GET['id'])) {
        $stmt = $db->prepare("DELETE FROM contacts WHERE id = ? AND user_email = ?");
        $stmt->execute([(int)$_GET['id'], $imap_user]); exit;
    }

    if ($_GET['action'] == 'bulk_delete_contacts' && isset($_POST['contact_ids'])) {
        $ids = $_POST['contact_ids'];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$imap_user]);
            $stmt = $db->prepare("DELETE FROM contacts WHERE id IN ($placeholders) AND user_email = ?");
            $stmt->execute($params);
        }
        exit;
    }
    
    if ($_GET['action'] == 'edit_contact') {
        $id = (int)$_POST['id'];
        $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 1;
        $name = trim(trim($_POST['name'] ?? '') . ' ' . trim($_POST['lastname'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $email_val = $email === '' ? null : strtolower($email);
        $phone = trim($_POST['phone'] ?? ''); $notes = trim($_POST['notes'] ?? ''); $avatar = $_POST['avatar'] ?? ''; $gender = trim($_POST['gender'] ?? ''); $birthday = trim($_POST['birthday'] ?? ''); $website = trim($_POST['website'] ?? ''); $im_address = trim($_POST['im_address'] ?? '');
        
        $address = json_encode(['street' => trim($_POST['street'] ?? ''), 'zip' => trim($_POST['zip'] ?? ''), 'city' => trim($_POST['city'] ?? ''), 'country' => trim($_POST['country'] ?? ''), 'region' => trim($_POST['region'] ?? '')]);
        
        try {
            if ($id === 0) {
                $stmt = $db->prepare("INSERT OR IGNORE INTO contacts (user_email, name, email, phone, address, notes, avatar, gender, birthday, website, im_address, group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$imap_user, $name, $email_val, $phone, $address, $notes, $avatar, $gender, $birthday, $website, $im_address, $group_id]);
            } else {
                $stmt = $db->prepare("UPDATE OR IGNORE contacts SET name=?, email=?, phone=?, address=?, notes=?, avatar=?, gender=?, birthday=?, website=?, im_address=?, group_id=? WHERE id=? AND user_email=?");
                $stmt->execute([$name, $email_val, $phone, $address, $notes, $avatar, $gender, $birthday, $website, $im_address, $group_id, $id, $imap_user]);
            }
        } catch (Exception $e) {}
        exit;
    }
    
    if ($_GET['action'] == 'import_contacts') {
        $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 1;
        if (isset($_FILES['contact_file']) && is_uploaded_file($_FILES['contact_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['contact_file']['name'], PATHINFO_EXTENSION));
            $stmt = $db->prepare("INSERT OR IGNORE INTO contacts (user_email, name, email, phone, address, group_id) VALUES (?, ?, ?, ?, ?, ?)");
            $added = 0;
            $content = file_get_contents($_FILES['contact_file']['tmp_name']);
            if (function_exists('mb_detect_encoding')) {
                $enc = mb_detect_encoding($content, 'UTF-8, UTF-16LE, UTF-16BE, ISO-8859-1, Windows-1252', true);
                if ($enc && $enc !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $enc);
            }
            if ($ext == 'vcf') {
                $content = str_replace(["\r\n", "\r"], "\n", $content);
                preg_match_all('/BEGIN:VCARD.*?END:VCARD/s', $content, $cards);
                foreach($cards[0] as $card) {
                    preg_match('/FN[^\:]*\:(.*)/', $card, $name_match); preg_match('/EMAIL[^\:]*\:(.*)/', $card, $email_match);
                    $name = !empty($name_match[1]) ? trim($name_match[1]) : ''; $email = !empty($email_match[1]) ? strtolower(trim($email_match[1])) : null;
                    if (!empty($name) || !empty($email)) { $stmt->execute([$imap_user, $name, $email, '', '{}', $group_id]); $added++; }
                }
            } else {
                $stream = fopen('php://memory', 'r+'); fwrite($stream, $content); rewind($stream);
                $first_line = fgets($stream); $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ','; rewind($stream);
                $header = fgetcsv($stream, 0, $delimiter);
                $col = ['name'=>-1, 'fname'=>-1, 'lname'=>-1, 'email'=>-1, 'phone'=>-1, 'street'=>-1, 'city'=>-1, 'zip'=>-1, 'country'=>-1, 'region'=>-1];
                if(is_array($header)) {
                    foreach($header as $i => $h) {
                        $h = strtolower(trim((string)$h, " \t\n\r\0\x0B\xEF\xBB\xBF\"'"));
                        if(in_array($h, ['name','display name','anzeigename'])) $col['name'] = $i;
                        elseif(in_array($h, ['first name','vorname','given name'])) $col['fname'] = $i;
                        elseif(in_array($h, ['last name','nachname','family name'])) $col['lname'] = $i;
                        elseif(in_array($h, ['e-mail address','e-mail 1 - value','e-mail','email','e-mail-adresse'])) $col['email'] = $i;
                        elseif(in_array($h, ['primary phone','mobile phone','phone 1 - value','telefon (mobil)','telefon (privat)','telefon'])) $col['phone'] = $i;
                        elseif(in_array($h, ['business street','home street','address 1 - street','straße (geschäftlich)','straße (privat)','straße'])) $col['street'] = $i;
                        elseif(in_array($h, ['business city','home city','address 1 - city','ort (geschäftlich)','ort (privat)','ort'])) $col['city'] = $i;
                        elseif(in_array($h, ['business postal code','home postal code','address 1 - po box','postleitzahl'])) $col['zip'] = $i;
                        elseif(in_array($h, ['business country/region','home country/region','address 1 - country','land'])) $col['country'] = $i;
                        elseif(in_array($h, ['business state','home state','address 1 - region','bundesland'])) $col['region'] = $i;
                    }
                }
                while (($data = fgetcsv($stream, 0, $delimiter)) !== FALSE) {
                    if(!is_array($data) || empty(implode('',$data))) continue;
                    $email = ($col['email'] !== -1 && isset($data[$col['email']])) ? trim($data[$col['email']]) : '';
                    if(empty($email)) { foreach($data as $val) { if(filter_var(trim($val), FILTER_VALIDATE_EMAIL)) { $email = strtolower(trim($val)); break; } } }
                    $name = '';
                    if($col['name'] !== -1 && isset($data[$col['name']])) $name = trim($data[$col['name']]);
                    elseif($col['fname'] !== -1 || $col['lname'] !== -1) {
                        $f = ($col['fname'] !== -1 && isset($data[$col['fname']])) ? trim($data[$col['fname']]) : '';
                        $l = ($col['lname'] !== -1 && isset($data[$col['lname']])) ? trim($data[$col['lname']]) : '';
                        $name = trim($f . ' ' . $l);
                    } else { if(!empty($data[0]) && stripos($data[0], '@') === false) $name = trim($data[0]); }
                    $phone = ($col['phone'] !== -1 && isset($data[$col['phone']])) ? trim($data[$col['phone']]) : '';
                    $addr = ['street' => ($col['street'] !== -1 && isset($data[$col['street']])) ? trim($data[$col['street']]) : '', 'city' => ($col['city'] !== -1 && isset($data[$col['city']])) ? trim($data[$col['city']]) : '', 'zip' => ($col['zip'] !== -1 && isset($data[$col['zip']])) ? trim($data[$col['zip']]) : '', 'country' => ($col['country'] !== -1 && isset($data[$col['country']])) ? trim($data[$col['country']]) : '', 'region' => ($col['region'] !== -1 && isset($data[$col['region']])) ? trim($data[$col['region']]) : ''];
                    if (!empty($name) || !empty($email)) {
                        $email_val = empty($email) ? null : strtolower($email);
                        $stmt->execute([$imap_user, $name, $email_val, $phone, json_encode($addr), $group_id]);
                        $added++;
                    }
                }
                fclose($stream);
            }
            header("Location: index.php?msg=import_success&count=" . $added); exit;
        }
        exit;
    }

    if ($_GET['action'] == 'get_identities') {
        $stmt = $db->prepare("SELECT * FROM identities WHERE user_email = ?"); $stmt->execute([$imap_user]); $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(empty($ids)) {
            $stmt = $db->prepare("INSERT INTO identities (user_email, display_name, email, is_default) VALUES (?, ?, ?, 1)"); $stmt->execute([$imap_user, $imap_user, $imap_user]);
            $stmt = $db->prepare("SELECT * FROM identities WHERE user_email = ?"); $stmt->execute([$imap_user]); $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        header('Content-Type: application/json'); echo json_encode($ids); exit;
    }

    if ($_GET['action'] == 'save_identity') {
        $id = (int)$_POST['id']; $dname = trim($_POST['display_name']); $email = trim($_POST['email']); $org = trim($_POST['organization']); $reply = trim($_POST['reply_to']); $bcc = trim($_POST['bcc']); $sig = trim($_POST['signature']);
        $is_html = isset($_POST['is_html_sig']) && $_POST['is_html_sig'] == '1' ? 1 : 0; $is_default = isset($_POST['is_default']) && $_POST['is_default'] == '1' ? 1 : 0;
        if ($is_default) $db->prepare("UPDATE identities SET is_default = 0 WHERE user_email = ?")->execute([$imap_user]);
        if ($id === 0) { $stmt = $db->prepare("INSERT INTO identities (user_email, display_name, email, organization, reply_to, bcc, signature, is_html_sig, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"); $stmt->execute([$imap_user, $dname, $email, $org, $reply, $bcc, $sig, $is_html, $is_default]); } 
        else { $stmt = $db->prepare("UPDATE identities SET display_name=?, email=?, organization=?, reply_to=?, bcc=?, signature=?, is_html_sig=?, is_default=? WHERE id=? AND user_email=?"); $stmt->execute([$dname, $email, $org, $reply, $bcc, $sig, $is_html, $is_default, $id, $imap_user]); }
        exit;
    }

    if ($_GET['action'] == 'get_quick_replies') {
        $stmt = $db->prepare("SELECT * FROM quick_replies WHERE user_email = ?"); $stmt->execute([$imap_user]);
        header('Content-Type: application/json'); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    if ($_GET['action'] == 'save_quick_reply') {
        $id = (int)$_POST['id']; $name = trim($_POST['name']); $body = trim($_POST['body']);
        if ($id === 0) { $stmt = $db->prepare("INSERT INTO quick_replies (user_email, name, body) VALUES (?, ?, ?)"); $stmt->execute([$imap_user, $name, $body]); } 
        else { $stmt = $db->prepare("UPDATE quick_replies SET name=?, body=? WHERE id=? AND user_email=?"); $stmt->execute([$name, $body, $id, $imap_user]); }
        exit;
    }

    if ($_GET['action'] == 'save_folders') {
        $visible = isset($_POST['visible_folders']) ? $_POST['visible_folders'] : [];
        $stmt = $db->prepare("SELECT folder_raw FROM cache_folders WHERE user_email = ?"); $stmt->execute([$imap_user]); $all_folders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hidden = array_diff($all_folders, $visible); $hidden_json = json_encode(array_values($hidden));
        $stmt = $db->prepare("UPDATE settings SET hidden_folders = ? WHERE user_email = ?"); $stmt->execute([$hidden_json, $imap_user]);
        header("Location: index.php?folder=" . urlencode($folder)); exit;
    }

    if ($_GET['action'] == 'save_settings') {
        $stmt = $db->prepare("SELECT * FROM settings WHERE user_email = ?"); $stmt->execute([$imap_user]); $curr = $stmt->fetch(PDO::FETCH_ASSOC);
        $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : (int)$curr['per_page']; if ($per_page < 1) $per_page = 25; 
        $signature = isset($_POST['signature']) ? $_POST['signature'] : $curr['signature']; $archive_folder = isset($_POST['archive_folder']) ? $_POST['archive_folder'] : $curr['archive_folder']; $language = isset($_POST['language']) ? $_POST['language'] : $curr['language']; 
        $stmt = $db->prepare("UPDATE settings SET per_page = ?, signature = ?, archive_folder = ?, language = ? WHERE user_email = ?"); $stmt->execute([$per_page, $signature, $archive_folder, $language, $imap_user]);
        header("Location: index.php?folder=" . urlencode($folder)); exit;
    }

    if ($_GET['action'] == 'refresh_cache') {
        $stmt = $db->prepare("DELETE FROM cache_folders WHERE user_email = ?"); $stmt->execute([$imap_user]);
        header("Location: index.php?folder=" . urlencode($folder)); exit;
    }

    $server = $server_base . $folder;
    $inbox = @imap_open($server, $imap_user, $imap_pass);
    
    if ($inbox) {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($_GET['action'] == 'delete' && $id) { imap_delete($inbox, $id); imap_expunge($inbox); }
        elseif ($_GET['action'] == 'bulk_delete' && isset($_POST['mail_ids'])) {
            foreach ($_POST['mail_ids'] as $mail_id) { imap_delete($inbox, $mail_id); } imap_expunge($inbox);
        }
        elseif ($_GET['action'] == 'bulk_move' && isset($_POST['mail_ids']) && !empty($_POST['target_folder'])) {
            $target = urldecode($_POST['target_folder']); $sequence = implode(',', $_POST['mail_ids']);
            imap_mail_move($inbox, $sequence, $target); imap_expunge($inbox);
        }
        elseif ($_GET['action'] == 'mark_unread' && $id) imap_clearflag_full($inbox, $id, "\\Seen");
        elseif ($_GET['action'] == 'mark_read' && $id) imap_setflag_full($inbox, $id, "\\Seen");
        elseif ($_GET['action'] == 'mark_flagged' && $id) imap_setflag_full($inbox, $id, "\\Flagged");
        elseif ($_GET['action'] == 'unmark_flagged' && $id) imap_clearflag_full($inbox, $id, "\\Flagged");
        elseif ($_GET['action'] == 'export_eml' && $id) {
            header('Content-Type: message/rfc822'); header('Content-Disposition: attachment; filename="email_'.$id.'.eml"');
            echo imap_fetchheader($inbox, $id) . imap_body($inbox, $id); imap_close($inbox); exit;
        }
        elseif ($_GET['action'] == 'show_source' && $id) {
            header('Content-Type: text/plain; charset=UTF-8'); echo imap_fetchheader($inbox, $id) . imap_body($inbox, $id); imap_close($inbox); exit;
        }
        elseif ($_GET['action'] == 'get_mail_data' && $id) {
            $head = imap_headerinfo($inbox, $id); $struct = imap_fetchstructure($inbox, $id);
            function get_part_action($imap, $id, $part, $part_no) {
                $data = ($part_no) ? imap_fetchbody($imap, $id, $part_no) : imap_body($imap, $id);
                if ($part->encoding == 3) $data = base64_decode($data); elseif ($part->encoding == 4) $data = quoted_printable_decode($data); return $data;
            }
            function find_msg_action($imap, $id, $struct, $part_no = "") {
                $html = ""; $plain = "";
                if ($struct->type == 0) {
                    $data = get_part_action($imap, $id, $struct, $part_no ?: "1");
                    if (strtoupper($struct->subtype) == "HTML") return ["html" => $data]; else return ["plain" => $data];
                }
                if (isset($struct->parts)) {
                    foreach ($struct->parts as $index => $sub_struct) {
                        $prefix = $part_no ? $part_no . "." : ""; $res = find_msg_action($imap, $id, $sub_struct, $prefix . ($index + 1));
                        if (isset($res["html"]) && !$html) $html = $res["html"]; elseif (isset($res["plain"]) && !$plain) $plain = $res["plain"];
                    }
                }
                return ["html" => $html, "plain" => $plain];
            }
            $res = find_msg_action($inbox, $id, $struct); $body_content = !empty($res['html']) ? $res['html'] : nl2br(htmlspecialchars($res['plain']));
            $from_mail = strtolower($head->from[0]->mailbox . "@" . $head->from[0]->host); $from_name = isset($head->from[0]->personal) ? imap_utf8($head->from[0]->personal) : $from_mail;
            $stmt_col = $db->prepare("INSERT OR IGNORE INTO contacts (user_email, name, email) VALUES (?, ?, ?)"); $stmt_col->execute([$imap_user, $from_name, $from_mail]);
            $to_mail = ''; if(isset($head->to)) { $to_arr = []; foreach($head->to as $t) { $to_arr[] = strtolower($t->mailbox . "@" . $t->host); } $to_mail = implode(', ', $to_arr); }
            $subject = isset($head->subject) ? imap_utf8($head->subject) : ''; $date = date("d.m.Y H:i", $head->udate);
            header('Content-Type: application/json'); echo json_encode(['from' => $from_mail, 'from_name' => $from_name, 'to' => $to_mail, 'subject' => $subject, 'date' => $date, 'body' => $body_content]); imap_close($inbox); exit;
        }
        elseif ($_GET['action'] == 'download' && $id && isset($_GET['part'])) {
            $part = $_GET['part']; $filename = isset($_GET['file']) ? $_GET['file'] : 'download'; $struct = imap_fetchstructure($inbox, $id);
            $encoding = 0; if (isset($struct->parts) && isset($struct->parts[$part-1])) { $encoding = $struct->parts[$part-1]->encoding; }
            $data = imap_fetchbody($inbox, $id, $part); if ($encoding == 3) { $data = base64_decode($data); } elseif ($encoding == 4) { $data = quoted_printable_decode($data); }
            header('Content-Description: File Transfer'); header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($filename).'"');
            header('Expires: 0'); header('Cache-Control: must-revalidate'); header('Content-Length: ' . strlen($data)); echo $data; imap_close($inbox); exit;
        }
        elseif ($_GET['action'] == 'send') {
            $to = $_POST['to']; $subject = $_POST['subject']; $body = $_POST['body']; $boundary = md5(time());
            $stmt_col = $db->prepare("INSERT OR IGNORE INTO contacts (user_email, name, email) VALUES (?, ?, ?)"); $recps = explode(',', $to);
            foreach($recps as $r) {
                if (preg_match('/(?:(.*)<)?([^>]+@[^>]+)>?/', trim($r), $m)) { $c_name = trim(trim($m[1]), ' "'); $c_mail = strtolower(trim($m[2])); $stmt_col->execute([$imap_user, $c_name, $c_mail]); } else { $stmt_col->execute([$imap_user, '', strtolower(trim($r))]); }
            }
            $headers = "From: " . $imap_user . "\r\nReply-To: " . $imap_user . "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"";
            $message = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n";
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                    if (is_uploaded_file($tmp_name)) {
                        $name = $_FILES['attachments']['name'][$i]; $type = $_FILES['attachments']['type'][$i];
                        $message .= "--$boundary\r\nContent-Type: $type; name=\"$name\"\r\nContent-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode(file_get_contents($tmp_name))) . "\r\n";
                    }
                }
            }
            $message .= "--$boundary--";
            $mail_sent = send_via_smtp($smtp_host, $imap_user, $imap_pass, $to, "To: $to\r\nSubject: $subject\r\n" . $headers, $message);
            if ($mail_sent) { $full_email = "To: $to\r\nSubject: $subject\r\n" . $headers . "\r\n\r\n" . $message; @imap_append($inbox, $server_base . '[Gmail]/Gesendet', $full_email, "\\Seen"); header("Location: index.php"); } else { header("Location: index.php?msg=smtp_error&host=" . urlencode($smtp_host)); exit; }
            exit;
        }
        elseif ($_GET['action'] == 'logout') { session_destroy(); header("Location: index.php"); exit; }
        if (!in_array($_GET['action'], ['logout', 'send', 'download', 'export_eml', 'show_source', 'get_mail_data'])) { imap_close($inbox); }
    }
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { http_response_code(200); exit; }
header("Location: index.php?folder=" . urlencode($folder)); exit;
?>