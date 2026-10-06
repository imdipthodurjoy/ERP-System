<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid Sale ID.");
}

$saleId = (int) $_GET["id"];

// =========================
// FETCH SALE
// =========================
$stmt = $conn->prepare("
    SELECT
        s.id,
        s.client_id,
        s.total_amount,
        s.paid_amount,
        s.due_amount,
        s.sale_type,
        s.sale_date,
        s.created_by,
        s.created_at,
        c.name AS client_name,
        c.phone AS client_phone,
        c.address AS client_address
    FROM sales s
    LEFT JOIN clients c ON s.client_id = c.id
    WHERE s.id = ?
");

$stmt->bind_param("i", $saleId);
$stmt->execute();

$saleResult = $stmt->get_result();

if ($saleResult->num_rows === 0) {
    die("Sale not found.");
}

$sale = $saleResult->fetch_assoc();

$stmt->close();


// =========================
// FETCH SALE ITEMS
// =========================
$itemStmt = $conn->prepare("
    SELECT
        si.id,
        si.product_id,
        si.quantity,
        si.unit_price,
        si.total_price,
        p.product_name,
        p.product_code,
        p.unit
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");

$itemStmt->bind_param("i", $saleId);
$itemStmt->execute();

$itemsResult = $itemStmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Invoice #<?= $saleId ?> - ERP System</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

body {
    background: #f5f6fa;
}

.invoice-container {
    max-width: 1000px;
    margin: 30px auto;
}

.invoice-card {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.invoice-title {
    font-size: 30px;
    font-weight: 700;
}

.company-name {
    font-size: 24px;
    font-weight: 700;
}

.info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.total-section {
    max-width: 400px;
    margin-left: auto;
}

.grand-total {
    font-size: 22px;
    font-weight: 700;
}

.due {
    color: #dc3545;
    font-weight: 700;
}

.paid {
    color: #198754;
    font-weight: 700;
}

@media print {

    body {
        background: white;
    }

    .no-print {
        display: none !important;
    }

    .invoice-container {
        margin: 0;
        max-width: 100%;
    }

    .invoice-card {
        box-shadow: none;
        border-radius: 0;
        padding: 20px;
    }

}

</style>

</head>

<body>


<div class="invoice-container">

    <!-- ACTION BUTTONS -->

    <div class="d-flex justify-content-between mb-3 no-print">

        <a
        href="index.php"
        class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>
            Back to Sales

        </a>


        <button
        onclick="window.print()"
        class="btn btn-primary">

            <i class="bi bi-printer"></i>
            Print Invoice

        </button>

    </div>


    <!-- INVOICE -->

    <div class="invoice-card">


        <!-- HEADER -->

        <div class="row align-items-center mb-4">

            <div class="col-md-7">

                <div class="company-name">
                    ERP SYSTEM
                </div>

                <div class="text-muted">
                    Sales & Inventory Management
                </div>

            </div>


            <div class="col-md-5 text-md-end">

                <div class="invoice-title">
                    INVOICE
                </div>

                <div>
                    Invoice #: <strong>#<?= $saleId ?></strong>
                </div>

                <div>
                    Date:
                    <?= htmlspecialchars($sale["sale_date"]) ?>
                </div>

            </div>

        </div>


        <hr>


        <!-- CUSTOMER INFO -->

        <div class="row my-4">

            <div class="col-md-6">

                <div class="info-box">

                    <strong>Bill To</strong>

                    <div class="mt-2">

                        <?php if (!empty($sale["client_name"])): ?>

                            <strong>
                                <?= htmlspecialchars($sale["client_name"]) ?>
                            </strong>

                            <?php if (!empty($sale["client_phone"])): ?>

                                <br>
                                Phone:
                                <?= htmlspecialchars($sale["client_phone"]) ?>

                            <?php endif; ?>


                            <?php if (!empty($sale["client_address"])): ?>

                                <br>
                                Address:
                                <?= htmlspecialchars($sale["client_address"]) ?>

                            <?php endif; ?>

                        <?php else: ?>

                            <strong>Walk-in Customer</strong>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <div class="col-md-6 text-md-end">

                <div class="info-box">

                    <strong>Sale Information</strong>

                    <div class="mt-2">

                        Sale Type:

                        <?php if ($sale["sale_type"] === "online"): ?>

                            <span class="badge bg-primary">
                                Online
                            </span>

                        <?php else: ?>

                            <span class="badge bg-success">
                                Retail
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="mt-1">

                        Created By:
                        <?= $sale["created_by"] !== null
                            ? (int)$sale["created_by"]
                            : "-"
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- PRODUCTS -->

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-dark">

                    <tr>

                        <th width="5%">#</th>

                        <th>Product</th>

                        <th>Code</th>

                        <th>Qty</th>

                        <th>Unit Price</th>

                        <th>Total</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $counter = 1;

                if ($itemsResult->num_rows > 0):

                    while ($item = $itemsResult->fetch_assoc()):

                ?>

                    <tr>

                        <td>
                            <?= $counter++ ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $item["product_name"] ?? "Unknown Product"
                                ) ?>
                            </strong>

                        </td>

                        <td>

                            <?= !empty($item["product_code"])
                                ? htmlspecialchars($item["product_code"])
                                : "-"
                            ?>

                        </td>

                        <td>

                            <?= (int)$item["quantity"] ?>

                            <?php if (!empty($item["unit"])): ?>

                                <?= htmlspecialchars($item["unit"]) ?>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= number_format(
                                (float)$item["unit_price"],
                                2
                            ) ?>

                        </td>

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$item["total_price"],
                                    2
                                ) ?>

                            </strong>

                        </td>

                    </tr>

                <?php

                    endwhile;

                else:

                ?>

                    <tr>

                        <td
                        colspan="6"
                        class="text-center text-muted">

                            No items found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- TOTAL -->

        <div class="total-section mt-4">

            <div class="d-flex justify-content-between mb-2">

                <span>
                    Total Amount
                </span>

                <strong>

                    <?= number_format(
                        (float)$sale["total_amount"],
                        2
                    ) ?>

                </strong>

            </div>


            <div class="d-flex justify-content-between mb-2">

                <span>
                    Paid Amount
                </span>

                <span class="paid">

                    <?= number_format(
                        (float)$sale["paid_amount"],
                        2
                    ) ?>

                </span>

            </div>


            <hr>


            <div class="d-flex justify-content-between">

                <span class="grand-total">
                    Due Amount
                </span>

                <span class="grand-total due">

                    <?= number_format(
                        (float)$sale["due_amount"],
                        2
                    ) ?>

                </span>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="text-center mt-5 pt-3 border-top">

            <p class="text-muted mb-0">
                Thank you for your business!
            </p>

        </div>

    </div>

</div>


</body>

</html>