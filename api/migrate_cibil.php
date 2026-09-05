<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/db.php';

if (!$pdo) {
    die("Error: Database connection failed.");
}

try {
    $pdo->exec("ALTER TABLE leads ADD COLUMN cibil_score VARCHAR(50) NULL AFTER employment_type");
    echo "Column cibil_score added successfully.\n";
} catch (Exception $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

$stmt = $pdo->query("DESCRIBE leads");
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . ($row['Null'] ?? '') . "\n";
}
