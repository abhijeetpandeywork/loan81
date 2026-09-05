<?php
/**
 * Lead Submission API - Loan81
 */
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

// Allow preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get JSON input or Form POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$name = trim($input['name'] ?? $input['fullName'] ?? '');
$phone = trim($input['phone'] ?? $input['mobile'] ?? '');
$email = trim($input['email'] ?? '');

// Priority: loan_type (snake) or loanType (camel)
$loan_type = trim($input['loan_type'] ?? $input['loanType'] ?? '');
if (empty($loan_type)) {
    $loan_type = 'General Loan';
}

$amount = !empty($input['amount']) ? floatval(preg_replace('/[^\d.]/', '', (string)$input['amount'])) : null;
$monthly_income = !empty($input['monthly_income']) ? floatval(preg_replace('/[^\d.]/', '', (string)$input['monthly_income'])) : (!empty($input['monthlyIncome']) ? floatval(preg_replace('/[^\d.]/', '', (string)$input['monthlyIncome'])) : null);
$employment_type = trim($input['employment_type'] ?? $input['employmentType'] ?? '');
$city = trim($input['city'] ?? '');
$message = trim($input['message'] ?? '');
$source = trim($input['source'] ?? 'Website Inquiry');
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit;
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO leads (name, phone, email, loan_type, amount, monthly_income, employment_type, city, message, source, ip_address)
            VALUES (:name, :phone, :email, :loan_type, :amount, :monthly_income, :employment_type, :city, :message, :source, :ip_address)
        ");
        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':loan_type' => $loan_type,
            ':amount' => $amount,
            ':monthly_income' => $monthly_income,
            ':employment_type' => $employment_type,
            ':city' => $city,
            ':message' => $message,
            ':source' => $source,
            ':ip_address' => $ip_address,
        ]);

        $lead_id = $pdo->lastInsertId();

        // Optional: Send alert to loan81.info@gmail.com
        @mail(
            'loan81.info@gmail.com',
            "New Lead Received: $loan_type - $phone",
            "A new lead has been submitted on Loan81:\n\nName: $name\nPhone: $phone\nEmail: $email\nLoan Type: $loan_type\nAmount: ₹$amount\nCity: $city\nSource: $source",
            "From: no-reply@loan81.com\r\nReply-To: $email"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Lead submitted successfully!',
            'lead_id' => $lead_id
        ]);
        exit;
    } catch (\PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    // If DB is offline (e.g. local test mode), succeed gracefully
    echo json_encode([
        'success' => true,
        'message' => 'Lead received in mock mode (Database offline).'
    ]);
    exit;
}
