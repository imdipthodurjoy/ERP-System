<?php

require_once "../config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('staffAllowed')) {
    staffAllowed();
}

$error = "";
$success = "";

// =========================
// CURRENT USER
// =========================
$createdBy = $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? $_SESSION['user']['id']
    ?? null;

if ($createdBy === null) {
    $error = "Logged-in user ID not found. Please login again.";
}

// =========================
// FETCH CLIENTS
// =========================
$clients = [];

$clientQuery = $conn->query("
    SELECT id, name, phone
    FROM clients
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($clientQuery) {
    while ($row = $clientQuery->fetch_assoc()) {
        $clients[] = $row;
    }
}

// =========================
// FETCH PRODUCTS
// =========================
$products = [];

$productQuery = $conn->query("
    SELECT id, product_name, product_code, selling_price, current_stock, unit
    FROM products
    WHERE status = 'active'
    ORDER BY product_name ASC
");

if ($productQuery) {
    while ($row = $productQuery->fetch_assoc()) {
        $products[] = $row;
    }
}

// =========================
// SAVE SALE
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $createdBy !== null) {

    $clientId = !empty($_POST["client_id"])
        ? (int) $_POST["client_id"]
        : null;

    $saleDate = !empty($_POST["sale_date"])
        ? $_POST["sale_date"]
        : date("Y-m-d");

    $paidAmount = isset($_POST["paid_amount"])
        ? (float) $_POST["paid_amount"]
        : 0;

    $productIds = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $unitPrices = $_POST["unit_price"] ?? [];

    try {

        // =========================
        // BASIC VALIDATION
        // =========================
        if (empty($productIds)) {
            throw new Exception("Please add at least one product.");
        }

        if (count($productIds) !== count($quantities) ||
            count($productIds) !== count($unitPrices)) {
            throw new Exception("Invalid product data.");
        }

        if ($paidAmount < 0) {
            throw new Exception("Paid amount cannot be negative.");
        }

        // =========================
        // PREPARE ITEMS
        // =========================
        $items = [];
        $totalAmount = 0;
        $usedProducts = [];

        for ($i = 0; $i < count($productIds); $i++) {

            $productId = (int) $productIds[$i];
            $quantity = (int) $quantities[$i];
            $unitPrice = (float) $unitPrices[$i];

            if ($productId <= 0) {
                throw new Exception("Please select a valid product.");
            }

            if ($quantity <= 0) {
                throw new Exception("Quantity must be greater than 0.");
            }

            if ($unitPrice < 0) {
                throw new Exception("Unit price cannot be negative.");
            }

            // Prevent duplicate product rows
            if (in_array($productId, $usedProducts)) {
                throw new Exception(
                    "Same product cannot be added twice. Please increase its quantity instead."
                );
            }

            $usedProducts[] = $productId;

            $itemTotal = $quantity * $unitPrice;

            $items[] = [
                "product_id" => $productId,
                "quantity" => $quantity,
                "unit_price" => $unitPrice,
                "total_price" => $itemTotal
            ];

            $totalAmount += $itemTotal;
        }

        if ($totalAmount <= 0) {
            throw new Exception("Sale total must be greater than 0.");
        }

        if ($paidAmount > $totalAmount) {
            throw new Exception("Paid amount cannot be greater than total amount.");
        }

        $dueAmount = $totalAmount - $paidAmount;

        // =========================
        // START TRANSACTION
        // =========================
        $conn->begin_transaction();

        // =========================
        // INSERT SALE
        // =========================
        $saleType = "retail";

        if ($clientId === null) {

            $stmt = $conn->prepare("
                INSERT INTO sales
                (
                    client_id,
                    total_amount,
                    paid_amount,
                    due_amount,
                    sale_type,
                    sale_date,
                    created_by
                )
                VALUES
                (
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param(
                "dddssi",
                $totalAmount,
                $paidAmount,
                $dueAmount,
                $saleType,
                $saleDate,
                $createdBy
            );

        } else {

            $stmt = $conn->prepare("
                INSERT INTO sales
                (
                    client_id,
                    total_amount,
                    paid_amount,
                    due_amount,
                    sale_type,
                    sale_date,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param(
                "idddssi",
                $clientId,
                $totalAmount,
                $paidAmount,
                $dueAmount,
                $saleType,
                $saleDate,
                $createdBy
            );
        }

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $saleId = $conn->insert_id;

        $stmt->close();

        // =========================
        // PREPARE SALE ITEM
        // =========================
        $itemStmt = $conn->prepare("
            INSERT INTO sale_items
            (
                sale_id,
                product_id,
                quantity,
                unit_price,
                total_price
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$itemStmt) {
            throw new Exception($conn->error);
        }

        // =========================
        // STOCK TRANSACTION
        // =========================
        $stockStmt = $conn->prepare("
            INSERT INTO stock_transactions
            (
                product_id,
                type,
                quantity,
                note,
                transaction_date,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stockStmt) {
            throw new Exception($conn->error);
        }

        // =========================
        // UPDATE EACH PRODUCT
        // =========================
        foreach ($items as $item) {

            $productId = $item["product_id"];
            $quantity = $item["quantity"];
            $unitPrice = $item["unit_price"];
            $totalPrice = $item["total_price"];

            // Lock product row
            $productStmt = $conn->prepare("
                SELECT product_name, current_stock
                FROM products
                WHERE id = ?
                AND status = 'active'
                FOR UPDATE
            ");

            if (!$productStmt) {
                throw new Exception($conn->error);
            }

            $productStmt->bind_param("i", $productId);
            $productStmt->execute();

            $productResult = $productStmt->get_result();

            if ($productResult->num_rows === 0) {
                throw new Exception("Product not found.");
            }

            $product = $productResult->fetch_assoc();

            $productStmt->close();

            // Check stock
            if ((int)$product["current_stock"] < $quantity) {

                throw new Exception(
                    "Not enough stock for: " .
                    $product["product_name"] .
                    ". Available: " .
                    $product["current_stock"]
                );
            }

            // Insert sale item
            $itemStmt->bind_param(
                "iiidd",
                $saleId,
                $productId,
                $quantity,
                $unitPrice,
                $totalPrice
            );

            if (!$itemStmt->execute()) {
                throw new Exception($itemStmt->error);
            }

            // Reduce stock
            $updateStmt = $conn->prepare("
                UPDATE products
                SET current_stock = current_stock - ?
                WHERE id = ?
                AND current_stock >= ?
            ");

            if (!$updateStmt) {
                throw new Exception($conn->error);
            }

            $updateStmt->bind_param(
                "iii",
                $quantity,
                $productId,
                $quantity
            );

            if (!$updateStmt->execute() || $updateStmt->affected_rows !== 1) {
                throw new Exception(
                    "Failed to update stock for " .
                    $product["product_name"]
                );
            }

            $updateStmt->close();

            // Stock transaction
            $stockType = "out";
            $note = "Sale #" . $saleId;
            $transactionDate = $saleDate;

            $stockStmt->bind_param(
                "isissi",
                $productId,
                $stockType,
                $quantity,
                $note,
                $transactionDate,
                $createdBy
            );

            if (!$stockStmt->execute()) {
                throw new Exception($stockStmt->error);
            }
        }

        $itemStmt->close();
        $stockStmt->close();

        // =========================
        // COMMIT
        // =========================
        $conn->commit();

        header("Location: index.php?success=1&sale_id=" . $saleId);
        exit;

    } catch (Exception $e) {

        $conn->rollback();

        $error = $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>New Sale - ERP System</title>

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

.page-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.product-row {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 10px;
}

.stock-info {
    font-size: 13px;
}

.total-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}

.grand-total {
    font-size: 24px;
    font-weight: 700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="mb-1">
                <i class="bi bi-cart-plus"></i>
                New Sale
            </h3>

            <p class="text-muted mb-0">
                Create a new retail sale
            </p>

        </div>

        <a href="index.php" class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>
            Back to Sales

        </a>

    </div>


    <!-- ERROR -->
    <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- FORM -->
    <form method="POST" id="saleForm">

        <div class="page-card">

            <!-- CUSTOMER -->
            <div class="row mb-4">

                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Client
                    </label>

                    <select
                        name="client_id"
                        class="form-select">

                        <option value="">
                            Walk-in Customer
                        </option>

                        <?php foreach ($clients as $client): ?>

                            <option value="<?= (int)$client["id"] ?>">

                                <?= htmlspecialchars($client["name"]) ?>

                                <?php if (!empty($client["phone"])): ?>

                                    - <?= htmlspecialchars($client["phone"]) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-3">

                    <label class="form-label fw-bold">
                        Sale Date
                    </label>

                    <input
                        type="date"
                        name="sale_date"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        required>

                </div>


                <div class="col-md-3">

                    <label class="form-label fw-bold">
                        Sale Type
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="Retail"
                        readonly>

                </div>

            </div>


            <!-- PRODUCTS -->
            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">

                    <i class="bi bi-box-seam"></i>
                    Products

                </h5>

                <button
                    type="button"
                    class="btn btn-success btn-sm"
                    onclick="addProductRow()">

                    <i class="bi bi-plus-lg"></i>
                    Add Product

                </button>

            </div>


            <div id="productsContainer">

                <!-- FIRST ROW -->

                <div class="product-row">

                    <div class="row g-2 align-items-end">

                        <div class="col-md-5">

                            <label class="form-label">
                                Product
                            </label>

                            <select
                                name="product_id[]"
                                class="form-select product-select"
                                onchange="productChanged(this)"
                                required>

                                <option value="">
                                    Select Product
                                </option>

                                <?php foreach ($products as $product): ?>

                                    <option
                                        value="<?= (int)$product["id"] ?>"
                                        data-price="<?= htmlspecialchars($product["selling_price"]) ?>"
                                        data-stock="<?= (int)$product["current_stock"] ?>">

                                        <?= htmlspecialchars($product["product_name"]) ?>

                                        <?php if (!empty($product["product_code"])): ?>

                                            (<?= htmlspecialchars($product["product_code"]) ?>)

                                        <?php endif; ?>

                                        - Stock: <?= (int)$product["current_stock"] ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="stock-info text-muted mt-1">
                                Available Stock: <span class="available-stock">-</span>
                            </div>

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                Quantity
                            </label>

                            <input
                                type="number"
                                name="quantity[]"
                                class="form-control quantity"
                                min="1"
                                value="1"
                                onchange="calculateTotal()"
                                oninput="calculateTotal()"
                                required>

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                Unit Price
                            </label>

                            <input
                                type="number"
                                name="unit_price[]"
                                class="form-control unit-price"
                                min="0"
                                step="0.01"
                                value="0"
                                onchange="calculateTotal()"
                                oninput="calculateTotal()"
                                required>

                        </div>


                        <div class="col-md-2">

                            <label class="form-label">
                                Total
                            </label>

                            <input
                                type="text"
                                class="form-control item-total"
                                value="0.00"
                                readonly>

                        </div>


                        <div class="col-md-1">

                            <button
                                type="button"
                                class="btn btn-outline-danger w-100"
                                onclick="removeProductRow(this)">

                                <i class="bi bi-trash"></i>

                            </button>

                        </div>

                    </div>

                </div>

            </div>


            <hr class="my-4">


            <!-- TOTALS -->

            <div class="row justify-content-end">

                <div class="col-md-5">

                    <div class="total-box">

                        <div class="d-flex justify-content-between mb-2">

                            <span>
                                Total Amount
                            </span>

                            <strong id="totalAmount">
                                0.00
                            </strong>

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-bold">
                                Paid Amount
                            </label>

                            <input
                                type="number"
                                name="paid_amount"
                                id="paidAmount"
                                class="form-control"
                                value="0"
                                min="0"
                                step="0.01"
                                oninput="calculateDue()">

                        </div>


                        <div class="d-flex justify-content-between">

                            <span class="fw-bold">
                                Due Amount
                            </span>

                            <strong
                                id="dueAmount"
                                class="text-danger">

                                0.00

                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- SAVE -->

            <div class="d-flex justify-content-end gap-2 mt-4">

                <a
                    href="index.php"
                    class="btn btn-secondary">

                    Cancel

                </a>

                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="bi bi-check-circle"></i>
                    Save Sale

                </button>

            </div>

        </div>

    </form>

</div>


<script>

function productChanged(select) {

    const row = select.closest(".product-row");

    const option = select.options[select.selectedIndex];

    const price = option.dataset.price || 0;

    const stock = option.dataset.stock || 0;

    row.querySelector(".unit-price").value = price;

    row.querySelector(".available-stock").textContent = stock;

    calculateTotal();
}


function calculateTotal() {

    let total = 0;

    document.querySelectorAll(".product-row").forEach(function(row) {

        const quantity =
            parseFloat(
                row.querySelector(".quantity").value
            ) || 0;

        const unitPrice =
            parseFloat(
                row.querySelector(".unit-price").value
            ) || 0;

        const itemTotal = quantity * unitPrice;

        row.querySelector(".item-total").value =
            itemTotal.toFixed(2);

        total += itemTotal;

    });

    document.getElementById("totalAmount").textContent =
        total.toFixed(2);

    calculateDue();
}


function calculateDue() {

    const total =
        parseFloat(
            document.getElementById("totalAmount").textContent
        ) || 0;

    const paid =
        parseFloat(
            document.getElementById("paidAmount").value
        ) || 0;

    let due = total - paid;

    if (due < 0) {
        due = 0;
    }

    document.getElementById("dueAmount").textContent =
        due.toFixed(2);
}


function addProductRow() {

    const container =
        document.getElementById("productsContainer");

    const firstRow =
        document.querySelector(".product-row");

    const newRow =
        firstRow.cloneNode(true);

    // Reset values

    newRow.querySelector(".product-select").value = "";

    newRow.querySelector(".quantity").value = 1;

    newRow.querySelector(".unit-price").value = 0;

    newRow.querySelector(".item-total").value = "0.00";

    newRow.querySelector(".available-stock").textContent = "-";

    container.appendChild(newRow);

    calculateTotal();
}


function removeProductRow(button) {

    const rows =
        document.querySelectorAll(".product-row");

    if (rows.length <= 1) {

        alert("At least one product is required.");

        return;
    }

    button.closest(".product-row").remove();

    calculateTotal();
}


document.getElementById("saleForm").addEventListener(
    "submit",
    function(event) {

        let valid = true;

        document.querySelectorAll(".product-row").forEach(
            function(row) {

                const select =
                    row.querySelector(".product-select");

                const quantity =
                    parseInt(
                        row.querySelector(".quantity").value
                    ) || 0;

                const stock =
                    parseInt(
                        select.options[
                            select.selectedIndex
                        ]?.dataset.stock || 0
                    );

                if (!select.value || quantity <= 0) {
                    valid = false;
                }

                if (quantity > stock) {
                    valid = false;

                    alert(
                        "Quantity cannot be greater than available stock."
                    );
                }

            }
        );

        if (!valid) {
            event.preventDefault();
        }

    }
);


calculateTotal();

</script>

</body>

</html>