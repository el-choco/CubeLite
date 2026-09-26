<?php
session_start();
if (!isset($_SESSION['imap_user'])) exit('Nicht eingeloggt');

$imap_host = $_SESSION['imap_host'];
$imap_user = $_SESSION['imap_user'];
$imap_pass = $_SESSION['imap_pass'];

function get_part($imap, $id, $part, $part_no) {
    $data = ($part_no) ? imap_fetchbody($imap, $id, $part_no) : imap_body($imap, $id);
    if ($part->encoding == 3) { $data = imap_base64($data); }
    elseif ($part->encoding == 4) { $data = imap_qprint($data); }
    return $data;
}

function find_msg($imap, $id, $struct, $part_no = "") {
    $html = ""; $plain = "";
    if ($struct->type == 0) {
        $data = get_part($imap, $id, $struct, $part_no ?: "1");
        if (strtoupper($struct->subtype) == "HTML") return array("html" => $data);
        else return array("plain" => $data);
    }
    if (isset($struct->parts)) {
        foreach ($struct->parts as $index => $sub_struct) {
            $prefix = $part_no ? $part_no . "." : "";
            $sub_part_no = $prefix . ($index + 1);
            $res = find_msg($imap, $id, $sub_struct, $sub_part_no);
            if (isset($res["html"]) && !$html) { $html = $res["html"]; }
            elseif (isset($res["plain"]) && !$plain) { $plain = $res["plain"]; }
        }
    }
    return array("html" => $html, "plain" => $plain);
}

function get_attachments($struct) {
    $attachments = [];
    if (isset($struct->parts)) {
        foreach ($struct->parts as $i => $part) {
            $part_no = $i + 1;
            $filename = '';
            if (isset($part->ifdparameters) && $part->ifdparameters) {
                foreach ($part->dparameters as $obj) {
                    if (strtolower($obj->attribute) == 'filename') $filename = $obj->value;
                }
            }
            if (!$filename && isset($part->ifparameters) && $part->ifparameters) {
                foreach ($part->parameters as $obj) {
                    if (strtolower($obj->attribute) == 'name') $filename = $obj->value;
                }
            }
            if ($filename) {
                // Decode mime words like =?UTF-8?Q?name?=
                $decoded_name = imap_mime_header_decode($filename);
                $clean_name = '';
                foreach ($decoded_name as $part_name) $clean_name .= $part_name->text;
                $attachments[] = ['name' => $clean_name, 'part' => $part_no];
            }
        }
    }
    return $attachments;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $folder = isset($_GET['folder']) ? $_GET['folder'] : 'INBOX';
    
    $server = "{".$imap_host.":993/imap/ssl}".$folder;
    $inbox = @imap_open($server, $imap_user, $imap_pass);
    
    if ($inbox) {
        $head = imap_headerinfo($inbox, $id);
        $struct = imap_fetchstructure($inbox, $id);
        $res = find_msg($inbox, $id, $struct);
        $attachments = get_attachments($struct);
        
        $from_name = isset($head->from[0]->personal) ? imap_utf8($head->from[0]->personal) : '';
        $from_mail = isset($head->from[0]->mailbox) ? $head->from[0]->mailbox . "@" . $head->from[0]->host : '';
        $subj = isset($head->subject) ? imap_utf8($head->subject) : '(kein Betreff)';
        $date = date("d.m.Y H:i", $head->udate);
        $avatar = $from_name ? strtoupper(substr($from_name, 0, 1)) : strtoupper(substr($from_mail, 0, 1));
        
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Roboto, system-ui, sans-serif; margin: 0; padding: 20px; color: #202124; }
            .mail-header { display: flex; gap: 16px; margin-bottom: 24px; margin-top: 24px; }
            .avatar { width: 40px; height: 40px; border-radius: 50%; background: #0b57d0; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 500; }
            .meta { flex: 1; }
            .subject { font-size: 22px; margin: 0 0 8px 0; font-weight: 400; }
            .from-line { font-size: 14px; }
            .from-name { font-weight: 600; margin-right: 4px; }
            .from-mail { color: #5f6368; }
            .date { color: #5f6368; font-size: 12px; float: right; }
            .mail-body { border-top: 1px solid #e5e7eb; padding-top: 20px; }
            .attachments { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
            .attachment-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #c4c7c5; border-radius: 20px; text-decoration: none; color: #1f1f1f; font-size: 13px; font-weight: 500; margin-right: 10px; margin-bottom: 10px; }
            .attachment-btn:hover { background: #f0f4f9; }
        </style></head><body>';
        
        echo '<div class="mail-header">';
        echo '<div class="avatar">' . htmlspecialchars($avatar) . '</div>';
        echo '<div class="meta">';
        echo '<h2 class="subject">' . htmlspecialchars($subj) . '</h2>';
        echo '<div class="from-line"><span class="from-name">' . htmlspecialchars($from_name) . '</span> <span class="from-mail">&lt;' . htmlspecialchars($from_mail) . '&gt;</span> <span class="date">' . htmlspecialchars($date) . '</span></div>';
        echo '</div></div>';
        
        echo '<div class="mail-body">';
        if (!empty($res["html"])) {
            echo $res["html"];
        } elseif (!empty($res["plain"])) {
            echo '<div style="white-space:pre-wrap;">' . htmlspecialchars($res["plain"]) . '</div>';
        } else {
            echo 'Kein Inhalt.';
        }
        echo '</div>';
        
        // Anhänge anzeigen
        if (!empty($attachments)) {
            echo '<div class="attachments">';
            echo '<strong>Anhänge:</strong><br><br>';
            foreach ($attachments as $att) {
                $link = 'action.php?action=download&id='.$id.'&folder='.urlencode($folder).'&part='.$att['part'].'&file='.urlencode($att['name']);
                echo '<a href="'.$link.'" class="attachment-btn" target="_blank">📄 '.htmlspecialchars($att['name']).'</a>';
            }
            echo '</div>';
        }
        
        echo '</body></html>';
        imap_close($inbox);
    }
}
?>