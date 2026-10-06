<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$error = "";
$success = "";


// =========================
// GET PRODUCT ID
// =========================

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$product_id = (int) $_GET["id"];


// =========================
// UPDATE PRODUCT
// =========================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_name = trim($_POST["product_name"]);
    $product_code = trim($_POST["product_code"]);
    $unit = trim($_POST["unit"]);
    $purchase_price = (float) $_POST["purchase_price"];
    $selling_price = (float) $_POST["selling_price"];

    if ($product_name === "") {

        $error = "Product name is required.";

    } elseif ($purchase_price < 0 || $selling_price < 0) {

        $error = "Price cannot be negative.";

    } else {

        // Check duplicate product code
        if ($product_code !== "") {

            $check = $conn->prepare("
                SELECT id
                FROM products
                WHERE product_code = ?
                AND id != ?
                LIMIT 1
            ");

            $check->bind_param(
                "si",
                $product_code,
                $product_id
            );

            $check->execute();

            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Product code already exists.";
            }

            $check->close();
        }


        // Update product
        if ($error === "") {

            $stmt = $conn->prepare("
                UPDATE products
                SET
                    product_name = ?,
                    product_code = ?,
                    unit = ?,
                    purchase_price = ?,
                    selling_price = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssddi",
                $product_name,
                $product_code,
                $unit,
                $purchase_price,
                $selling_price,
                $product_id
            );

            if ($stmt->execute()) {

                $success = "Product updated successfully.";

            } else {

                $error = "Failed to update product.";

            }

            $stmt->close();
        }
    }
}


// =========================
// GET PRODUCT
// =========================

$stmt = $conn->prepare("
    SELECT
        id,
        product_name,
        product_code,
        unit,
        purchase_price,
        selling_price,
        current_stock,
        status
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit;
}

$product = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>ERP System - Edit Product</title>

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

        .sidebar h4 {
            color: white;
            padding: 20px;
            margin: 0;
            border-bottom: 1px solid #343a40;
        }

        .sidebar a {
            display: block;
            color: #adb5bd;
            text-decoration: none;
            padding: 12px 20px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #343a40;
            color: white;
        }

        .main-content {
            padding: 25px;
        }

        .topbar {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

    </style>

</head>


<body>


<div class="container-fluid">

    <div class="row">


        <!-- SIDEBAR -->

        <div class="col-md-2 p-0 sidebar">

            <h4>
                ERP System
            </h4>

            <a href="../dashboard/">
                Dashboard
            </a>

            <a href="index.php" class="active">
                Stock
            </a>

            <a href="../sales/">
                Sales
            </a>

            <a href="../summary/">
                Daily Summary
            </a>

            <a href="../summary/partial.php">
                Partial Summary
            </a>

            <a href="../clients/">
                Retail Clients
            </a>

            <a href="../online-sales/">
                Online Sales
            </a>

            <a href="../users/">
                Users
            </a>

        </div>


        <!-- MAIN CONTENT -->

        <div class="col-md-10 main-content">


            <!-- TOP BAR -->

            <div class="topbar d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Edit Product
                    </h2>

                    <p class="text-muted mb-0">
                        Update product information
                    </p>

                </div>


                <div>

                    <span class="text-muted me-3">
                        <?= htmlspecialchars($_SESSION["user_name"]) ?>
                    </span>

                    <a
                        href="../auth/logout.php"
                        class="btn btn-sm btn-danger"
                    >
                        Logout
                    </a>

                </div>

            </div>


            <!-- FORM -->

            <div class="form-card">

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <?php if ($success !== ""): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                <?php endif; ?>


                <form method="POST">


                    <div class="row">


                        <!-- PRODUCT NAME -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Product Name
                            </label>

                            <input
                                type="text"
                                name="product_name"
                                class="form-control"
                                value="<?= htmlspecialchars($product["product_name"]) ?>"
                                required
                            >

                        </div>


                        <!-- PRODUCT CODE -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Product Code
                            </label>

                            <input
                                type="text"
                                name="product_code"
                                class="form-control"
                                value="<?= htmlspecialchars($product["product_code"] ?? "") ?>"
                            >

                        </div>


                        <!-- UNIT -->

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Unit
                            </label>

                            <select
                                name="unit"
                                class="form-select"
                            >

                                <option
                                    value="pcs"
                                    <?= $product["unit"] === "pcs" ? "selected" : "" ?>
                                >
                                    Pieces (pcs)
                                </option>

                                <option
                                    value="kg"
                                    <?= $product["unit"] === "kg" ? "selected" : "" ?>
                                >
                                    Kilogram (kg)
                                </option>

                                <option
                                    value="liter"
                                    <?= $product["unit"] === "liter" ? "selected" : "" ?>
                                >
                                    Liter
                                </option>

                                <option
                                    value="box"
                                    <?= $product["unit"] === "box" ? "selected" : "" ?>
                                >
                                    Box
                                </option>

                            </select>

                        </div>


                        <!-- PURCHASE PRICE -->

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Purchase Price
                            </label>

                            <input
                                type="number"
                                name="purchase_price"
                                class="form-control"
                                step="0.01"
                                min="0"
                                value="<?= htmlspecialchars($product["purchase_price"]) ?>"
                                required
                            >

                        </div>


                        <!-- SELLING PRICE -->

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Selling Price
                            </label>

                            <input
                                type="number"
                                name="selling_price"
                                class="form-control"
                                step="0.01"
                                min="0"
                                value="<?= htmlspecialchars($product["selling_price"]) ?>"
                                required
                            >

                        </div>


                    </div>


                    <!-- CURRENT STOCK -->

                    <div class="alert alert-info">

                        <strong>
                            Current Stock:
                        </strong>

                        <?= htmlspecialchars($product["current_stock"]) ?>

                        <?= htmlspecialchars($product["unit"]) ?>

                        <br>

                        <small>
                            Current stock is managed through Stock In / Out / Returned.
                        </small>

                    </div>


                    <div class="mt-3">

                        <button
                            type="submit"
                            class="btn btn-dark"
                        >
                            Update Product
                        </button>


                        <a
                            href="index.php"
                            class="btn btn-secondary ms-2"
                        >
                            Back to Stock
                        </a>

                    </div>


                </form>

            </div>


        </div>

    </div>

</div>


</body>

</html>