<?php
// admin.php — credential log viewer with charts at top, table at bottom

session_start();

// ────────────────────────────────────────────────
//  CONFIG
// ────────────────────────────────────────────────
$PASSWORD = 'honey potter';               // ← CHANGE THIS IMMEDIATELY
$LOG_FILE = '/var/www/logs/logins.txt';
$PER_PAGE = 10;

// ────────────────────────────────────────────────
//  AUTHENTICATION
// ────────────────────────────────────────────────
$logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$logged_in) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (trim($_POST['pass']) === $PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $logged_in = true;
        } else {
            $error = "Incorrect password.";
        }
    }

    if (!$logged_in) {
        // Login form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Admin – GC3 Remote Access</title>
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
          <style>
            :root { --bg: #0f172a; --card: #1e293b; --text: #e2e8f0; --text-muted: #94a3b8; --primary: #3b82f6; --danger: #ef4444; --border: #334155; }
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
            .login-box { background: var(--card); border-radius: 12px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; border: 1px solid var(--border); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.5); }
            h1 { font-size: 1.8rem; margin-bottom: 1.5rem; text-align: center; }
            .error { color: var(--danger); text-align: center; margin-bottom: 1.2rem; font-size: 0.95rem; }
            input[type="password"] { width: 100%; padding: 0.9rem 1.1rem; background: #0f172a; border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 1.05rem; margin-bottom: 1.5rem; }
            input[type="password"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.3); }
            button { width: 100%; padding: 1rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 1.05rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
            button:hover { background: #2563eb; }
          </style>
        </head>
        <body>
          <div class="login-box">
            <h1>Admin Access</h1>
            <?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
              <input type="password" name="pass" placeholder="Enter admin password" required autofocus>
              <button type="submit">Login</button>
            </form>
          </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ────────────────────────────────────────────────
//  LOAD ALL LOGS
// ────────────────────────────────────────────────
$all_logs = [];
if (file_exists($LOG_FILE)) {
    $lines = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            $all_logs[] = $data;
        }
    }
    $all_logs = array_reverse($all_logs);
}

$total = count($all_logs);
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $PER_PAGE;
$pages = max(1, ceil($total / $PER_PAGE));

$visible_logs = array_slice($all_logs, $offset, $PER_PAGE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – GC3 Credentials</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg: #0f172a;
      --card: #1e293b;
      --text: #e2e8f0;
      --text-muted: #94a3b8;
      --primary: #3b82f6;
      --danger: #ef4444;
      --border: #334155;
      --hover: #2d3748;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 2rem 1rem; }
    .container { max-width: 1400px; margin: 0 auto; }
    header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem; }
    h1, h2 { margin: 0; }
    h2 { font-size: 1.5rem; margin: 2.5rem 0 1.2rem; }
    .header-actions { display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap; }
    .btn {
      padding: 0.6rem 1.2rem;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .table-container, .chart-container {
      background: var(--card);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border);
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .chart-container { height: 480px; position: relative; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 1rem 1.2rem; text-align: left; border-bottom: 1px solid var(--border); }
    th { background: #253549; font-weight: 600; color: #cbd5e1; text-transform: uppercase; font-size: 0.85rem; }
    tr:hover { background: var(--hover); }
    .timestamp { white-space: nowrap; font-family: ui-monospace, monospace; }
    .ip { font-family: ui-monospace, monospace; color: #60a5fa; }
    .username { color: #fbbf24; }
    .password-b64 { font-family: ui-monospace, monospace; color: #f472b6; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
    .decode-cell button { background: #4f46e5; color: white; border: none; border-radius: 6px; padding: 0.4rem 0.8rem; font-size: 0.85rem; cursor: pointer; margin-left: 0.5rem; }
    .decode-cell button:hover { background: #4338ca; }
    .decoded { margin-top: 0.8rem; padding: 0.8rem; background: #111827; border: 1px solid #4b5563; border-radius: 6px; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-all; color: #86efac; display: none; }
    .decoded.error { color: var(--danger); background: #7f1d1d; border-color: #991b1b; }
    .pagination { margin-top: 2rem; display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
    .pagination a { padding: 0.6rem 1.1rem; background: var(--card); border: 1px solid var(--border); border-radius: 8px; color: var(--text); text-decoration: none; font-weight: 500; }
    .pagination a:hover, .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
    .pagination .active { pointer-events: none; }
    .empty { text-align: center; padding: 4rem; color: var(--text-muted); font-size: 1.1rem; }
  </style>
</head>
<body>

  <div class="container">
    <header>
      <h1>Credentials Log — GC3 Remote Access</h1>
      <div class="header-actions">
        <button class="btn" onclick="location.reload()">Refresh</button>
        <button class="btn" onclick="downloadCSV()">Download CSV</button>
        <a class="btn" href="?logout=1">Logout</a>
      </div>
    </header>

    <?php if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; } ?>

    <!-- Top 25 Passwords Chart -->
    <h2>Top 25 Passwords by Frequency</h2>
    <div class="chart-container">
      <canvas id="passwordChart"></canvas>
    </div>

    <!-- Top 25 Usernames Chart -->
    <h2>Top 25 Usernames by Frequency</h2>
    <div class="chart-container">
      <canvas id="usernameChart"></canvas>
    </div>

    <!-- Log Table – moved to bottom -->
    <h2>Captured Credentials</h2>
    <div class="table-container">
      <?php if (empty($visible_logs)): ?>
        <div class="empty">No credentials captured yet.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Time</th>
              <th>IP</th>
              <th>Username</th>
              <th>Password (base64)</th>
              <th>Decoded</th>
              <th>User Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($visible_logs as $index => $entry): ?>
              <tr>
                <td class="timestamp"><?= htmlspecialchars($entry['timestamp'] ?? '—') ?></td>
                <td class="ip"><?= htmlspecialchars($entry['ip'] ?? '—') ?></td>
                <td class="username"><?= htmlspecialchars($entry['username'] ?? '—') ?></td>
                <td class="password-b64"><?= htmlspecialchars($entry['password_b64'] ?? '—') ?></td>
                <td class="decode-cell">
                  <?php if (!empty($entry['password_b64'])): ?>
                    <button class="decode-btn" onclick="decodePassword(this, '<?= addslashes($entry['password_b64']) ?>', 'decoded-<?= $index ?>')">
                      Show
                    </button>
                    <div id="decoded-<?= $index ?>" class="decoded"></div>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td class="user-agent"><?= htmlspecialchars($entry['user_agent'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end = min($pages, $page + $range);
        if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>">← Previous</a>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a href="?page=<?= $page+1 ?>">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <script>
    // Decode password
    function decodePassword(btn, b64, targetId) {
      const target = document.getElementById(targetId);
      if (target.style.display === 'block') {
        target.style.display = 'none';
        btn.textContent = 'Show';
        return;
      }
      try {
        const decoded = atob(b64);
        target.textContent = decoded;
        target.classList.remove('error');
      } catch (e) {
        target.textContent = 'Invalid base64 encoding';
        target.classList.add('error');
      }
      target.style.display = 'block';
      btn.textContent = 'Hide';
    }

    // Download CSV
    function downloadCSV() {
      const logs = <?php echo json_encode($all_logs); ?>;
      if (!logs || logs.length === 0) { alert('No data to download.'); return; }
      let csv = 'Timestamp,IP,Username,Password (base64),Password (decoded),User Agent\n';
      logs.forEach(entry => {
        const t = (entry.timestamp || '').replace(/"/g, '""');
        const ip = (entry.ip || '').replace(/"/g, '""');
        const u = (entry.username || '').replace(/"/g, '""');
        const pb = (entry.password_b64 || '').replace(/"/g, '""');
        let d = '';
        try { d = atob(pb).replace(/"/g, '""'); } catch { d = '[invalid]'; }
        const ua = (entry.user_agent || '').replace(/"/g, '""').replace(/\n/g, ' ');
        csv += `"${t}","${ip}","${u}","${pb}","${d}","${ua}"\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `gc3-credentials-${new Date().toISOString().slice(0,10)}.csv`;
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    const logs = <?php echo json_encode($all_logs); ?>;

    // Password frequency
    const passCounts = {};
    logs.forEach(entry => {
      const b64 = entry.password_b64 || '';
      if (b64) {
        try {
          const decoded = atob(b64).trim();
          if (decoded) passCounts[decoded] = (passCounts[decoded] || 0) + 1;
        } catch {}
      }
    });
    const topPasswords = Object.entries(passCounts).sort((a,b)=>b[1]-a[1]).slice(0,25);
    const passLabels = topPasswords.map(([p]) => p.length > 20 ? p.substring(0,17)+'...' : p);
    const passValues = topPasswords.map(([,c]) => c);

    // Username frequency
    const userCounts = {};
    logs.forEach(entry => {
      const user = (entry.username || '').trim();
      if (user) userCounts[user] = (userCounts[user] || 0) + 1;
    });
    const topUsers = Object.entries(userCounts).sort((a,b)=>b[1]-a[1]).slice(0,25);
    const userLabels = topUsers.map(([u]) => u.length > 25 ? u.substring(0,22)+'...' : u);
    const userValues = topUsers.map(([,c]) => c);

    // Password Chart
    new Chart(document.getElementById('passwordChart'), {
      type: 'bar',
      data: { labels: passLabels, datasets: [{ label: 'Occurrences', data: passValues, backgroundColor: 'rgba(59,130,246,0.65)', borderColor: 'rgba(59,130,246,1)', borderWidth:1, borderRadius:4 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, title: {display:true,text:'Count',color:'#cbd5e1'}, ticks:{color:'#cbd5e1'}, grid:{color:'#334155'} }, x: { ticks:{color:'#cbd5e1',autoSkip:true,maxRotation:45,minRotation:45}, grid:{display:false} } },
        plugins: { legend:{display:false}, title:{display:true,text:'Top 25 Passwords by Frequency',color:'#e2e8f0',font:{size:18}} }
      }
    });

    // Username Chart
    new Chart(document.getElementById('usernameChart'), {
      type: 'bar',
      data: { labels: userLabels, datasets: [{ label: 'Occurrences', data: userValues, backgroundColor: 'rgba(245,158,11,0.65)', borderColor: 'rgba(245,158,11,1)', borderWidth:1, borderRadius:4 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, title: {display:true,text:'Count',color:'#cbd5e1'}, ticks:{color:'#cbd5e1'}, grid:{color:'#334155'} }, x: { ticks:{color:'#cbd5e1',autoSkip:true,maxRotation:45,minRotation:45}, grid:{display:false} } },
        plugins: { legend:{display:false}, title:{display:true,text:'Top 25 Usernames by Frequency',color:'#e2e8f0',font:{size:18}} }
      }
    });
  </script>

</body>
</html>

