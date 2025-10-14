<?php
/**
 * Debug Database Issues
 */

// Load bootstrap (compatible with legacy and modern layouts)
$bootstrapPaths = [
    dirname(__DIR__) . '/includes/bootstrap.php',
    dirname(__DIR__) . '/legacy/includes/bootstrap.php',
];

$bootstrapLoaded = false;
foreach ($bootstrapPaths as $bootstrapPath) {
    if (file_exists($bootstrapPath)) {
        require_once $bootstrapPath;
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    fwrite(STDERR, "Unable to locate Elonara Social bootstrap file.\n");
    exit(1);
}

$db = VT_Database::getInstance();

echo "Debugging Database Issues...\n\n";

// Test basic connection
echo "Testing connection: ";
try {
    $result = $db->getVar("SELECT 1");
    echo "Connected (result: $result)\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    exit;
}

// Test table existence
echo "Testing vt_config table: ";
$exists = $db->getVar("SHOW TABLES LIKE 'vt_config'");
echo $exists ? "Exists\n" : "Missing\n";

// Test simple insert with detailed error reporting
echo "Testing insert with debugging: ";

$test_data = [
    'option_name' => 'debug_test_' . time(),
    'option_value' => 'debug_value',
    'autoload' => 'no'
];

try {
    echo "\nAttempting insert with data: " . json_encode($test_data) . "\n";

    // Direct PDO test
    $pdo = $db->pdo ?? null;
    if (!$pdo) {
        echo "PDO instance not accessible\n";
        exit;
    }

    $query = "INSERT INTO vt_config (option_name, option_value, autoload) VALUES (?, ?, ?)";
    echo "Query: $query\n";

    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$test_data['option_name'], $test_data['option_value'], $test_data['autoload']]);

    if ($result) {
        $insert_id = $pdo->lastInsertId();
        echo "Direct PDO insert successful, ID: $insert_id\n";

        // Clean up
        $pdo->exec("DELETE FROM vt_config WHERE option_name = '{$test_data['option_name']}'");
    } else {
        echo "Direct PDO insert failed\n";
        print_r($stmt->errorInfo());
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Test VT_Database insert method directly
echo "\nTesting VT_Database insert method: ";
try {
    $test_data2 = [
        'option_name' => 'vt_test_' . time(),
        'option_value' => 'vt_value',
        'autoload' => 'no'
    ];

    $result = $db->insert('config', $test_data2);

    if ($result) {
        echo "VT_Database insert successful, ID: $result\n";

        // Verify
        $retrieved = $db->getVar("SELECT option_value FROM vt_config WHERE option_name = '{$test_data2['option_name']}'");
        echo "Retrieved value: $retrieved\n";

        // Clean up
        $db->delete('config', ['option_name' => $test_data2['option_name']]);

    } else {
        echo "VT_Database insert failed\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
