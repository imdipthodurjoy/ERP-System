<?php

session_start();


// =========================
// LOGIN CHECK
// =========================

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;

}


// =========================
// ADMIN ONLY
// =========================

function adminOnly()
{
    if (
        !isset($_SESSION["role"]) ||
        $_SESSION["role"] !== "admin"
    ) {

        http_response_code(403);

        die("
            <div style='
                font-family: Arial;
                text-align: center;
                margin-top: 100px;
            '>

                <h1>403 - Access Denied</h1>

                <p>
                    You do not have permission to access this page.
                </p>

                <a href='../dashboard/'>
                    Back to Dashboard
                </a>

            </div>
        ");
    }
}


// =========================
// REQUIRE ADMIN
// =========================

function requireAdmin()
{
    if (
        !isset($_SESSION["role"]) ||
        $_SESSION["role"] !== "admin"
    ) {

        header(
            "Location: ../dashboard/index.php?error=" .
            urlencode("Admin access required.")
        );

        exit;
    }
}


// =========================
// STAFF OR ADMIN
// =========================

function staffAllowed()
{
    if (
        !isset($_SESSION["role"]) ||
        !in_array($_SESSION["role"], ["admin", "staff"])
    ) {

        http_response_code(403);

        die("Access Denied.");
    }
}

?>