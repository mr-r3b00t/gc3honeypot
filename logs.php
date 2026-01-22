<?php
// logs.php — Apache access log viewer with pagination + search
// Requires same auth as admin.php

session_start();

// ────────────────────────────────────────────────
//  CONFIG
// ────────────────────────────────────────────────
$PASSWORD       = 'honey potter';               // Must match admin.php
$LOG_FILE       = '/var/log/apache2/access.log';
$LINES_PER_PAGE = 100;
$MAX_PAGES_SHOWN = 10;

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
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Access Log – GC3 Honeypot</title>
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
            input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.3); }
            button { width: 100%; padding: 1rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 1.05rem; font-weight: 600; cursor: pointer; }
            button:hover { background: #2563eb; }
          </style>
        </head>
        <body>
          <div class="login-box">
            <h1>Access Log Viewer</h1>
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
//  SEARCH & PAGINATION
// ────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $LINES_PER_PAGE;

// ────────────────────────────────────────────────
//  LOAD & FILTER LOG
// ────────────────────────────────────────────────
$filtered_lines = [];
$total_lines    = 0;
$error_message  = '';

if (file_exists($LOG_FILE) && is_readable($LOG_FILE)) {
    $all_lines = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total_lines = count($all_lines);

    if ($search !== '') {
        $search_lower = strtolower($search);
        foreach ($all_lines as $line) {
            if (stripos($line, $search_lower) !== false) {
                $filtered_lines[] = $line;
            }
        }
    } else {
        $filtered_lines = $all_lines;
    }

    $reversed = array_reverse($filtered_lines);
    $lines = array_slice($reversed, $offset, $LINES_PER_PAGE);
} else {
    $error_message = "Cannot read access log: " . htmlspecialchars($LOG_FILE) . " (file missing or permission denied)";
}

$displayed_count = count($filtered_lines);
$total_pages = $displayed_count > 0 ? ceil($displayed_count / $LINES_PER_PAGE) : 1;
if ($page > $total_pages) $page = $total_pages;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Log – GC3 Honeypot</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
      --log-line: #cbd5e1;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 2rem 1rem; min-height: 100vh; }
    .container { max-width: 1600px; margin: 0 auto; }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1.5rem;
    }
    .page-header h1 {
      margin: 0;
      font-size: 1.9rem;
      font-weight: 600;
    }
    .header-actions {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      padding: 0.65rem 1.3rem;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .btn:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    .btn-primary {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    .btn-primary:hover { background: #2563eb; }
    .btn-clear {
      background: var(--danger);
      border-color: #991b1b;
      color: white;
    }
    .btn-clear:hover { background: #dc2626; }

    .search-form {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 2rem;
      align-items: center;
    }
    .search-input {
      flex: 1 1 320px;
      min-width: 0;
      padding: 0.8rem 1.1rem;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: #111827;
      color: var(--text);
      font-size: 1rem;
    }
    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
    }
    .search-buttons {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .log-container {
      background: var(--card);
      border-radius: 12px;
      border: 1px solid var(--border);
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    .log-header {
      background: #253549;
      padding: 1rem 1.5rem;
      font-weight: 600;
      color: #cbd5e1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    pre {
      margin: 0;
      padding: 1.5rem;
      font-family: 'Consolas', 'Menlo', 'Monaco', monospace;
      font-size: 0.95rem;
      line-height: 1.5;
      white-space: pre-wrap;
      word-break: break-all;
      color: var(--log-line);
      background: #111827;
      overflow-x: auto;
    }
    .line-number {
      color: #64748b;
      margin-right: 1.2rem;
      user-select: none;
      display: inline-block;
      width: 5ch;
      text-align: right;
    }

    .error-box {
      background: #7f1d1d;
      color: #fecaca;
      padding: 1.5rem;
      border-radius: 8px;
      margin: 2rem 0;
      border: 1px solid #991b1b;
    }

    .pagination {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 2rem;
    }
    .pagination a, .pagination span {
      padding: 0.6rem 1.1rem;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      text-decoration: none;
      font-weight: 500;
      min-width: 44px;
      text-align: center;
    }
    .pagination a:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .pagination .current { background: var(--primary); color: white; border-color: var(--primary); pointer-events: none; }
    .pagination .dots { background: transparent; border: none; color: var(--text-muted); }

    @media (max-width: 640px) {
      .page-header { flex-direction: column; align-items: flex-start; gap: 1.25rem; }
      .search-form { flex-direction: column; align-items: stretch; }
      .search-buttons { width: 100%; }
      .btn, .search-buttons .btn { flex: 1; }
    }
  </style>
</head>
<body>

  <div class="container">
    <header class="page-header">
      <h1>Apache Access Log Viewer</h1>
      <div class="header-actions">
        <button class="btn" onclick="location.href='?<?php if($search) echo 'search=' . urlencode($search) . '&'; ?>page=1'">Refresh</button>
        <a class="btn" href="?logout=1">Logout</a>
      </div>
    </header>

    <?php if (isset($_GET['logout'])) { session_destroy(); header("Location: logs.php"); exit; } ?>

    <form class="search-form" method="get">
      <input 
        type="text" 
        name="search" 
        class="search-input" 
        placeholder="Search log lines (IP, status, path, method...)" 
        value="<?= htmlspecialchars($search) ?>" 
        autofocus
      >
      <div class="search-buttons">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
          <a href="logs.php" class="btn btn-clear">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($error_message): ?>
      <div class="error-box"><?= $error_message ?></div>
    <?php else: ?>
      <div class="log-container">
        <div class="log-header">
          <span>
            <?php if ($search !== ''): ?>
              Filtered: <?= number_format($displayed_count) ?> matching lines
            <?php else: ?>
              Total: <?= number_format($total_lines) ?> lines
            <?php endif; ?>
            (showing <?= $offset + 1 ?>–<?= min($offset + count($lines), $displayed_count) ?>)
          </span>
          <span>File: <?= htmlspecialchars($LOG_FILE) ?></span>
        </div>
        <pre><?php
          if (empty($lines)) {
            echo "No matching lines found" . ($search ? " for '" . htmlspecialchars($search) . "'" : "") . ".\n";
          } else {
            foreach ($lines as $i => $line) {
              $global_index = $offset + $i + 1;
              $num = str_pad($global_index, 7, ' ', STR_PAD_LEFT);
              echo "<span class=\"line-number\">$num</span> $line\n";
            }
          }
        ?></pre>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php
          $query = $search ? 'search=' . urlencode($search) . '&' : '';
          if ($page > 1): ?>
            <a href="?<?= $query ?>page=<?= $page - 1 ?>">← Prev</a>
          <?php else: ?>
            <span>← Prev</span>
          <?php endif; ?>

          <?php
          $start = max(1, $page - floor($MAX_PAGES_SHOWN / 2));
          $end   = min($total_pages, $start + $MAX_PAGES_SHOWN - 1);
          if ($end - $start + 1 < $MAX_PAGES_SHOWN) $start = max(1, $end - $MAX_PAGES_SHOWN + 1);

          if ($start > 1): ?>
            <a href="?<?= $query ?>page=1">1</a>
            <?php if ($start > 2): ?><span class="dots">...</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?<?= $query ?>page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span class="dots">...</span><?php endif; ?>
            <a href="?<?= $query ?>page=<?= $total_pages ?>"><?= $total_pages ?></a>
          <?php endif; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?<?= $query ?>page=<?= $page + 1 ?>">Next →</a>
          <?php else: ?>
            <span>Next →</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

</body>
</html>
