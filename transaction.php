<?php

require_once "../config/auth.php";

staffAllowed();

require_once "../config/database.php";

$error = "";
$success = "";


/* Handle transaction */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = (int) $_POST["product_id"];
    $type = $_POST["type"];
    $quantity = (int) $_POST["quantity"];
    $note = trim($_POST["note"]);
    $transaction_date = $_POST["transaction_date"];

    /* Validate */
    if ($product_id <= 0 || $quantity <= 0) {

        $error = "Please select a product and enter a valid quantity.";

    } elseif (!in_array($type, ["in", "out", "returned"])) {

        $error = "Invalid transaction type.";

    } else {

        /* Get current stock */
        $stmt = $conn->prepare(
            "SELECT current_stock FROM products WHERE id = ? AND status = 'active'"
        );

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $stmt->close();


        if (!$product) {

            $error = "Product not found or inactive.";

        } else {

            $current_stock = (int) $product["current_stock"];

            /*
            Stock calculation

            Stock In      → + quantity
            Returned      → + quantity
            Stock Out     → - quantity
            */

            if ($type === "out") {

                if ($quantity > $current_stock) {

                    $error = "Not enough stock available.";

                } else {

                    $new_stock = $current_stock - $quantity;
                }

            } else {

                $new_stock = $current_stock + $quantity;
            }


            /* If no error, save transaction */
            if ($error === "") {

                $user_id = $_SESSION["user_id"];

                $conn->begin_transaction();

                try {

                    /* Insert transaction */
                    $stmt = $conn->prepare(
                        "INSERT INTO stock_transactions
                        (product_id, type, quantity, note, transaction_date, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "isissi",
                        $product_id,
                        $type,
                        $quantity,
                        $note,
                        $transaction_date,
                        $user_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Transaction insert failed.");
                    }

                    $stmt->close();


                    /* Update current stock */
                    $stmt = $conn->prepare(
                        "UPDATE products SET current_stock = ? WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "ii",
                        $new_stock,
                        $product_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Stock update failed.");
                    }

                    $stmt->close();


                    $conn->commit();

                    $success = "Stock transaction completed successfully.";

                } catch (Exception $e) {

                    $conn->rollback();

                    $error = "Something went wrong. Transaction was not completed.";
                }
            }
        }
    }
}


/* Get active products */
$products = $conn->query(
    "SELECT id, product_name, product_code, current_stock
     FROM products
     WHERE status = 'active'
     ORDER BY product_name ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Stock Transaction - ERP System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body>

<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Stock Transaction</h2>

        <a href="index.php" class="btn btn-secondary">
            Back to Stock
        </a>

    </div>


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


    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">

                <div class="row">

                    <!-- Product -->
                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Product
                        </label>

                        <select
                            name="product_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select Product
                            </option>

                            <?php while ($product = $products->fetch_assoc()): ?>

                                <option value="<?= $product["id"] ?>">

                                    <?= htmlspecialchars($product["product_name"]) ?>

                                    <?php if ($product["product_code"]): ?>
                                        - <?= htmlspecialchars($product["product_code"]) ?>
                                    <?php endif; ?>

                                    | Stock:
                                    <?= $product["current_stock"] ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <!-- Transaction Type -->
                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Transaction Type
                        </label>

                        <select
                            name="type"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select Type
                            </option>

                            <option value="in">
                                Stock In
                            </option>

                            <option value="out">
                                Stock Out
                            </option>

                            <option value="returned">
                                Returned
                            </option>

                        </select>

                    </div>


                    <!-- Quantity -->
                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Quantity
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            class="form-control"
                            min="1"
                            required
                        >

                    </div>


                    <!-- Date -->
                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Transaction Date
                        </label>

                        <input
                            type="date"
                            name="transaction_date"
                            class="form-control"
                            value="<?= date("Y-m-d") ?>"
                            required
                        >

                    </div>


                    <!-- Note -->
                    <div class="col-12 mb-3">

                        <label class="form-label">
                            Note
                        </label>

                        <textarea
                            name="note"
                            class="form-control"
                            rows="3"
                            placeholder="Optional note"
                        ></textarea>

                    </div>


                    <div class="col-12">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Transaction
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

</body>

</html>