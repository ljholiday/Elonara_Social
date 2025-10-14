<?php
// Run php clear-cache.php
session_start();

// Clear ALL transients
if (isset($_SESSION['app_transients'])) {
    $count = count($_SESSION['app_transients']);
    unset($_SESSION['app_transients']);
    echo "Cleared $count cached items from session.<br><br>";
} else {
    echo "No cached items found.<br><br>";
}
