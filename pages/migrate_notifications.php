<?php
/**
 * Migration Script - Add related_id to notifications table
 * Run this script by visiting it in your browser to update your database schema.
 */
session_start();
require_once "../config/db.php";

// Redirect if not logged in (basic security)
if (!isset($_SESSION["user_id"])) {
    die("Please log in to run the migration.");
}

echo "<h2>Database Migration</h2>";

// Check if the column already exists
$checkSql = "SHOW COLUMNS FROM notifications LIKE 'related_id'";
$result = mysqli_query($conn, $checkSql);
$exists = mysqli_num_rows($result) > 0;

if ($exists) {
    echo "<p style='color: green;'>The 'related_id' column already exists in the 'notifications' table. No action needed.</p>";
} else {
    echo "<p>Adding 'related_id' column to 'notifications' table...</p>";
    
    $alterSql = "ALTER TABLE notifications ADD COLUMN related_id INT NULL AFTER type";
    if (mysqli_query($conn, $alterSql)) {
        echo "<p style='color: green;'>Successfully added 'related_id' column!</p>";
    } else {
        echo "<p style='color: red;'>Error adding column: " . mysqli_error($conn) . "</p>";
    }
}

echo "<p><a href='home.php'>Back to Feed</a></p>";
?>
