<?php
session_start();
$_SESSION['role'] = 'transcript_pro';
$_SESSION['user_id'] = 1;

ob_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = []; // "All statuses"
require 'modules/transcript/report_cost_share.php';
$html = ob_get_clean();

$pattern = '/<td>pass1234<\/td>.*?<span class="status-badge[^>]+>([^<]+)<\/span>/s';
if (preg_match($pattern, $html, $matches)) {
    echo "INJECTED HTML STATUS: " . trim($matches[1]) . "\n";
} else {
    echo "Row not found in HTML output\n";
}
?>