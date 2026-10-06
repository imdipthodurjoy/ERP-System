<?php

require_once "../config/auth.php";

adminOnly();

require_once "../config/database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$product_id = (int) $_GET["id"];

/* Check product exists */
$stmt = $conn->prepare("SELECT id, product_name FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    header("Location: index.php");
    exit;
}


/* Check stock transaction history */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM stock_transactions WHERE product_id = ?"
);

$stmt->bind_param("i", $product_id);
$stmt->execute();

$stock_history = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* Check sales history */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM sale_items WHERE product_id = ?"
);

$stmt->bind_param("i", $product_id);
$stmt->execute();

$sales_history = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* Check online order history */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM online_order_items WHERE product_id = ?"
);

$stmt->bind_param("i", $product_id);
$stmt->execute();

$online_history = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
If the product has any history,
do not permanently delete it.
Instead, make it inactive.
*/
if ($stock_history > 0 || $sales_history > 0 || $online_history > 0) {

    $stmt = $conn->prepare(
        "UPDATE products SET status = 'inactive' WHERE id = ?"
    );

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $stmt->close();

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Product Cannot Be Deleted</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >
    </head>

    <body>

        <div class="container mt-5">

            <div class="alert alert-warning">

                <h4>Product Cannot Be Permanently Deleted</h4>

                <p>
                    <strong><?= htmlspecialchars($product["product_name"]) ?></strong>
                    already has transaction or sales history.
                </p>

                <p>
                    For safety, the product has been marked as
                    <strong>Inactive</strong>.
                </p>

                <a href="index.php" class="btn btn-primary">
                    Back to Stock
                </a>

            </div>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/* Permanently delete product */
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {

    $stmt->close();

    header("Location: index.php");
    exit;

} else {

    $stmt->close();

    die("Unable to delete product.");
}

?>