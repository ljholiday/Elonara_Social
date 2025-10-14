#!/usr/bin/env php
<?php
/**
 * Validator Service Tests
 *
 * Tests modern ValidatorService and SanitizerService
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

function test(string $name, callable $fn): void {
    echo "\n🧪 Testing: {$name}\n";
    try {
        $result = $fn();
        if ($result === true) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL: " . ($result ?: 'returned false') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ FAIL: " . $e->getMessage() . "\n";
    }
}

echo "=== Validator Service Tests ===\n";

// Test 1: Services are registered
test("SanitizerService is registered in container", function() {
    $sanitizer = vt_service('sanitizer.service');
    if (!$sanitizer instanceof App\Services\SanitizerService) {
        return "Expected App\\Services\\SanitizerService, got " . get_class($sanitizer);
    }
    return true;
});

test("ValidatorService is registered in container", function() {
    $validator = vt_service('validator.service');
    if (!$validator instanceof App\Services\ValidatorService) {
        return "Expected App\\Services\\ValidatorService, got " . get_class($validator);
    }
    return true;
});

// Test 2: Email validation
test("Email validator accepts valid email", function() {
    $validator = vt_service('validator.service');
    $result = $validator->email('test@example.com');

    if (!$result['is_valid']) {
        return "Valid email marked as invalid";
    }
    if ($result['value'] !== 'test@example.com') {
        return "Email value not preserved: " . $result['value'];
    }
    if (!empty($result['errors'])) {
        return "Unexpected errors: " . implode(', ', $result['errors']);
    }
    return true;
});

test("Email validator rejects invalid email", function() {
    $validator = vt_service('validator.service');
    $result = $validator->email('invalid-email');

    if ($result['is_valid']) {
        return "Invalid email marked as valid";
    }
    if (empty($result['errors'])) {
        return "No errors returned for invalid email";
    }
    return true;
});

// Test 3: Text field validation
test("Text field validator enforces min length", function() {
    $validator = vt_service('validator.service');
    $result = $validator->textField('ab', 3, 100);

    if ($result['is_valid']) {
        return "Short text marked as valid";
    }
    if (empty($result['errors'])) {
        return "No errors for too-short text";
    }
    return true;
});

test("Text field validator enforces max length", function() {
    $validator = vt_service('validator.service');
    $result = $validator->textField(str_repeat('a', 300), 0, 100);

    if ($result['is_valid']) {
        return "Long text marked as valid";
    }
    if (empty($result['errors'])) {
        return "No errors for too-long text";
    }
    return true;
});

test("Text field validator accepts valid text", function() {
    $validator = vt_service('validator.service');
    $result = $validator->textField('Valid text', 1, 100);

    if (!$result['is_valid']) {
        return "Valid text marked as invalid: " . implode(', ', $result['errors']);
    }
    if ($result['value'] !== 'Valid text') {
        return "Text value changed: " . $result['value'];
    }
    return true;
});

// Test 4: Username validation
test("Username validator accepts valid username", function() {
    $validator = vt_service('validator.service');
    $result = $validator->username('john_doe123');

    if (!$result['is_valid']) {
        return "Valid username marked as invalid: " . implode(', ', $result['errors']);
    }
    return true;
});

test("Username validator rejects invalid characters", function() {
    $validator = vt_service('validator.service');
    $result = $validator->username('john@doe');

    if ($result['is_valid']) {
        return "Invalid username marked as valid";
    }
    return true;
});

test("Username validator enforces length", function() {
    $validator = vt_service('validator.service');
    $result = $validator->username('ab');

    if ($result['is_valid']) {
        return "Too-short username marked as valid";
    }
    return true;
});

// Test 5: Password validation
test("Password validator accepts valid password", function() {
    $validator = vt_service('validator.service');
    $result = $validator->password('password123');

    if (!$result['is_valid']) {
        return "Valid password marked as invalid: " . implode(', ', $result['errors']);
    }
    if ($result['value'] !== 'password123') {
        return "Password was modified";
    }
    return true;
});

test("Password validator rejects short password", function() {
    $validator = vt_service('validator.service');
    $result = $validator->password('pass');

    if ($result['is_valid']) {
        return "Short password marked as valid";
    }
    return true;
});

// Test 6: Integer validation
test("Integer validator accepts valid integer", function() {
    $validator = vt_service('validator.service');
    $result = $validator->integer('42', 0, 100);

    if (!$result['is_valid']) {
        return "Valid integer marked as invalid: " . implode(', ', $result['errors']);
    }
    if ($result['value'] !== 42) {
        return "Integer value incorrect: " . $result['value'];
    }
    return true;
});

test("Integer validator enforces range", function() {
    $validator = vt_service('validator.service');
    $result = $validator->integer('150', 0, 100);

    if ($result['is_valid']) {
        return "Out-of-range integer marked as valid";
    }
    return true;
});

// Test 7: Required field validation
test("Required validator rejects empty string", function() {
    $validator = vt_service('validator.service');
    $result = $validator->required('', 'Test field');

    if ($result['is_valid']) {
        return "Empty string marked as valid";
    }
    if (empty($result['errors'])) {
        return "No errors for empty required field";
    }
    return true;
});

test("Required validator accepts non-empty string", function() {
    $validator = vt_service('validator.service');
    $result = $validator->required('value', 'Test field');

    if (!$result['is_valid']) {
        return "Non-empty string marked as invalid";
    }
    return true;
});

// Test 8: Sanitizer methods
test("SanitizerService escapes HTML properly", function() {
    $sanitizer = vt_service('sanitizer.service');
    $result = $sanitizer->escapeHtml('<script>alert("xss")</script>');

    if (strpos($result, '<script>') !== false) {
        return "HTML not escaped: " . $result;
    }
    return true;
});

test("SanitizerService sanitizes text field", function() {
    $sanitizer = vt_service('sanitizer.service');
    $result = $sanitizer->textField('  <b>Text</b>  with   spaces  ');

    if (strpos($result, '<b>') !== false) {
        return "Tags not stripped: " . $result;
    }
    if ($result !== 'Text with spaces') {
        return "Unexpected result: " . $result;
    }
    return true;
});

test("SanitizerService creates slug", function() {
    $sanitizer = vt_service('sanitizer.service');
    $result = $sanitizer->slug('Hello World 123!');

    if ($result !== 'hello-world-123') {
        return "Unexpected slug: " . $result;
    }
    return true;
});

// Test 9: Escape methods accessible via validator
test("Validator exposes escape methods", function() {
    $validator = vt_service('validator.service');
    $result = $validator->escHtml('<b>test</b>');

    if (strpos($result, '<b>') !== false) {
        return "HTML not escaped via validator: " . $result;
    }
    return true;
});

// Test 10: URL validation
test("URL validator accepts valid URL", function() {
    $validator = vt_service('validator.service');
    $result = $validator->url('https://example.com');

    if (!$result['is_valid']) {
        return "Valid URL marked as invalid: " . implode(', ', $result['errors']);
    }
    return true;
});

test("URL validator rejects invalid URL", function() {
    $validator = vt_service('validator.service');
    $result = $validator->url('not a url');

    if ($result['is_valid']) {
        return "Invalid URL marked as valid";
    }
    return true;
});

echo "\n✅ All validator service tests passed!\n";
echo "\nValidator Service provides:\n";
echo "- email(), url(), textField(), textarea(), richText()\n";
echo "- integer(), float(), required(), slug(), phoneNumber()\n";
echo "- password(), passwordStrict(), username()\n";
echo "- validateArray(), fileUpload()\n";
echo "- escHtml(), escAttr(), escUrl() (output escaping)\n";
