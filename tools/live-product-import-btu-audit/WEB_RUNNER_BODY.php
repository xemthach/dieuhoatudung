// This file is appended mechanically to LIVE_AUDIT.php for the one-file upload artifact.
const LIVE_AUDIT_TOKEN = '__GENERATED_AT_PACKAGE_BUILD__';
$root = is_file(__DIR__.'/../artisan') ? dirname(__DIR__) : dirname(__DIR__, 2);
if (! hash_equals(LIVE_AUDIT_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}
if (isset($_GET['download'], $_GET['sig'])) {
    $file = basename((string) $_GET['download']);
    $signature = hash_hmac('sha256', $file, LIVE_AUDIT_TOKEN);
    if (! hash_equals($signature, (string) $_GET['sig']) || ! preg_match('/^LIVE_PRODUCT_IMPORT_BTU_AUDIT_\\d{8}_\\d{6}\\.(json|md|html)$/', $file)) {
        http_response_code(404);
        exit('Not found');
    }
    $path = $root.'/storage/logs/audits/'.$file;
    if (! is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$file.'"');
    readfile($path);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audit = liveAuditRun($root);
    $reports = liveAuditWriteReports($root, $audit);
    $links = [];
    foreach (['json', 'markdown', 'html'] as $kind) {
        $file = basename($reports[$kind]);
        $links[$kind] = '?token='.rawurlencode(LIVE_AUDIT_TOKEN).'&download='.rawurlencode($file).'&sig='.hash_hmac('sha256', $file, LIVE_AUDIT_TOKEN);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Audit complete</title><style>body{font:18px system-ui;max-width:760px;margin:4rem auto}.ok{color:#087f23}</style><h1>PRODUCT IMPORT / BTU LIVE AUDIT</h1><h2 class="ok">AUDIT COMPLETE</h2><ul><li>Environment: '.htmlspecialchars($audit['application']['app_env'], ENT_QUOTES, 'UTF-8').'</li><li>Version: '.htmlspecialchars($audit['application']['version'], ENT_QUOTES, 'UTF-8').'</li><li>Git SHA: '.htmlspecialchars((string) $audit['application']['git_sha'], ENT_QUOTES, 'UTF-8').'</li><li>Database: '.htmlspecialchars($audit['database']['name'], ENT_QUOTES, 'UTF-8').'</li><li>Started: '.htmlspecialchars($audit['generated_at'], ENT_QUOTES, 'UTF-8').'</li></ul><p>READ_ONLY_GUARD: '.htmlspecialchars($audit['read_only_proof']['status'], ENT_QUOTES, 'UTF-8').'</p><ul><li><a href="'.$links['json'].'">JSON REPORT</a></li><li><a href="'.$links['markdown'].'">MARKDOWN REPORT</a></li><li><a href="'.$links['html'].'">HTML REPORT</a></li></ul><pre>'.htmlspecialchars(json_encode($reports['sha256'], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8').'</pre><p><strong>AUDIT ĐÃ HOÀN TẤT. HÃY TẢI REPORT VÀ XÓA FILE AUDIT KHỎI SERVER.</strong></p><p>Delete this temporary filename: '.htmlspecialchars(basename($_SERVER['SCRIPT_NAME']), ENT_QUOTES, 'UTF-8').'</p>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><meta charset="utf-8"><title>PRODUCT IMPORT / BTU LIVE AUDIT</title><style>body{font:18px system-ui;max-width:760px;margin:4rem auto}button{font-size:20px;padding:1rem 2rem;background:#087f23;color:#fff;border:0;border-radius:.5rem}</style><h1>PRODUCT IMPORT / BTU LIVE AUDIT</h1><p>Environment and version will be collected after the read-only audit starts.</p><form method="post"><button>CHẠY KIỂM TRA READ-ONLY</button></form><p><strong>After completion: download JSON/Markdown/HTML reports and delete this temporary audit file immediately.</strong></p>
