<?php

require_once '../include/config.php';

// run check using if else
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    try {

        require_once '../include/dbh.inc.php';
        require_once 'login.model.auth.php';
        require_once 'login.contr.auth.php';

        $errors = [];

        if (is_input_empty($username, $password)) {
            $errors['empty_input'] = "Please Fill in all fields";
        }

        $result = get_user($pdo, $username);

        if (is_username_wrong($result)) {
            $errors['invalid_creds'] = "Invalid  Credentials, Please Try Again";
        }

        if (!is_username_wrong($result) && is_password_wrong($password, $result['password_hash'], $pdo, $username)) {
            $errors['invalid_creds'] = "Invalid Credentials, Please Try Again";
        }

        if (!empty($errors)) {
            $_SESSION['login_errors'] = $errors;

            header("Location: ../../public/index.php");
            die();
        }

        // IF NO ERRORS OCCURS

        $_SESSION['user_id'] = $result['id'];
        // Update the status to online (1) for the logged-in user
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $_SESSION['username'] = htmlspecialchars($result['full_name']);
        $_SESSION['role'] = htmlspecialchars($result['role']);

        check_for_account_type($pdo, $result['role']);

        $pdo = null;
        $pst = null;

        die();
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    header("../../public/index.php");
    die();
}