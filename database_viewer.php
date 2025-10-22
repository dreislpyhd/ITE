<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

// Security check - only allow local access
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    die('Access denied. Only local access allowed.');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$action = $_GET['action'] ?? 'tables';
$table = $_GET['table'] ?? '';
$limit = 50; // Limit rows displayed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer - Barangay 172</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-container { max-height: 400px; overflow-y: auto; }
        .code { font-family: 'Courier New', monospace; background: #f3f4f6; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Database Viewer</h1>
                <div class="text-sm text-gray-600">
                    <span class="code"><?= DB_HOST ?></span> / <span class="code"><?= DB_NAME ?></span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="mb-6">
                <a href="?action=tables" class="px-4 py-2 bg-blue-500 text-white rounded <?= $action == 'tables' ? 'bg-blue-600' : '' ?>">Tables</a>
                <a href="?action=structure" class="px-4 py-2 bg-green-500 text-white rounded ml-2 <?= $action == 'structure' ? 'bg-green-600' : '' ?>">Structure</a>
                <a href="?action=connections" class="px-4 py-2 bg-purple-500 text-white rounded ml-2 <?= $action == 'connections' ? 'bg-purple-600' : '' ?>">Connections</a>
            </nav>

            <?php if ($action == 'tables'): ?>
                <!-- Tables List -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold mb-4">Database Tables</h2>
                    <?php
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($tables as $tableName): ?>
                            <?php
                            $count = $pdo->query("SELECT COUNT(*) FROM `$tableName`")->fetchColumn();
                            ?>
                            <div class="bg-gray-50 p-4 rounded-lg border">
                                <h3 class="font-semibold text-lg mb-2"><?= htmlspecialchars($tableName) ?></h3>
                                <p class="text-sm text-gray-600 mb-3"><?= number_format($count) ?> records</p>
                                <div class="flex space-x-2">
                                    <a href="?action=view&table=<?= urlencode($tableName) ?>" class="text-blue-600 hover:text-blue-800 text-sm">View Data</a>
                                    <a href="?action=structure&table=<?= urlencode($tableName) ?>" class="text-green-600 hover:text-green-800 text-sm">Structure</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($action == 'view' && $table): ?>
                <!-- Table Data -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold mb-4">Table: <?= htmlspecialchars($table) ?></h2>
                    <a href="?action=tables" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">← Back to Tables</a>
                    
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM `$table` LIMIT " . (int)$limit);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $total = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    ?>
                    
                    <div class="mb-4 text-sm text-gray-600">
                        Showing <?= count($rows) ?> of <?= number_format($total) ?> records
                    </div>
                    
                    <?php if ($rows): ?>
                        <div class="table-container">
                            <table class="w-full border-collapse border border-gray-300">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <?php foreach (array_keys($rows[0]) as $column): ?>
                                            <th class="border border-gray-300 px-3 py-2 text-left"><?= htmlspecialchars($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr class="hover:bg-gray-50">
                                            <?php foreach ($row as $value): ?>
                                                <td class="border border-gray-300 px-3 py-2 text-sm">
                                                    <?= htmlspecialchars($value ?? 'NULL') ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No data found in this table.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($action == 'structure'): ?>
                <!-- Database Structure -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold mb-4">Database Structure</h2>
                    
                    <?php if ($table): ?>
                        <a href="?action=structure" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">← Back to All Tables</a>
                        <h3 class="text-xl font-semibold mb-4">Table: <?= htmlspecialchars($table) ?></h3>
                        
                        <?php
                        $stmt = $pdo->prepare("DESCRIBE `$table`");
                        $stmt->execute();
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <table class="w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Field</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Type</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Null</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Key</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Default</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columns as $column): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2 font-mono"><?= htmlspecialchars($column['Field']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($column['Type']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($column['Null']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($column['Key']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($column['Default'] ?? 'NULL') ?></td>
                                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($column['Extra']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <?php
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($tables as $tableName): ?>
                                <div class="bg-gray-50 p-4 rounded-lg border">
                                    <h3 class="font-semibold text-lg mb-2"><?= htmlspecialchars($tableName) ?></h3>
                                    <a href="?action=structure&table=<?= urlencode($tableName) ?>" class="text-blue-600 hover:text-blue-800 text-sm">View Structure</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action == 'connections'): ?>
                <!-- Database Connections -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold mb-4">Database Connections & Relationships</h2>
                    
                    <?php
                    // Get foreign key relationships
                    $stmt = $pdo->query("
                        SELECT 
                            TABLE_NAME,
                            COLUMN_NAME,
                            CONSTRAINT_NAME,
                            REFERENCED_TABLE_NAME,
                            REFERENCED_COLUMN_NAME
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");
                    $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if ($relationships): ?>
                        <h3 class="text-xl font-semibold mb-4">Foreign Key Relationships</h3>
                        <table class="w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Table</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Column</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">References</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Referenced Column</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relationships as $rel): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2 font-mono"><?= htmlspecialchars($rel['TABLE_NAME']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2 font-mono"><?= htmlspecialchars($rel['COLUMN_NAME']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2 font-mono"><?= htmlspecialchars($rel['REFERENCED_TABLE_NAME']) ?></td>
                                        <td class="border border-gray-300 px-3 py-2 font-mono"><?= htmlspecialchars($rel['REFERENCED_COLUMN_NAME']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-gray-500">No foreign key relationships found.</p>
                    <?php endif; ?>
                    
                    <div class="mt-8">
                        <h3 class="text-xl font-semibold mb-4">Database Configuration</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p><strong>Host:</strong> <span class="code"><?= DB_HOST ?></span></p>
                            <p><strong>Database:</strong> <span class="code"><?= DB_NAME ?></span></p>
                            <p><strong>User:</strong> <span class="code"><?= DB_USER ?></span></p>
                            <p><strong>Charset:</strong> <span class="code"><?= DB_CHARSET ?></span></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
