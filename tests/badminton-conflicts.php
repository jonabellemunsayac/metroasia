<?php
declare(strict_types=1);

// Load pure conflict helpers without dispatching an API request.
$source = file_get_contents(__DIR__ . '/../api.php');
foreach (['is_miami_court', 'is_wooden_court', 'related_booking_conflict_court_ids', 'court_booking_resources_conflict', 'court_block_applies'] as $name) {
    if (!preg_match('/^function ' . $name . '\(.*?^}/ms', $source, $match)) {
        throw new RuntimeException('Missing helper: ' . $name);
    }
    eval($match[0]);
}
foreach ([10, 11, 12] as $court) {
    $checks = [
        court_booking_resources_conflict(1, $court),
        court_booking_resources_conflict($court, 1),
        court_booking_resources_conflict($court, $court),
        court_block_applies($court, 'Badminton', $court, 'Badminton'),
        !court_block_applies(7, 'Pickleball', $court, 'Badminton'),
        !court_booking_resources_conflict(8, $court),
        !court_booking_resources_conflict(9, $court),
        court_block_applies(1, null, $court, 'Badminton'),
        !court_booking_resources_conflict(2, $court),
        !court_booking_resources_conflict(3, $court),
    ];
    if (in_array(false, $checks, true)) {
        throw new RuntimeException('Shared-court conflict failed for ' . $court);
    }
}
if (court_booking_resources_conflict(10, 11) || court_booking_resources_conflict(11, 12)) {
    throw new RuntimeException('Separate wooden courts must remain independently bookable.');
}
echo "Badminton server conflict checks passed.\n";

// Verify both parent groups, including block propagation and unrelated courts.
foreach ([1, 2, 7, 8, 9, 10, 11, 12] as $existing) {
    foreach ([1, 2, 7, 8, 9, 10, 11, 12] as $requested) {
        $expected = $existing === $requested
            || ($existing === 1 && in_array($requested, [10, 11, 12], true))
            || ($requested === 1 && in_array($existing, [10, 11, 12], true))
            || ($existing === 2 && in_array($requested, [7, 8, 9], true))
            || ($requested === 2 && in_array($existing, [7, 8, 9], true));
        if (court_booking_resources_conflict($existing, $requested) !== $expected
            || court_block_applies($existing, null, $requested, 'Badminton') !== $expected) {
            throw new RuntimeException('Incorrect parent-court conflict mapping.');
        }
    }
}
echo "Lakers and Miami booking/block isolation passed.\n";
