<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";
$success = "";

$name = "";
$username = "";
$role = "staff";
$status = "active";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "staff";
    $status = $_POST["status"] ?? "active";

    if ($name === "" || $username === "" || $password === "") {

        $error = "Name, username and password are required.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif (!in_array($role, ["admin", "staff"], true)) {

        $error = "Invalid role.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid status.";

    } else {

        // Check username
        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        $check->bind_param("s", $username);
        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error = "This username already exists.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, username, password, role, status)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssss",
                $name,
                $username,
                $hashed_password,
                $role,
                $status
            );

            if ($stmt->execute()) {

                header(
                    "Location: index.php?success=" .
                    urlencode("User added successfully.")
                );

                exit;

            } else {

                $error = "Failed to add user.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add User - ERP System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f6fa;
        }

        .sidebar {
            min-height: 100vh;
            background: #212529;
        }

        .sidebar a {
            color: #ddd;
            text-decoration: none;
            display: block;
            padding: 10px 15px;
        }

        .sidebar a:hover {
            background: #343a40;
            color: #fff;
        }

        .sidebar .active {
            background: #0d6efd;
            color: #fff;
        }

        .content {
            padding: 25px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

    </style>

</head>

<body>

<div class="container-fluid">

    <div class="row">

        <!-- Sidebar -->

        <div class="col-md-2 p-0 sidebar">

            <div class="p-3 text-white">
                <h4>ERP System</h4>
            </div>

            <a href="../dashboard/index.php">
                Dashboard
            </a>

            <a href="../stock/index.php">
                Products
            </a>

            <a href="../stock/transaction.php">
                Stock Transaction
            </a>

            <a href="../sales/index.php">
                Sales
            </a>

            <a href="../summary/index.php">
                Daily Summary
            </a>

            <a href="../summary/partial.php">
                Partial Summary
            </a>

            <a href="../clients/index.php">
                Retail Clients
            </a>

            <a href="../online-sales/index.php">
                Online Sales
            </a>

            <a href="index.php" class="active">
                Users
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>


        <!-- Main Content -->

        <div class="col-md-10 content">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Add User
                    </h2>

                    <p class="text-muted mb-0">
                        Create a new admin or staff account
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    ← Back to Users
                </a>

            </div>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <div class="card">

                <div class="card-body">

                    <form method="POST">

                        <div class="row">

                            <!-- Name -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="<?= htmlspecialchars($name) ?>"
                                    required
                                >

                            </div>


                            <!-- Username -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Username
                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    class="form-control"
                                    value="<?= htmlspecialchars($username) ?>"
                                    required
                                >

                                <small class="text-muted">
                                    Username must be unique.
                                </small>

                            </div>


                            <!-- Password -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Password
                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    required
                                >

                                <small class="text-muted">
                                    Minimum 6 characters.
                                </small>

                            </div>


                            <!-- Confirm Password -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Confirm Password
                                </label>

                                <input
                                    type="password"
                                    name="confirm_password"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <!-- Role -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Role
                                </label>

                                <select
                                    name="role"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value="staff"
                                        <?= $role === "staff" ? "selected" : "" ?>
                                    >
                                        Staff
                                    </option>

                                    <option
                                        value="admin"
                                        <?= $role === "admin" ? "selected" : "" ?>
                                    >
                                        Admin
                                    </option>

                                </select>

                            </div>


                            <!-- Status -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value="active"
                                        <?= $status === "active" ? "selected" : "" ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $status === "inactive" ? "selected" : "" ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                        </div>


                        <div class="mt-3">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Create User
                            </button>

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>