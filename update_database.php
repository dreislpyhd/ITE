<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<html><head><title>Update Database</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #f8f9fa; font-weight: bold; }
    .btn { display: inline-block; padding: 10px 20px; background: #ff6700; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    .btn:hover { background: #e55a00; }
</style></head><body>";

echo "<h1>🔧 Database Update Tool</h1>";

try {
    // Update Barangay Permit description
    echo "<div class='info'><strong>Updating Barangay Permit description...</strong></div>";
    
    $stmt = $conn->prepare("UPDATE barangay_services SET description = ? WHERE service_name = ?");
    $result = $stmt->execute([
        'Permit for various activities and events in the barangay',
        'Barangay Permit'
    ]);
    
    if ($result) {
        echo "<div class='success'>";
        echo "<h2>✅ Update Successful!</h2>";
        echo "<p><strong>Service:</strong> Barangay Permit</p>";
        echo "<p><strong>Old Description:</strong> Permit to operate business in barangay</p>";
        echo "<p><strong>New Description:</strong> Permit for various activities and events in the barangay</p>";
        echo "</div>";
    }
    
    // Show all barangay services
    echo "<h2>📋 Current Barangay Services</h2>";
    $services = $conn->query("SELECT * FROM barangay_services ORDER BY service_name")->fetchAll();
    
    if (!empty($services)) {
        echo "<table>";
        echo "<tr><th>Service Name</th><th>Description</th><th>Requirements</th></tr>";
        foreach ($services as $service) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($service['service_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($service['description']) . "</td>";
            echo "<td>" . htmlspecialchars($service['requirements'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div style='margin-top: 30px;'>";
    echo "<a href='admin/barangay-hall.php' class='btn'>View Barangay Hall Services</a>";
    echo "<a href='admin/index.php' class='btn'>Go to Admin Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
