<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid user ID."));
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Logged-in User ID
|--------------------------------------------------------------------------
*/

$current_user_id = 0;

if (isset($_SESSION["user_id"])) {
    $current_user_id = (int)$_SESSION["user_id"];
} elseif (isset($_SESSION["id"])) {
    $current_user_id = (int)$_SESSION["id"];
} elseif (
    isset($_SESSION["user"]) &&
    isset($_SESSION["user"]["id"])
) {
    $current_user_id = (int)$_SESSION["user"]["id"];
}


/*
|--------------------------------------------------------------------------
| Prevent Self Delete
|--------------------------------------------------------------------------
*/

if ($current_user_id === $id) {

    header(
        "Location: index.php?error=" .
        urlencode("You cannot delete your own account.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check User Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id FROM users WHERE id = ? LIMIT 1"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: index.php?error=" .
        urlencode("User not found.")
    );

    exit;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Delete User
|--------------------------------------------------------------------------
*/

$delete = $conn->prepare(
    "DELETE FROM users WHERE id = ?"
);

$delete->bind_param("i", $id);

if ($delete->execute()) {

    $delete->close();

    header(
        "Location: index.php?success=" .
        urlencode("User deleted successfully.")
    );

    exit;

} else {

    $delete->close();

    header(
        "Location: index.php?error=" .
        urlencode("Failed to delete user.")
    );

    exit;
}