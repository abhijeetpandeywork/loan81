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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; color: #0f172a; }
    .header { background: #0a1b39; color: #fff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; }
    .logo { font-size: 1.4rem; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; color: #fff; display: flex; align-items: center; gap: 8px; }
    .logo span { color: #0052ff; }
    .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
    .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
    th { background: #f1f5f9; padding: 12px 14px; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
    td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    tr:hover { background: #f8fafc; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-blue { background: #e0edff; color: #0052ff; }
    .badge-green { background: #dcfce7; color: #15803d; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
    .btn-primary { background: #0052ff; color: #fff; }
    .btn-green { background: #25d366; color: #fff; }
    .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #475569; }
    .login-box { max-width: 400px; margin: 80px auto; background: #fff; padding: 34px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06); text-align: center; }
    .input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 16px 0; font-size: 1rem; text-align: center; }
  </style>
</head>
<body>

  <div class="header">
    <div class="logo">Loan<span>81</span> Leads Portal</div>
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
        <h2 style="font-family: 'Plus Jakarta Sans'; margin-bottom: 8px;">Advisor Access</h2>
        <p style="color: #64748b; font-size: 0.9rem;">Enter admin security PIN to view customer inquiries.</p>
        <?php if ($error): ?>
          <p style="color: #ef4444; margin-top: 12px; font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST">
          <input type="password" name="pin" class="input" placeholder="Enter Access PIN" required autofocus>
          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">Login to Portal</button>
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
