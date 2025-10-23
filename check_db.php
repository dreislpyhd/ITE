<?php
// Check database contents
$db = new PDO('sqlite:barangay_management.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== DATABASE CONTENTS ===\n\n";

// Get all tables
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

echo "Total tables: " . count($tables) . "\n\n";

foreach ($tables as $table) {
    $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo str_pad($table, 35) . ": " . str_pad($count, 5, ' ', STR_PAD_LEFT) . " rows\n";
}

echo "\n=== CHECKING SQL FILE ===\n\n";

$sql_content = file_get_contents('barangay_management.sql');
$sql_lines = explode("\n", $sql_content);

echo "SQL file size: " . strlen($sql_content) . " bytes\n";
echo "SQL file lines: " . count($sql_lines) . "\n\n";

// Count tables in SQL file
preg_match_all('/CREATE TABLE (\w+)/', $sql_content, $matches);
$sql_tables = $matches[1];

echo "Tables in SQL file: " . count($sql_tables) . "\n";
echo "Tables: " . implode(', ', $sql_tables) . "\n\n";

// Count INSERT statements per table
foreach ($sql_tables as $table) {
    $pattern = "/INSERT INTO $table/";
    preg_match_all($pattern, $sql_content, $inserts);
    $insert_count = count($inserts[0]);
    
    $db_count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    
    $status = ($insert_count == $db_count) ? "✓" : "✗";
    echo "$status " . str_pad($table, 35) . ": SQL=" . str_pad($insert_count, 5, ' ', STR_PAD_LEFT) . " | DB=" . str_pad($db_count, 5, ' ', STR_PAD_LEFT) . "\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
