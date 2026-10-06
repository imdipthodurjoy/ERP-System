<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = "";
$error = "";

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$sql = "SELECT id, name, username, role, status, created_at
        FROM users
        ORDER BY id DESC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users - ERP System</title>

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

        .table th {
            white-space: nowrap;
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
                    <h2 class="mb-1">Users</h2>
                    <p class="text-muted mb-0">
                        Manage admin and staff accounts
                    </p>
                </div>

                <a href="add.php" class="btn btn-primary">
                    + Add User
                </a>

            </div>


            <!-- Messages -->

            <?php if ($success): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <!-- Users Table -->

            <div class="card">

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover align-middle mb-0">

                            <thead class="table-dark">

                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if ($result && $result->num_rows > 0): ?>

                                <?php while ($user = $result->fetch_assoc()): ?>

                                    <tr>

                                        <td>
                                            <?= (int)$user['id'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($user['name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </td>

                                        <td>

                                            <?php if ($user['role'] === 'admin'): ?>

                                                <span class="badge bg-danger">
                                                    Admin
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-primary">
                                                    Staff
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ($user['status'] === 'active'): ?>

                                                <span class="badge bg-success">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-secondary">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars($user['created_at']) ?>
                                        </td>

                                        <td>

                                            <a
                                                href="edit.php?id=<?= (int)$user['id'] ?>"
                                                class="btn btn-sm btn-warning"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                href="delete.php?id=<?= (int)$user['id'] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this user?');"
                                            >
                                                Delete
                                            </a>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="7" class="text-center py-4">

                                        <div class="text-muted">
                                            No users found.
                                        </div>

                                        <a href="add.php" class="btn btn-primary mt-2">
                                            + Add First User
                                        </a>

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

</body>
</html>