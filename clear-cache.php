<?php
// Run php clear-cache.php
session_start();

// Clear ALL transients
if (isset($_SESSION['vt_transients'])) {
    $count = count($_SESSION['vt_transients']);
    unset($_SESSION['vt_transients']);
    echo "Cleared $count cached items from session.<br><br>";
} else {
    echo "No cached items found.<br><br>";
}
