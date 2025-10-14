#!/bin/bash

# Elonara Social Quick Test Script
# Run this after making changes to verify nothing is broken

echo "Running Elonara Social Tests..."
echo

# Check if we're in the right directory (support both legacy and modern layouts)
if { [ -f "index.php" ] && [ -d "includes" ]; } || { [ -f "composer.json" ] && [ -d "src" ] && [ -d "public" ]; }; then
    :
else
    echo "Error: Run this script from the Elonara Social root directory (contains composer.json/src/public)"
    exit 1
fi

failed=0

# Run each test
for test_file in tests/*.php; do
    if [[ "$test_file" == *"debug"* ]]; then
        continue  # Skip debug files
    fi

    echo "Running $(basename $test_file)..."
    php "$test_file"

    if [ $? -ne 0 ]; then
        failed=1
    fi
    echo
done

# Summary
if [ $failed -eq 0 ]; then
    echo "All tests passed."
    exit 0
else
    echo "Some tests failed. Fix issues before deploying."
    exit 1
fi
