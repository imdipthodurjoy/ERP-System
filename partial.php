<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   Date Filter
========================= */

$from_date = $_GET['from_date'] ?? date('Y-m-d');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');

$error = "";

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {

    $from_date = $_GET['from_date'];
    $to_date   = $_GET['to_date'];

    if ($from_date > $to_date) {
        $error = "From Date cannot be greater than To Date.";
    }
}


/* =========================
   Default Values
========================= */

$total_transactions = 0;
$total_sales = 0;
$total_paid = 0;
$total_due = 0;

$stock_in = 0;
$stock_out = 0;
$stock_returned = 0;

$sales = [];


/* =========================
   Summary Data
========================= */

if (empty($error)) {

    /* Sales Summary */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_transactions,
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(SUM(paid_amount), 0) AS total_paid,
            COALESCE(SUM(due_amount), 0) AS total_due
        FROM sales
        WHERE sale_date BETWEEN ? AND ?
    ");

    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();

    $total_transactions = $summary['total_transactions'] ?? 0;
    $total_sales = $summary['total_sales'] ?? 0;
    $total_paid = $summary['total_paid'] ?? 0;
    $total_due = $summary['total_due'] ?? 0;

    $stmt->close();


    /* =========================
       Stock Summary
    ========================= */

    $stmt = $conn->prepare("
        SELECT
            type,
            COALESCE(SUM(quantity), 0) AS total
        FROM stock_transactions
        WHERE transaction_date BETWEEN ? AND ?
        GROUP BY type
    ");

    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        if ($row['type'] === 'in') {
            $stock_in = $row['total'];
        }

        elseif ($row['type'] === 'out') {
            $stock_out = $row['total'];
        }

        elseif ($row['type'] === 'returned') {
            $stock_returned = $row['total'];
        }
    }

    $stmt->close();


    /* =========================
       Sales List
    ========================= */

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.client_id,
            s.total_amount,
            s.paid_amount,
            s.due_amount,
            s.sale_type,
            s.sale_date,
            c.name AS client_name
        FROM sales s
        LEFT JOIN clients c
            ON s.client_id = c.id
        WHERE s.sale_date BETWEEN ? AND ?
        ORDER BY s.sale_date DESC, s.id DESC
    ");

    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Partial Summary - ERP System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
        }

        .sidebar {
            min-height: 100vh;
            background: #212529;
            color: white;
        }

        .sidebar a {
            color: #ddd;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
        }

        .sidebar a:hover {
            background: #343a40;
            color: white;
        }

        .sidebar .active {
            background: #0d6efd;
            color: white;
        }

        .topbar {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
        }

        .summary-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .summary-value {
            font-size: 24px;
            font-weight: 700;
        }

        @media print {

            .sidebar,
            .topbar,
            .no-print {
                display: none !important;
            }

            .main-content {
                width: 100% !important;
            }

            body {
                background: white;
            }
        }

    </style>

</head>

<body>

<div class="container-fluid">

    <div class="row">

        <!-- ================= Sidebar ================= -->

        <div class="col-md-2 sidebar p-0">

            <h4 class="text-center py-4">
                ERP SYSTEM
            </h4>

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

            <a href="index.php">
                Daily Summary
            </a>

            <a href="partial.php" class="active">
                Partial Summary
            </a>

            <a href="../clients/index.php">
                Retail Clients
            </a>

            <a href="../online-sales/index.php">
                Online Sales
            </a>

            <a href="../users/index.php">
                Users
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>


        <!-- ================= Main Content ================= -->

        <div class="col-md-10 p-0 main-content">

            <!-- Topbar -->

            <div class="topbar">

                <h4 class="mb-0">
                    Partial Summary
                </h4>

            </div>


            <div class="p-4">

                <!-- ================= Date Filter ================= -->

                <div class="card mb-4 no-print">

                    <div class="card-body">

                        <form method="GET">

                            <div class="row align-items-end">

                                <div class="col-md-4">

                                    <label class="form-label">
                                        From Date
                                    </label>

                                    <input
                                        type="date"
                                        name="from_date"
                                        class="form-control"
                                        value="<?= htmlspecialchars($from_date) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        To Date
                                    </label>

                                    <input
                                        type="date"
                                        name="to_date"
                                        class="form-control"
                                        value="<?= htmlspecialchars($to_date) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-4">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        Show Summary
                                    </button>

                                    <button
                                        type="button"
                                        onclick="window.print()"
                                        class="btn btn-dark"
                                    >
                                        Print Summary
                                    </button>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>


                <!-- ================= Error ================= -->

                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <?php if (empty($error)): ?>

                    <!-- ================= Report Header ================= -->

                    <div class="mb-4">

                        <h5>
                            Summary Report
                        </h5>

                        <p class="text-muted mb-0">

                            From
                            <strong>
                                <?= date('d M Y', strtotime($from_date)) ?>
                            </strong>

                            to

                            <strong>
                                <?= date('d M Y', strtotime($to_date)) ?>
                            </strong>

                        </p>

                    </div>


                    <!-- ================= Sales Cards ================= -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-3">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Total Sales
                                    </h6>

                                    <div class="summary-value text-primary">

                                        ৳<?= number_format($total_sales, 2) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Total Paid
                                    </h6>

                                    <div class="summary-value text-success">

                                        ৳<?= number_format($total_paid, 2) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Total Due
                                    </h6>

                                    <div class="summary-value text-danger">

                                        ৳<?= number_format($total_due, 2) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Transactions
                                    </h6>

                                    <div class="summary-value">

                                        <?= number_format($total_transactions) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ================= Stock Cards ================= -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-4">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Stock In
                                    </h6>

                                    <div class="summary-value text-success">

                                        <?= number_format($stock_in) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Stock Out
                                    </h6>

                                    <div class="summary-value text-danger">

                                        <?= number_format($stock_out) ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="card summary-card">

                                <div class="card-body">

                                    <h6 class="text-muted">
                                        Returned
                                    </h6>

                                    <div class="summary-value text-warning">

                                        <?= number_format($stock_returned) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ================= Sales Table ================= -->

                    <div class="card">

                        <div class="card-header bg-dark text-white">

                            Sales Report

                        </div>

                        <div class="card-body p-0">

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover mb-0">

                                    <thead class="table-light">

                                        <tr>

                                            <th>#</th>

                                            <th>Client</th>

                                            <th>Total</th>

                                            <th>Paid</th>

                                            <th>Due</th>

                                            <th>Type</th>

                                            <th>Date</th>

                                            <th class="no-print">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php if (count($sales) > 0): ?>

                                        <?php foreach ($sales as $sale): ?>

                                            <tr>

                                                <td>
                                                    <?= $sale['id'] ?>
                                                </td>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $sale['client_name']
                                                        ?: 'Walk-in Customer'
                                                    ) ?>

                                                </td>

                                                <td>
                                                    ৳<?= number_format(
                                                        $sale['total_amount'],
                                                        2
                                                    ) ?>
                                                </td>

                                                <td class="text-success">

                                                    ৳<?= number_format(
                                                        $sale['paid_amount'],
                                                        2
                                                    ) ?>

                                                </td>

                                                <td class="text-danger">

                                                    ৳<?= number_format(
                                                        $sale['due_amount'],
                                                        2
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        ucfirst($sale['sale_type'])
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <?= date(
                                                        'd M Y',
                                                        strtotime($sale['sale_date'])
                                                    ) ?>

                                                </td>

                                                <td class="no-print">

                                                    <a
                                                        href="../sales/view.php?id=<?= $sale['id'] ?>"
                                                        class="btn btn-sm btn-primary"
                                                    >
                                                        View
                                                    </a>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <tr>

                                            <td
                                                colspan="8"
                                                class="text-center py-4"
                                            >
                                                No sales found for this date range.
                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

</body>

</html>