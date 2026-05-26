<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('scheduled publishing controls document availability', function () {
    $nowUtc = '2026-05-25 12:00:00';

    assert_true(
        !document_is_available('2026-05-25 13:00:00', $nowUtc),
        'expected a future scheduled document to be unavailable'
    );

    assert_true(
        document_is_available('2026-05-25 11:00:00', $nowUtc),
        'expected a published document to be available'
    );

    assert_true(
        document_is_available(null, $nowUtc),
        'expected an unscheduled document to be available immediately'
    );
});
test('documents can be found by partial case-insensitive title search', function () {
    $lowercaseResults = find_documents_by_title('welcome');

    assert_true(
        count($lowercaseResults) === 1,
        'expected one matching document for lowercase search'
    );

    assert_true(
        $lowercaseResults[0]['title'] === 'Welcome Packet',
        'expected Welcome Packet to match lowercase search'
    );

    $uppercaseResults = find_documents_by_title('WELCOME');

    assert_true(
        count($uppercaseResults) === 1,
        'expected uppercase search to match'
    );

    $mixedCaseResults = find_documents_by_title('wElcome');

    assert_true(
        count($mixedCaseResults) === 1,
        'expected mixed-case search to match'
    );

    assert_true(
        $mixedCaseResults[0]['title'] === 'Welcome Packet',
        'expected Welcome Packet to match mixed-case search'
    );

    $partialResults = find_documents_by_title('packet');

    assert_true(
        count($partialResults) === 1,
        'expected partial title search to match'
    );

    $missingResults = find_documents_by_title('does-not-exist');

    assert_true(
        count($missingResults) === 0,
        'expected no results for an unknown title'
    );
});
echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
