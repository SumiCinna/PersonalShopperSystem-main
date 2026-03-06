<?php
session_start();
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $username_input = trim($_POST['username']);
    $password_input = $_POST['password'];
    
    $expected_role = $_POST['login_type'] ?? 'customer'; 

    function redirectWithError($error_message, $role) {
        $_SESSION['error'] = $error_message;
        if ($role === 'admin') {
            header("Location: ../admin-login.php");
        } elseif ($role === 'cashier') {
            header("Location: ../cashier-login.php");
        } else {
            header("Location: ../customer-login.php"); 
        }
        exit();
    }

    if (empty($username_input) || empty($password_input)) {
        redirectWithError('Please fill in both username and password.', $expected_role);
    }

    $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username_input, $username_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password_input, $user['password']) || $password_input === $user['password']) {
            
            if ($user['role'] !== $expected_role) {
                redirectWithError('Access denied. You do not have permission for this portal.', $expected_role);
            }

            if ($user['status'] !== 'active') {
                redirectWithError('This account is currently ' . $user['status'] . '. Please contact support.', $expected_role);
            }

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();

            if ($user['role'] === 'admin') {
                header("Location: ../modules/admin/dashboard.php");
            } elseif ($user['role'] === 'cashier') {
                header("Location: ../modules/cashier/dashboard.php");
            } else {
                header("Location: ../modules/customer/home.php");
            }
            exit();

        } else {
            redirectWithError('Invalid username or password.', $expected_role);
        }
    } else {
        redirectWithError('Invalid username or password.', $expected_role);
    }
    
    $stmt->close();
} else {
    header("Location: ../index.php");
    exit();
}
?>