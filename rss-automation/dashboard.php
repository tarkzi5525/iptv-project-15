<?php
/**
 * RSS Automation Dashboard
 *
 * A simple web UI to:
 *  - View recent automation logs
 *  - See the last N articles added to blog.json
 *  - Trigger a manual run (via HTTP)
 *
 * Access: https://yoursite.com/rss-automation/dashboard.php
 * IMPORTANT: Protect this file with HTTP Basic Auth or move it outside the web root!
 */

$config    = require __DIR__ . '/config.php';
$logFile   = $config['log_file'];
$blogPath  = $config['blog_json_path'];

// ─── Optional: Simple password protection ───────────────────────────────────
// Uncomment and set a password to protect the dashboard:
// define('DASHBOARD_PASS', 'changeme');
// if (!isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] !== DASHBOARD_PASS) {
//     header('WWW-Authenticate: Basic realm="RSS Dashboard"');
//     header('HTTP/1.0 401 Unauthorized');
//     exit('Unauthorized');
// }

// ─── Manual trigger ──────────────────────────────────────────────────────────
$runOutput = '';
if (isset($_POST['run'])) {
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/run.php') . ' 2>&1';
    ob_start();
    passthru($cmd);
    $runOutput = ob_get_clean();
}

// ─── Load data ───────────────────────────────────────────────────────────────
$logLines = [];
if (file_exists($logFile)) {
    $raw = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice(array_reverse($raw), 0, 100);
}

$posts = [];
if (file_exists($blogPath)) {
    $db    = json_decode(file_get_contents($blogPath), true);
    $posts = array_slice($db['posts'] ?? [], 0, 10);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RSS Automation Dashboard</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#0f1117;color:#e0e0e0;padding:24px}
  h1{color:#f97316;margin-bottom:6px}
  .sub{color:#888;font-size:.85rem;margin-bottom:24px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  @media(max-width:800px){.grid{grid-template-columns:1fr}}
  .card{background:#1a1d27;border-radius:10px;padding:20px}
  .card h2{font-size:1rem;color:#f97316;margin-bottom:14px;border-bottom:1px solid #2d3044;padding-bottom:8px}
  .log-box{height:320px;overflow-y:auto;font-size:.78rem;font-family:monospace;
           background:#0d0f18;padding:10px;border-radius:6px;line-height:1.6}
  .log-box .err{color:#f87171}
  .log-box .inf{color:#34d399}
  .log-box .dbg{color:#60a5fa}
  table{width:100%;border-collapse:collapse;font-size:.82rem}
  th{text-align:left;color:#888;font-weight:500;padding:6px 8px;border-bottom:1px solid #2d3044}
  td{padding:6px 8px;border-bottom:1px solid #1f2235;vertical-align:top}
  a{color:#f97316;text-decoration:none}
  a:hover{text-decoration:underline}
  .badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;background:#1e2e1e;color:#34d399}
  .run-form{margin-bottom:20px}
  .run-form button{background:#f97316;color:#fff;border:none;padding:10px 20px;
                    border-radius:6px;cursor:pointer;font-size:.9rem}
  .run-form button:hover{background:#ea6c0a}
  .run-output{margin-top:12px;background:#0d0f18;padding:12px;border-radius:6px;
               font-size:.78rem;font-family:monospace;white-space:pre-wrap;
               max-height:200px;overflow-y:auto;color:#a3e635}
  .stat-row{display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap}
  .stat{background:#1a1d27;border-radius:8px;padding:14px 20px;flex:1;min-width:140px}
  .stat .val{font-size:1.8rem;font-weight:700;color:#f97316}
  .stat .lbl{font-size:.78rem;color:#888;margin-top:2px}
</style>
</head>
<body>

<h1>RSS Automation Dashboard</h1>
<p class="sub">Automated article pipeline — monitor runs and published posts</p>

<!-- Stats row -->
<div class="stat-row">
  <div class="stat">
    <div class="val"><?= count($posts) ?></div>
    <div class="lbl">Recent Articles</div>
  </div>
  <div class="stat">
    <div class="val"><?= file_exists($logFile) ? number_format(count(file($logFile))) : 0 ?></div>
    <div class="lbl">Log Entries</div>
  </div>
  <div class="stat">
    <div class="val"><?= file_exists($logFile) ? date('H:i', filemtime($logFile)) : '–' ?></div>
    <div class="lbl">Last Run (time)</div>
  </div>
</div>

<!-- Manual trigger -->
<div class="card run-form" style="margin-bottom:20px">
  <h2>Manual Run</h2>
  <form method="POST">
    <button type="submit" name="run">▶ Run Automation Now</button>
  </form>
  <?php if ($runOutput): ?>
    <div class="run-output"><?= htmlspecialchars($runOutput) ?></div>
  <?php endif; ?>
</div>

<div class="grid">

  <!-- Recent articles -->
  <div class="card">
    <h2>Latest Published Articles</h2>
    <table>
      <thead>
        <tr><th>Title</th><th>Category</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td>
            <a href="/blog/<?= htmlspecialchars($p['slug'] ?? '') ?>" target="_blank">
              <?= htmlspecialchars(mb_substr($p['title'] ?? '', 0, 60)) ?>
            </a>
          </td>
          <td><span class="badge"><?= htmlspecialchars($p['category'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($p['date'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
        <tr><td colspan="3" style="color:#666;text-align:center;padding:20px">No articles yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Logs -->
  <div class="card">
    <h2>Recent Logs (last 100 lines)</h2>
    <div class="log-box">
      <?php foreach ($logLines as $line): ?>
        <?php
          $class = 'dbg';
          if (str_contains($line, '[ERROR]')) $class = 'err';
          elseif (str_contains($line, '[INFO]')) $class = 'inf';
        ?>
        <div class="<?= $class ?>"><?= htmlspecialchars($line) ?></div>
      <?php endforeach; ?>
      <?php if (empty($logLines)): ?>
        <div style="color:#666">No log entries yet. Run the automation to generate logs.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

</body>
</html>
