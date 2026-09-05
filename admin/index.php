<?php
session_start();
require_once __DIR__ . '/../api/db.php';

// Authentication PIN (Client PIN)
$correct_pin = '9999997775';
$error = '';

if (isset($_REQUEST['pin'])) {
    if (trim($_REQUEST['pin']) === $correct_pin) {
        $_SESSION['loan81_admin'] = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'Invalid Access PIN. Please try again.';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['loan81_admin']);
    header('Location: index.php');
    exit;
}

$is_logged_in = !empty($_SESSION['loan81_admin']);

// AJAX Endpoint: Update Lead Status & Notes
if ($is_logged_in && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_lead') {
    header('Content-Type: application/json');
    $lead_id = intval($_POST['lead_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'New');
    $notes = trim($_POST['notes'] ?? '');

    if ($lead_id && $pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE leads SET status = :status, notes = :notes WHERE id = :id");
            $stmt->execute([':status' => $status, ':notes' => $notes, ':id' => $lead_id]);
            echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid lead ID']);
    }
    exit;
}

// CSV Export
if ($is_logged_in && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="loan81_leads_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Lead ID', 'Date & Time', 'Customer Name', 'Phone', 'Email', 'Loan Category', 'Amount Needed (INR)', 'Monthly Income (INR)', 'Employment Type', 'City', 'Status', 'Advisor Notes', 'Lead Source', 'IP Address', 'Customer Message']);

    if ($pdo) {
        $stmt = $pdo->query("SELECT id, created_at, name, phone, email, loan_type, amount, monthly_income, employment_type, city, status, notes, source, ip_address, message FROM leads ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['status'] = $row['status'] ?: 'New';
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// Fetch all leads for dashboard
$leads = [];
$total_volume = 0;
$today_count = 0;
$new_count = 0;
$today_date = date('Y-m-d');

if ($is_logged_in && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM leads ORDER BY id DESC LIMIT 500");
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leads as &$lead) {
            $amt = floatval($lead['amount'] ?? 0);
            $total_volume += $amt;
            $lead_date = date('Y-m-d', strtotime($lead['created_at']));
            if ($lead_date === $today_date) {
                $today_count++;
            }
            $lead_status = $lead['status'] ?: 'New';
            if ($lead_status === 'New') {
                $new_count++;
            }
            $lead['status'] = $lead_status;
        }
        unset($lead);
    } catch (Exception $e) {
        $error = 'Database query error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Loan81 - Advisor Leads & Inquiries CRM</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon-81.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
      --primary-navy: #0E2954;
      --navy-dark: #071833;
      --primary-blue: #176AB8;
      --teal-accent: #04C398;
      --teal-dark: #039f7c;
      --bg-light: #f4f8fc;
      --surface-white: #ffffff;
      --border-color: #e2e8f0;
      --border-hover: #cbd5e1;
      --text-main: #0E2954;
      --text-muted: #64748b;
      --radius-sm: 6px;
      --radius-md: 10px;
      --radius-lg: 14px;
      --shadow-sm: 0 2px 8px rgba(14, 41, 84, 0.05);
      --shadow-md: 0 6px 20px rgba(14, 41, 84, 0.08);
      --shadow-lg: 0 15px 40px rgba(14, 41, 84, 0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background: var(--bg-light); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; }

    /* Header */
    .admin-header {
      background: linear-gradient(135deg, var(--navy-dark) 0%, var(--primary-navy) 100%);
      color: #ffffff;
      padding: 14px 28px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 15px rgba(7, 24, 51, 0.15);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .admin-brand { display: flex; align-items: center; gap: 14px; text-decoration: none; }
    .admin-brand img { height: 38px; width: auto; object-fit: contain; }
    .brand-divider { width: 1px; height: 26px; background: rgba(255, 255, 255, 0.2); }
    .brand-badge {
      font-size: 0.82rem;
      font-weight: 600;
      color: #e2e8f0;
      letter-spacing: 0.02em;
    }
    .header-actions { display: flex; align-items: center; gap: 10px; }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary-blue), var(--teal-accent)); color: #fff; border: none; }
    .btn-primary:hover { opacity: 0.95; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(23, 106, 184, 0.3); }
    .btn-green { background: #04C398; color: #fff; }
    .btn-green:hover { background: var(--teal-dark); transform: translateY(-1px); }
    .btn-outline { background: transparent; border-color: rgba(255, 255, 255, 0.35); color: #fff; }
    .btn-outline:hover { background: rgba(255, 255, 255, 0.1); border-color: #fff; }
    .btn-subtle { background: #eef5fc; color: var(--primary-blue); border-color: #d0e3f8; }
    .btn-subtle:hover { background: #e0edff; }
    .btn-sm { padding: 5px 10px; font-size: 0.78rem; border-radius: 5px; }

    /* Layout */
    .admin-container { max-width: 1420px; width: 100%; margin: 24px auto; padding: 0 20px; flex: 1; }

    /* Login Screen */
    .login-wrapper {
      max-width: 440px;
      margin: 80px auto;
      background: var(--surface-white);
      border-radius: var(--radius-lg);
      padding: 40px;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-lg);
      text-align: center;
    }
    .login-logo { height: 46px; margin-bottom: 20px; }
    .login-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--primary-navy); margin-bottom: 6px; }
    .login-desc { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
    .pin-input {
      width: 100%;
      padding: 14px;
      font-size: 1.25rem;
      text-align: center;
      letter-spacing: 0.25em;
      font-weight: 700;
      border: 2px solid var(--border-color);
      border-radius: var(--radius-md);
      margin-bottom: 18px;
      color: var(--primary-navy);
      background: #fafcfe;
      transition: all 0.2s;
    }
    .pin-input:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 4px rgba(23, 106, 184, 0.15); background: #fff; }

    /* Metrics Summary KPI Cards */
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }
    .metric-card {
      background: var(--surface-white);
      border-radius: var(--radius-md);
      padding: 20px;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      gap: 16px;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .metric-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .metric-icon {
      width: 50px;
      height: 50px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      flex-shrink: 0;
    }
    .metric-icon.blue { background: #e0edff; color: var(--primary-blue); }
    .metric-icon.green { background: #e6f9f4; color: var(--teal-accent); }
    .metric-icon.amber { background: #fef3c7; color: #d97706; }
    .metric-icon.purple { background: #f3e8ff; color: #7c3aed; }
    .metric-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
    .metric-value { font-size: 1.65rem; font-weight: 800; color: var(--primary-navy); font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1.1; }
    .metric-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }

    /* Filter & Controls Panel */
    .filter-card {
      background: var(--surface-white);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      padding: 18px 22px;
      margin-bottom: 22px;
    }
    .filter-grid {
      display: grid;
      grid-template-columns: 2.2fr 1.3fr 1.1fr 1.1fr 1.1fr auto;
      gap: 12px;
      align-items: center;
    }
    .filter-group { position: relative; }
    .search-box {
      width: 100%;
      padding: 10px 14px 10px 38px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 0.88rem;
      color: var(--text-main);
      background: #fafcfe;
      transition: all 0.2s;
    }
    .search-box:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 3px rgba(23, 106, 184, 0.12); }
    .search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem; pointer-events: none; }
    .filter-select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 0.85rem;
      color: var(--text-main);
      background: #fafcfe;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
    }
    .filter-select:focus { outline: none; border-color: var(--primary-blue); background: #fff; }

    .filter-meta-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px solid #f1f5f9;
      font-size: 0.82rem;
      color: var(--text-muted);
    }
    .results-count { font-weight: 700; color: var(--primary-navy); }
    .active-filter-tags { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .filter-tag {
      background: #eef5fc;
      color: var(--primary-blue);
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    /* Leads Table Card */
    .leads-card {
      background: var(--surface-white);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      margin-bottom: 40px;
    }
    .table-header-bar {
      padding: 16px 22px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
    }
    .table-title { font-size: 1.15rem; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--primary-navy); }
    
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left; }
    thead th {
      background: #f8fafc;
      padding: 13px 16px;
      font-weight: 700;
      color: var(--primary-navy);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-bottom: 1px solid var(--border-color);
      white-space: nowrap;
    }
    tbody tr {
      border-bottom: 1px solid #f1f5f9;
      transition: background-color 0.15s ease;
      cursor: pointer;
    }
    tbody tr:hover { background: #f8fbff; }
    tbody td { padding: 14px 16px; vertical-align: middle; white-space: nowrap; }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.02em;
    }
    .badge-product { background: #e0edff; color: var(--primary-blue); border: 1px solid rgba(23, 106, 184, 0.2); }
    
    /* Status Badges */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 9999px;
      font-size: 0.74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .status-new { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .status-contacted { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .status-in-progress { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .status-sanctioned { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .status-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .phone-link {
      color: var(--primary-blue);
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .phone-link:hover { text-decoration: underline; color: var(--navy-dark); }

    .action-group { display: flex; align-items: center; gap: 6px; }
    .btn-action-view {
      background: #eef5fc;
      color: var(--primary-blue);
      border: 1px solid #d0e3f8;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.15s;
    }
    .btn-action-view:hover { background: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }

    .btn-action-whatsapp {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.15s;
    }
    .btn-action-whatsapp:hover { background: #04C398; color: #fff; border-color: #04C398; }

    /* Slide-Over Drawer for Lead Detail */
    .drawer-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(7, 24, 51, 0.45);
      backdrop-filter: blur(4px);
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .drawer-overlay.active { opacity: 1; visibility: visible; }

    .detail-drawer {
      position: fixed;
      top: 0; right: 0; bottom: 0;
      width: min(560px, 94vw);
      background: #ffffff;
      z-index: 1001;
      box-shadow: -15px 0 45px rgba(7, 24, 51, 0.25);
      transform: translateX(105%);
      transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
    }
    .detail-drawer.active { transform: translateX(0); }

    .drawer-top {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
      flex-shrink: 0;
    }
    .drawer-title-group h3 {
      font-size: 1.25rem;
      font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--primary-navy);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .drawer-title-group span { font-size: 0.8rem; color: var(--text-muted); }
    .drawer-close {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid var(--border-color);
      background: #f8fafc;
      color: var(--text-main);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.1rem;
      transition: all 0.2s;
    }
    .drawer-close:hover { background: #e2e8f0; }

    .drawer-scroll-body {
      flex: 1;
      overflow-y: auto;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* Detail Cards inside Drawer */
    .detail-section {
      background: #fafcfe;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 16px 18px;
    }
    .detail-section-title {
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--primary-blue);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .detail-item strong {
      display: block;
      font-size: 0.74rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 3px;
    }
    .detail-item span {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--primary-navy);
    }
    .detail-item.full-width { grid-column: span 2; }

    .notes-textarea {
      width: 100%;
      min-height: 80px;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 0.88rem;
      color: var(--text-main);
      resize: vertical;
      margin-top: 6px;
      font-family: inherit;
    }
    .notes-textarea:focus { outline: none; border-color: var(--primary-blue); background: #fff; }

    .status-select-lg {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--primary-navy);
      background: #fff;
      cursor: pointer;
    }

    .drawer-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      background: #ffffff;
      flex-shrink: 0;
    }

    /* Toast Notification */
    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--navy-dark);
      color: #ffffff;
      padding: 12px 20px;
      border-radius: var(--radius-md);
      font-size: 0.88rem;
      font-weight: 600;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      border-left: 4px solid var(--teal-accent);
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 2000;
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .toast.show { transform: translateY(0); opacity: 1; }

    /* Responsive */
    @media (max-width: 1100px) {
      .filter-grid {
        grid-template-columns: 1fr 1fr;
      }
      .filter-grid > *:first-child {
        grid-column: span 2;
      }
      .filter-grid > *:last-child {
        grid-column: span 2;
      }
    }
    @media (max-width: 768px) {
      .admin-header { padding: 12px 16px; flex-wrap: wrap; gap: 10px; }
      .metrics-grid { grid-template-columns: 1fr 1fr; }
      .detail-grid { grid-template-columns: 1fr; }
      .detail-item.full-width { grid-column: span 1; }
      .filter-grid { grid-template-columns: 1fr; }
      .filter-grid > * { grid-column: span 1 !important; }
    }
  </style>
</head>
<body>

  <!-- ADMIN HEADER -->
  <header class="admin-header">
    <div class="admin-brand">
      <img src="../assets/images/logo-white.png" alt="Loan81">
      <div class="brand-divider"></div>
      <span class="brand-badge">Advisory Leads CRM</span>
    </div>
    <?php if ($is_logged_in): ?>
      <div class="header-actions">
        <a href="?export=csv" class="btn btn-green btn-sm" title="Download filtered or full leads list">
          <i class="fa-solid fa-file-excel"></i> Export CSV
        </a>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.location.reload();" title="Refresh leads">
          <i class="fa-solid fa-rotate-right"></i> Refresh
        </button>
        <a href="?action=logout" class="btn btn-outline btn-sm" style="border-color: rgba(255,255,255,0.25);">
          <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
      </div>
    <?php endif; ?>
  </header>

  <div class="admin-container">
    <?php if (!$is_logged_in): ?>
      <!-- LOGIN FORM -->
      <div class="login-wrapper">
        <img src="../assets/images/logo.png" alt="Loan81" class="login-logo">
        <h2 class="login-title">Advisor Portal Access</h2>
        <p class="login-desc">Enter your 10-digit authorization PIN to view and manage customer applications.</p>
        
        <?php if ($error): ?>
          <div style="background: #fef2f2; color: #dc2626; padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 0.85rem; font-weight: 600; border: 1px solid #fecaca;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <input type="password" name="pin" class="pin-input" placeholder="••••••••••" maxlength="10" required autofocus autocomplete="current-password">
          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 1rem; border-radius: 8px;">
            <i class="fa-solid fa-lock-open"></i> Unlock Dashboard
          </button>
        </form>
      </div>
    <?php else: ?>

      <!-- 1. EXECUTIVE KPI SUMMARY METRICS -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-icon blue"><i class="fa-solid fa-users"></i></div>
          <div>
            <div class="metric-label">Total Leads</div>
            <div class="metric-value"><?= count($leads) ?></div>
            <div class="metric-sub">Across all loan products</div>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon green"><i class="fa-solid fa-calendar-check"></i></div>
          <div>
            <div class="metric-label">Inquiries Today</div>
            <div class="metric-value"><?= $today_count ?></div>
            <div class="metric-sub">Received in last 24 hrs</div>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon amber"><i class="fa-solid fa-bell"></i></div>
          <div>
            <div class="metric-label">Action Pending (New)</div>
            <div class="metric-value" id="kpiNewCount"><?= $new_count ?></div>
            <div class="metric-sub">Need advisor call-back</div>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon purple"><i class="fa-solid fa-indian-rupee-sign"></i></div>
          <div>
            <div class="metric-label">Loan Volume Inquired</div>
            <div class="metric-value">
              <?php
                if ($total_volume >= 10000000) {
                    echo '₹' . number_format($total_volume / 10000000, 2) . ' Cr';
                } elseif ($total_volume >= 100000) {
                    echo '₹' . number_format($total_volume / 100000, 2) . ' L';
                } else {
                    echo '₹' . number_format($total_volume);
                }
              ?>
            </div>
            <div class="metric-sub">Sum of all loan requests</div>
          </div>
        </div>
      </div>

      <!-- 2. ADVANCED SEARCH & MULTI-FILTER BAR -->
      <div class="filter-card">
        <div class="filter-grid">
          <!-- Text Search -->
          <div class="filter-group">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="search-box" placeholder="Search by name, phone, email, city..." autocomplete="off">
          </div>

          <!-- Category Filter -->
          <div class="filter-group">
            <select id="categoryFilter" class="filter-select">
              <option value="">All Loan Products</option>
              <option value="Personal Loan">Personal Loan</option>
              <option value="Business Loan">Business & MSME Loan</option>
              <option value="Home Loan">Home Loan</option>
              <option value="Loan Against Property">Loan Against Property (LAP)</option>
              <option value="Debt Consolidation">Debt Consolidation</option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="filter-group">
            <select id="statusFilter" class="filter-select">
              <option value="">All Statuses</option>
              <option value="New">New (Pending)</option>
              <option value="Contacted">Contacted</option>
              <option value="In Progress">In Progress</option>
              <option value="Sanctioned">Sanctioned</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>

          <!-- Date Filter -->
          <div class="filter-group">
            <select id="dateFilter" class="filter-select">
              <option value="">All Dates</option>
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="week">Last 7 Days</option>
              <option value="month">This Month</option>
            </select>
          </div>

          <!-- Amount Filter -->
          <div class="filter-group">
            <select id="amountFilter" class="filter-select">
              <option value="">All Amounts</option>
              <option value="below_5l">Under ₹5 Lakhs</option>
              <option value="5l_25l">₹5L - ₹25 Lakhs</option>
              <option value="25l_1cr">₹25L - ₹1 Crore</option>
              <option value="above_1cr">Above ₹1 Crore</option>
            </select>
          </div>

          <!-- Reset Filter -->
          <div>
            <button type="button" id="resetFiltersBtn" class="btn btn-subtle" style="white-space: nowrap; width: 100%; justify-content: center;">
              <i class="fa-solid fa-arrow-rotate-left"></i> Reset
            </button>
          </div>
        </div>

        <div class="filter-meta-bar">
          <div>
            Showing <span id="filteredCount" class="results-count"><?= count($leads) ?></span> of <span class="results-count"><?= count($leads) ?></span> leads
          </div>
          <div class="active-filter-tags" id="activeFilterTags">
            <!-- Dynamically populated tags -->
          </div>
        </div>
      </div>

      <!-- 3. DETAILED LEADS TABLE -->
      <div class="leads-card">
        <div class="table-header-bar">
          <div>
            <h2 class="table-title">Inbound Customer Inquiries</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Click any row to open the complete customer details drawer</span>
          </div>
          <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Real-Time Database Connected
          </span>
        </div>

        <div class="table-wrap">
          <table id="leadsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date / Time</th>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Loan Category</th>
                <th>Amount Needed</th>
                <th>Monthly Income</th>
                <th>City / Location</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($leads)): ?>
                <tr id="noDataRow">
                  <td colspan="10" style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-inbox" style="font-size: 2.5rem; margin-bottom: 12px; color: #cbd5e1; display: block;"></i>
                    <strong>No leads found.</strong><br>
                    Customer inquiries submitted through the website forms will instantly appear here.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($leads as $l): 
                  $status_val = $l['status'] ?: 'New';
                  $status_slug = strtolower(str_replace(' ', '-', $status_val));
                  $amt_val = floatval($l['amount'] ?? 0);
                  $income_val = floatval($l['monthly_income'] ?? 0);
                  $phone_clean = preg_replace('/\D/', '', $l['phone']);
                  $lead_json = htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8');
                ?>
                  <tr class="lead-row" 
                      data-id="<?= $l['id'] ?>"
                      data-name="<?= htmlspecialchars(strtolower($l['name'] ?: '')) ?>"
                      data-phone="<?= htmlspecialchars($phone_clean) ?>"
                      data-email="<?= htmlspecialchars(strtolower($l['email'] ?: '')) ?>"
                      data-city="<?= htmlspecialchars(strtolower($l['city'] ?: '')) ?>"
                      data-category="<?= htmlspecialchars($l['loan_type']) ?>"
                      data-status="<?= htmlspecialchars($status_val) ?>"
                      data-date="<?= date('Y-m-d', strtotime($l['created_at'])) ?>"
                      data-amount="<?= $amt_val ?>"
                      data-lead='<?= $lead_json ?>'
                      onclick="openLeadDrawerFromRow(this, event)">
                    
                    <td><strong style="color: var(--primary-navy);">#<?= $l['id'] ?></strong></td>
                    
                    <td style="color: var(--text-muted); font-size: 0.8rem;">
                      <i class="fa-regular fa-clock" style="font-size: 0.72rem;"></i> <?= date('d M Y, h:i A', strtotime($l['created_at'])) ?>
                    </td>

                    <td>
                      <div style="font-weight: 700; color: var(--primary-navy);"><?= htmlspecialchars($l['name'] ?: 'Prospect') ?></div>
                      <?php if (!empty($l['email'])): ?>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($l['email']) ?></div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <a href="tel:<?= htmlspecialchars($l['phone']) ?>" class="phone-link" onclick="event.stopPropagation();">
                        <i class="fa-solid fa-phone" style="font-size: 0.72rem;"></i> <?= htmlspecialchars($l['phone']) ?>
                      </a>
                    </td>

                    <td>
                      <span class="badge badge-product"><?= htmlspecialchars($l['loan_type']) ?></span>
                    </td>

                    <td>
                      <?php if ($amt_val > 0): ?>
                        <strong style="color: var(--primary-navy);">₹<?= number_format($amt_val) ?></strong>
                      <?php else: ?>
                        <span style="color: #94a3b8;">-</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?php if ($income_val > 0): ?>
                        <span style="color: #475569;">₹<?= number_format($income_val) ?></span>
                      <?php else: ?>
                        <span style="color: #94a3b8;">-</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?php if (!empty($l['city'])): ?>
                        <span style="color: #475569;"><i class="fa-solid fa-location-dot" style="color: #94a3b8; font-size: 0.75rem;"></i> <?= htmlspecialchars($l['city']) ?></span>
                      <?php else: ?>
                        <span style="color: #94a3b8;">-</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <span class="status-badge status-<?= $status_slug ?>" id="rowStatusBadge-<?= $l['id'] ?>">
                        <?= htmlspecialchars($status_val) ?>
                      </span>
                    </td>

                    <td>
                      <div class="action-group" onclick="event.stopPropagation();">
                        <button type="button" class="btn-action-view" title="View 360° Lead Details" onclick="openLeadDrawer(<?= $lead_json ?>);">
                          <i class="fa-solid fa-eye"></i> View
                        </button>
                        <a href="https://wa.me/91<?= $phone_clean ?>?text=<?= urlencode('Hello ' . ($l['name'] ?: 'there') . '! Greetings from Loan81. We have received your inquiry for ' . $l['loan_type'] . ($amt_val ? ' of ₹' . number_format($amt_val) : '') . '. Our senior advisor is ready to assist you with the lowest interest rate options across 20+ partner banks.') ?>" target="_blank" rel="noopener" class="btn-action-whatsapp" title="Chat on WhatsApp">
                          <i class="fa-brands fa-whatsapp"></i> Chat
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. SLIDE-OVER DRAWER FOR 360° LEAD DETAILS -->
      <div id="drawerOverlay" class="drawer-overlay" onclick="closeLeadDrawer();"></div>
      <aside id="detailDrawer" class="detail-drawer" aria-label="Customer Lead Details">
        <div class="drawer-top">
          <div class="drawer-title-group">
            <h3 id="drawerLeadId">Lead Details</h3>
            <span id="drawerLeadDate">Submitted: -</span>
          </div>
          <button type="button" class="drawer-close" onclick="closeLeadDrawer();" aria-label="Close drawer">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="drawer-scroll-body">
          <!-- Customer Profile Section -->
          <div class="detail-section">
            <div class="detail-section-title"><i class="fa-solid fa-user"></i> Customer Contact Profile</div>
            <div class="detail-grid">
              <div class="detail-item full-width">
                <strong>Full Name</strong>
                <span id="drawerCustomerName" style="font-size: 1.15rem; color: var(--primary-navy);">-</span>
              </div>
              <div class="detail-item">
                <strong>Phone Number</strong>
                <span id="drawerCustomerPhone">
                  <a href="#" id="drawerPhoneLink" class="phone-link">-</a>
                </span>
              </div>
              <div class="detail-item">
                <strong>Email Address</strong>
                <span id="drawerCustomerEmail">-</span>
              </div>
              <div class="detail-item full-width">
                <strong>Location / City</strong>
                <span id="drawerCustomerCity">-</span>
              </div>
            </div>
          </div>

          <!-- Loan Request Section -->
          <div class="detail-section">
            <div class="detail-section-title"><i class="fa-solid fa-hand-holding-dollar"></i> Loan Application Data</div>
            <div class="detail-grid">
              <div class="detail-item">
                <strong>Loan Product</strong>
                <span id="drawerLoanType" class="badge badge-product">-</span>
              </div>
              <div class="detail-item">
                <strong>Amount Needed</strong>
                <span id="drawerAmount" style="color: var(--primary-navy); font-size: 1.1rem;">-</span>
              </div>
              <div class="detail-item">
                <strong>Monthly Income</strong>
                <span id="drawerIncome">-</span>
              </div>
              <div class="detail-item">
                <strong>Employment Type</strong>
                <span id="drawerEmployment">-</span>
              </div>
            </div>
          </div>

          <!-- Attribution & System Data -->
          <div class="detail-section">
            <div class="detail-section-title"><i class="fa-solid fa-chart-pie"></i> Ingestion Attribution</div>
            <div class="detail-grid">
              <div class="detail-item">
                <strong>Inquiry Source</strong>
                <span id="drawerSource" style="color: var(--primary-blue);">-</span>
              </div>
              <div class="detail-item">
                <strong>IP Address</strong>
                <span id="drawerIp" style="font-family: monospace; font-size: 0.85rem; color: var(--text-muted);">-</span>
              </div>
              <div class="detail-item full-width" id="drawerMessageWrap" style="display: none;">
                <strong>Customer Message / Query</strong>
                <p id="drawerMessage" style="background: #ffffff; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.88rem; color: #334155; margin-top: 4px; line-height: 1.5;"></p>
              </div>
            </div>
          </div>

          <!-- Advisor Management & Notes -->
          <div class="detail-section" style="background: #ffffff; border: 2px solid #d0e3f8;">
            <div class="detail-section-title" style="color: var(--navy-dark);"><i class="fa-solid fa-clipboard-check"></i> Advisor Status & Follow-up Notes</div>
            <div style="margin-bottom: 12px;">
              <label style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Change Pipeline Status</label>
              <select id="drawerStatusSelect" class="status-select-lg">
                <option value="New">🟢 New (Uncontacted)</option>
                <option value="Contacted">🔵 Contacted / In Touch</option>
                <option value="In Progress">🟡 In Progress (Docs Processing)</option>
                <option value="Sanctioned">🟣 Sanctioned / Approved</option>
                <option value="Rejected">🔴 Rejected / Ineligible</option>
              </select>
            </div>
            <div>
              <label style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block;">Advisor Remarks / Internal Notes</label>
              <textarea id="drawerNotes" class="notes-textarea" placeholder="Add confidential remarks, bank submitted, eligibility outcome, or callback time..."></textarea>
            </div>
            <div style="text-align: right; margin-top: 10px;">
              <button type="button" id="saveLeadNotesBtn" class="btn btn-primary btn-sm" onclick="saveLeadChanges();">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
              </button>
            </div>
          </div>
        </div>

        <!-- Drawer Footer with Instant One-Tap Triggers -->
        <div class="drawer-footer">
          <a href="#" id="drawerWhatsAppBtn" target="_blank" rel="noopener" class="btn btn-green" style="flex: 1; justify-content: center;">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
          </a>
          <a href="#" id="drawerCallBtn" class="btn btn-subtle" style="flex: 1; justify-content: center;">
            <i class="fa-solid fa-phone"></i> Call Customer
          </a>
        </div>
      </aside>

      <!-- Toast Feedback -->
      <div id="adminToast" class="toast">
        <i class="fa-solid fa-circle-check" style="color: var(--teal-accent);"></i>
        <span id="toastMsg">Changes saved</span>
      </div>

    <?php endif; ?>
  </div>

  <script>
    // Lead Management & Filter System
    let currentLead = null;

    function openLeadDrawerFromRow(rowElem, event) {
      // Avoid firing if link or button inside was clicked
      if (event && (event.target.tagName === 'A' || event.target.tagName === 'BUTTON' || event.target.closest('a') || event.target.closest('button'))) {
        return;
      }
      const raw = rowElem.getAttribute('data-lead');
      if (raw) {
        try {
          const lead = JSON.parse(raw);
          openLeadDrawer(lead);
        } catch (e) {
          console.error(e);
        }
      }
    }

    function openLeadDrawer(lead) {
      currentLead = lead;
      document.getElementById('drawerLeadId').innerText = 'Lead #' + lead.id + ' — ' + (lead.name || 'Prospect');
      
      const dt = new Date(lead.created_at);
      document.getElementById('drawerLeadDate').innerText = 'Submitted: ' + dt.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
      
      document.getElementById('drawerCustomerName').innerText = lead.name || 'Not Provided';
      
      const phoneElem = document.getElementById('drawerCustomerPhone');
      const phoneLink = document.getElementById('drawerPhoneLink');
      if (lead.phone) {
        phoneLink.href = 'tel:' + lead.phone;
        phoneLink.innerHTML = '<i class="fa-solid fa-phone"></i> ' + lead.phone;
        document.getElementById('drawerCallBtn').href = 'tel:' + lead.phone;
        document.getElementById('drawerCallBtn').style.display = 'inline-flex';
        
        const cleanPhone = lead.phone.replace(/\D/g, '');
        const amtText = lead.amount ? ' of ₹' + Number(lead.amount).toLocaleString('en-IN') : '';
        const msg = encodeURIComponent(`Hello ${lead.name || 'there'}! Greetings from Loan81. We have received your inquiry for ${lead.loan_type || 'Loan'}${amtText}. Our advisor is ready to assist you with the best bank rate options.`);
        document.getElementById('drawerWhatsAppBtn').href = `https://wa.me/91${cleanPhone}?text=${msg}`;
        document.getElementById('drawerWhatsAppBtn').style.display = 'inline-flex';
      } else {
        phoneLink.innerText = 'Not Provided';
        document.getElementById('drawerCallBtn').style.display = 'none';
        document.getElementById('drawerWhatsAppBtn').style.display = 'none';
      }

      document.getElementById('drawerCustomerEmail').innerText = lead.email || 'Not Provided';
      document.getElementById('drawerCustomerCity').innerText = lead.city || 'Not Specified';

      document.getElementById('drawerLoanType').innerText = lead.loan_type || 'Personal Loan';
      
      const amtVal = parseFloat(lead.amount) || 0;
      document.getElementById('drawerAmount').innerText = amtVal > 0 ? '₹' + amtVal.toLocaleString('en-IN') : 'Not Specified';

      const incVal = parseFloat(lead.monthly_income) || 0;
      document.getElementById('drawerIncome').innerText = incVal > 0 ? '₹' + incVal.toLocaleString('en-IN') + ' / mo' : 'Not Specified';

      document.getElementById('drawerEmployment').innerText = lead.employment_type || 'Salaried / Self-Employed';
      document.getElementById('drawerSource').innerText = lead.source || 'Website Lead';
      document.getElementById('drawerIp').innerText = lead.ip_address || 'Unknown';

      const msgWrap = document.getElementById('drawerMessageWrap');
      if (lead.message && lead.message.trim() !== '') {
        msgWrap.style.display = 'block';
        document.getElementById('drawerMessage').innerText = lead.message;
      } else {
        msgWrap.style.display = 'none';
      }

      document.getElementById('drawerStatusSelect').value = lead.status || 'New';
      document.getElementById('drawerNotes').value = lead.notes || '';

      document.getElementById('drawerOverlay').classList.add('active');
      document.getElementById('detailDrawer').classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeLeadDrawer() {
      document.getElementById('drawerOverlay').classList.remove('active');
      document.getElementById('detailDrawer').classList.remove('active');
      document.body.style.overflow = '';
      currentLead = null;
    }

    function showToast(message) {
      const toast = document.getElementById('adminToast');
      document.getElementById('toastMsg').innerText = message;
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }

    // Save Status & Notes via AJAX
    function saveLeadChanges() {
      if (!currentLead) return;
      const saveBtn = document.getElementById('saveLeadNotesBtn');
      const newStatus = document.getElementById('drawerStatusSelect').value;
      const newNotes = document.getElementById('drawerNotes').value;

      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
      saveBtn.disabled = true;

      const formData = new FormData();
      formData.append('ajax_action', 'update_lead');
      formData.append('lead_id', currentLead.id);
      formData.append('status', newStatus);
      formData.append('notes', newNotes);

      fetch('index.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
        saveBtn.disabled = false;
        if (data.success) {
          showToast('Lead #' + currentLead.id + ' updated to ' + newStatus);
          
          // Update cached lead data
          currentLead.status = newStatus;
          currentLead.notes = newNotes;

          // Update table row badge
          const rowBadge = document.getElementById('rowStatusBadge-' + currentLead.id);
          if (rowBadge) {
            rowBadge.innerText = newStatus;
            const slug = newStatus.toLowerCase().replace(/\s+/g, '-');
            rowBadge.className = 'status-badge status-' + slug;
          }

          // Update row data-attributes
          const row = document.querySelector(`.lead-row[data-id="${currentLead.id}"]`);
          if (row) {
            row.setAttribute('data-status', newStatus);
            row.setAttribute('data-lead', JSON.stringify(currentLead));
          }

          // Recompute active pending count in KPI
          updateKpiCounts();
        } else {
          alert('Failed to update lead: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(err => {
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
        saveBtn.disabled = false;
        alert('Network error while saving changes.');
      });
    }

    function updateKpiCounts() {
      const allRows = document.querySelectorAll('.lead-row');
      let newCount = 0;
      allRows.forEach(r => {
        if (r.getAttribute('data-status') === 'New') newCount++;
      });
      const kpi = document.getElementById('kpiNewCount');
      if (kpi) kpi.innerText = newCount;
    }

    // Interactive Real-Time Filtering
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const amountFilter = document.getElementById('amountFilter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const filteredCountElem = document.getElementById('filteredCount');
    const activeTagsElem = document.getElementById('activeFilterTags');

    function applyFilters() {
      const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
      const cat = categoryFilter ? categoryFilter.value : '';
      const stat = statusFilter ? statusFilter.value : '';
      const dateVal = dateFilter ? dateFilter.value : '';
      const amtVal = amountFilter ? amountFilter.value : '';

      const rows = document.querySelectorAll('.lead-row');
      let visible = 0;

      const now = new Date();
      const todayStr = now.toISOString().split('T')[0];
      
      const yest = new Date(now);
      yest.setDate(now.getDate() - 1);
      const yestStr = yest.toISOString().split('T')[0];

      const weekAgo = new Date(now);
      weekAgo.setDate(now.getDate() - 7);

      const monthAgo = new Date(now.getFullYear(), now.getMonth(), 1);

      rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const phone = row.getAttribute('data-phone') || '';
        const email = row.getAttribute('data-email') || '';
        const city = row.getAttribute('data-city') || '';
        const category = row.getAttribute('data-category') || '';
        const status = row.getAttribute('data-status') || '';
        const dateStr = row.getAttribute('data-date') || '';
        const amount = parseFloat(row.getAttribute('data-amount')) || 0;

        let matchSearch = true;
        if (query) {
          matchSearch = name.includes(query) || phone.includes(query) || email.includes(query) || city.includes(query);
        }

        let matchCat = true;
        if (cat) {
          matchCat = category === cat;
        }

        let matchStat = true;
        if (stat) {
          matchStat = status === stat;
        }

        let matchDate = true;
        if (dateVal === 'today') {
          matchDate = dateStr === todayStr;
        } else if (dateVal === 'yesterday') {
          matchDate = dateStr === yestStr;
        } else if (dateVal === 'week') {
          matchDate = new Date(dateStr) >= weekAgo;
        } else if (dateVal === 'month') {
          matchDate = new Date(dateStr) >= monthAgo;
        }

        let matchAmt = true;
        if (amtVal === 'below_5l') {
          matchAmt = amount > 0 && amount < 500000;
        } else if (amtVal === '5l_25l') {
          matchAmt = amount >= 500000 && amount <= 2500000;
        } else if (amtVal === '25l_1cr') {
          matchAmt = amount > 2500000 && amount <= 10000000;
        } else if (amtVal === 'above_1cr') {
          matchAmt = amount > 10000000;
        }

        if (matchSearch && matchCat && matchStat && matchDate && matchAmt) {
          row.style.display = '';
          visible++;
        } else {
          row.style.display = 'none';
        }
      });

      if (filteredCountElem) {
        filteredCountElem.innerText = visible;
      }

      // Update active tags
      if (activeTagsElem) {
        let tagsHtml = '';
        if (query) tagsHtml += `<span class="filter-tag">"${query}" <i class="fa-solid fa-xmark" onclick="clearFilter('search')"></i></span>`;
        if (cat) tagsHtml += `<span class="filter-tag">${cat} <i class="fa-solid fa-xmark" onclick="clearFilter('cat')"></i></span>`;
        if (stat) tagsHtml += `<span class="filter-tag">${stat} <i class="fa-solid fa-xmark" onclick="clearFilter('stat')"></i></span>`;
        if (dateVal) tagsHtml += `<span class="filter-tag">${dateVal} <i class="fa-solid fa-xmark" onclick="clearFilter('date')"></i></span>`;
        if (amtVal) tagsHtml += `<span class="filter-tag">${amtVal} <i class="fa-solid fa-xmark" onclick="clearFilter('amt')"></i></span>`;
        activeTagsElem.innerHTML = tagsHtml;
      }
    }

    function clearFilter(type) {
      if (type === 'search' && searchInput) searchInput.value = '';
      if (type === 'cat' && categoryFilter) categoryFilter.value = '';
      if (type === 'stat' && statusFilter) statusFilter.value = '';
      if (type === 'date' && dateFilter) dateFilter.value = '';
      if (type === 'amt' && amountFilter) amountFilter.value = '';
      applyFilters();
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (dateFilter) dateFilter.addEventListener('change', applyFilters);
    if (amountFilter) amountFilter.addEventListener('change', applyFilters);

    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        if (dateFilter) dateFilter.value = '';
        if (amountFilter) amountFilter.value = '';
        applyFilters();
      });
    }

    // Keyboard navigation (Escape to close drawer)
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && document.getElementById('detailDrawer').classList.contains('active')) {
        closeLeadDrawer();
      }
    });
  </script>
</body>
</html>
