<?php
session_start();
require_once __DIR__ . '/../api/db.php';

// Authentication
$correct_pin = '9999997775';
$error = '';

if (isset($_POST['pin'])) {
    if ($_POST['pin'] === $correct_pin) {
        $_SESSION['loan81_admin'] = true;
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

// CSV Export
if ($is_logged_in && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loan81_leads_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Name', 'Phone', 'Email', 'Loan Type', 'Amount (INR)', 'Income (INR)', 'City', 'Source', 'Message']);
    
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, created_at, name, phone, email, loan_type, amount, monthly_income, city, source, message FROM leads ORDER BY id DESC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

$leads = [];
if ($is_logged_in && $pdo) {
    $stmt = $pdo->query("SELECT * FROM leads ORDER BY id DESC LIMIT 200");
    $leads = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Loan81 - Leads Management Portal</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon-81.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background: #f4f8fc; color: #0E2954; }
    .header { background: #0E2954; color: #fff; padding: 16px 28px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .logo { display: flex; align-items: center; gap: 10px; }
    .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
    .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(14,41,84,0.06); padding: 24px; margin-bottom: 24px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
    th { background: #eef5fc; padding: 12px 14px; font-weight: 700; color: #0E2954; border-bottom: 2px solid #cbd5e1; }
    td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    tr:hover { background: #f8fafc; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-blue { background: #e0edff; color: #176AB8; }
    .badge-green { background: #e6f9f4; color: #04C398; border: 1px solid rgba(4,195,152,0.3); }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
    .btn-primary { background: linear-gradient(135deg, #176AB8, #04C398); color: #fff; }
    .btn-green { background: #04C398; color: #fff; }
    .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #475569; }
    .login-box { max-width: 420px; margin: 80px auto; background: #fff; padding: 36px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 15px 35px rgba(14,41,84,0.1); text-align: center; }
    .input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 16px 0; font-size: 1rem; text-align: center; }
    .input:focus { outline: none; border-color: #176AB8; box-shadow: 0 0 0 3px rgba(23,106,184,0.15); }
  </style>
</head>
<body>

  <div class="header">
    <div class="logo">
      <img src="../assets/images/logo-white.png" alt="Loan81" style="height: 38px;">
      <span style="font-size: 0.9rem; opacity: 0.8; margin-left: 10px; font-weight: 500; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 12px;">Admin Leads Portal</span>
    </div>
    <?php if ($is_logged_in): ?>
      <div>
        <a href="?export=csv" class="btn btn-green"><i class="fa-solid fa-file-excel"></i> Export CSV</a>
        <a href="?action=logout" class="btn btn-outline" style="color: #fff; border-color: rgba(255,255,255,0.3); margin-left: 8px;">Logout</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="container">
    <?php if (!$is_logged_in): ?>
      <div class="login-box">
        <img src="../assets/images/logo.png" alt="Loan81" style="height: 48px; margin-bottom: 16px;">
        <h2 style="font-family: 'Plus Jakarta Sans'; margin-bottom: 6px; font-size: 1.4rem;">Advisor Portal Access</h2>
        <p style="color: #64748b; font-size: 0.875rem;">Enter authorization PIN to manage loan applications.</p>
        <?php if ($error): ?>
          <p style="color: #ef4444; margin-top: 12px; font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST">
          <input type="password" name="pin" class="input" placeholder="Enter Access PIN" required autofocus>
          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 13px; font-size: 0.95rem;">Login to Portal</button>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div>
            <h2 style="font-family: 'Plus Jakarta Sans';">Inbound Customer Leads (<?= count($leads) ?>)</h2>
            <p style="color: #64748b; font-size: 0.85rem;">Real-time inquiries received from Loan81 website</p>
          </div>
          <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i> Live System Connected</span>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Date / Time</th>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Loan Category</th>
                <th>Amount Needed</th>
                <th>Monthly Income</th>
                <th>City</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($leads)): ?>
                <tr><td colspan="9" style="text-align: center; padding: 40px; color: #94a3b8;">No leads submitted yet. New inquiries will appear here automatically.</td></tr>
              <?php else: ?>
                <?php foreach ($leads as $l): ?>
                  <tr>
                    <td>#<?= $l['id'] ?></td>
                    <td style="color: #64748b; font-size: 0.8rem;"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($l['name'] ?: 'Prospect') ?></strong></td>
                    <td>
                      <a href="tel:<?= htmlspecialchars($l['phone']) ?>" style="color: #0052ff; font-weight: 600; text-decoration: none;">
                        <i class="fa-solid fa-phone" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($l['phone']) ?>
                      </a>
                    </td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($l['loan_type']) ?></span></td>
                    <td><strong><?= $l['amount'] ? '₹' . number_format($l['amount']) : '-' ?></strong></td>
                    <td><?= $l['monthly_income'] ? '₹' . number_format($l['monthly_income']) : '-' ?></td>
                    <td><?= htmlspecialchars($l['city'] ?: '-') ?></td>
                    <td>
                      <a href="https://wa.me/91<?= preg_replace('/\D/', '', $l['phone']) ?>?text=<?= urlencode('Hello ' . ($l['name'] ?: 'there') . '! This is from Loan81 regarding your inquiry for ' . $l['loan_type'] . '.') ?>" target="_blank" class="btn btn-green" style="padding: 4px 8px; font-size: 0.75rem;">
                        <i class="fa-brands fa-whatsapp"></i> Chat
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
