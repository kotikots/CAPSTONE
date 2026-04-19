<?php
/**
 * fix_driver_emails.php
 * One-time script to fix drivers who accidentally have the 'admin@pare.local' email.
 */
require_once 'config/db.php';

echo "<h1>Syncing Driver Data...</h1>";

try {
    // 1. Find the driver(s) with the conflicting email
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM drivers WHERE email = 'admin@pare.local'");
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($drivers)) {
        echo "<p style='color: green;'>✅ No drivers found with conflicting admin email.</p>";
    } else {
        foreach ($drivers as $dr) {
            // Generate a more unique email based on their name
            $safeName = strtolower(str_replace(' ', '.', $dr['full_name']));
            $newEmail = $safeName . "@pare.local";

            echo "<p>Found Driver: <b>" . htmlspecialchars($dr['full_name']) . "</b></p>";
            echo "<p>Old Email: " . htmlspecialchars($dr['email']) . "</p>";
            echo "<p>Updating to: <b>$newEmail</b>...</p>";

            $update = $pdo->prepare("UPDATE drivers SET email = ? WHERE id = ?");
            $update->execute([$newEmail, $dr['id']]);

            echo "<p style='color: blue;'>Done.</p><hr>";
        }
        echo "<p style='color: green;'><b>✅ Data cleanup complete.</b></p>";
    }

    echo "<p><a href='admin/drivers.php'>Return to Admin Panel</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
