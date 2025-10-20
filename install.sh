#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="$(php -r "require '${PROJECT_DIR}/src/bootstrap.php'; echo app_config('app.name');" 2>/dev/null || echo 'Application')"
echo "====================================="
echo " ${APP_NAME} Installation"
echo "====================================="

echo "[INFO] Current directory: $PROJECT_DIR"

SCHEMA_FILE="$PROJECT_DIR/config/schema.sql"
SEED_FILE="$PROJECT_DIR/config/seed.sql"

# ----------------------------------------------------------------------
# Step 1: Test database connection
# ----------------------------------------------------------------------
echo
echo "[INFO] Step 1: Testing database connection..."

cd "$PROJECT_DIR"

php -d display_errors=1 -r '
require "src/bootstrap.php";
use App\Database\Database;
try {
    $db = new Database();
    echo "[OK] Database connection successful\n";
} catch (Throwable $e) {
    echo "[ERROR] Database connection failed: {$e->getMessage()}\n";
    exit(1);
}
'

# ----------------------------------------------------------------------
# Step 2: Create tables (config/schema.sql)
# ----------------------------------------------------------------------
if [ -f "$SCHEMA_FILE" ]; then
    echo
    echo "[INFO] Step 2: Creating database tables from config/schema.sql..."
    php -d display_errors=1 -r "
    require 'src/bootstrap.php';
    use App\Database\Database;
    \$db = new Database();
    \$pdo = \$db->pdo();
    \$sql = file_get_contents('$SCHEMA_FILE');
    try {
        \$pdo->exec(\$sql);
        echo '[OK] Tables created successfully' . PHP_EOL;
    } catch (Throwable \$e) {
        echo '[ERROR] Failed to create tables: ' . \$e->getMessage() . PHP_EOL;
        exit(1);
    }
    "
else
    echo
    echo "[WARN] No schema.sql found at: $SCHEMA_FILE"
fi

# ----------------------------------------------------------------------
# Step 3: Seed data (optional config/seed.sql)
# ----------------------------------------------------------------------
if [ -f "$SEED_FILE" ]; then
    echo
    echo "[INFO] Step 3: Seeding initial data from config/seed.sql..."
    php -d display_errors=1 -r "
    require 'src/bootstrap.php';
    use App\Database\Database;
    \$db = new Database();
    \$pdo = \$db->pdo();
    \$sql = file_get_contents('$SEED_FILE');
    try {
        \$pdo->exec(\$sql);
        echo '[OK] Seed data inserted' . PHP_EOL;
    } catch (Throwable \$e) {
        echo '[ERROR] Failed to seed data: ' . \$e->getMessage() . PHP_EOL;
        exit(1);
    }
    "
else
    echo
    echo "[INFO] No seed.sql file found. Skipping data seeding."
fi

echo
echo "[DONE] ${APP_NAME} installation completed."
