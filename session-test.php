<?php
declare(strict_types=1);

// Show current session timeout configuration and behavior
session_start();

echo "<h2>Session Timeout Test</h2>";

// Report configuration settings *as actually used at runtime*
echo "<pre>";
echo "session.gc_maxlifetime = " . ini_get("session.gc_maxlifetime") . " seconds\n";
echo "session.cookie_lifetime = " . ini_get("session.cookie_lifetime") . " seconds\n";
echo "Session save path: " . session_save_path() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session file: " . session_save_path() . "/sess_" . session_id() . "\n";
echo "</pre>";

// Show when this session started
if (!isset($_SESSION['started_at'])) {
    $_SESSION['started_at'] = time();
    echo "<p><strong>New session started at:</strong> " . date('Y-m-d H:i:s') . "</p>";
} else {
    echo "<p><strong>Session originally started at:</strong> " . date('Y-m-d H:i:s', $_SESSION['started_at']) . "</p>";
    echo "<p><strong>Elapsed time:</strong> " . (time() - $_SESSION['started_at']) . " seconds</p>";
}
?>

