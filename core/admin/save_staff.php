<?php
// core/admin/save_staff.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Capture the data
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename'] ?? '');
    $surname = trim($_POST['surname']);
    $suffix = trim($_POST['suffix'] ?? '');
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    
    // The auto-generated username from the frontend!
    $username = trim($_POST['username']); 

    // 2. Prevent Duplicate Usernames or Emails
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Error: That Email is already registered!";
        header("Location: ../../modules/admin/add_staff.php");
        exit();
    }
    $check->close();

    // Secure the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // 3. Start a Database Transaction (To safely insert into BOTH tables)
    $conn->begin_transaction();

    try {
        // STEP A: Insert into `users` table
        $insert_user = $conn->prepare("INSERT INTO users (username, email, password, role, terms_agreed, status) VALUES (?, ?, ?, ?, 1, 'active')");
        $insert_user->bind_param("ssss", $username, $email, $hashed_password, $role);
        $insert_user->execute();
        
        // Grab the ID of the user we just created
        $new_user_id = $conn->insert_id;
        $insert_user->close();

        // STEP B: Insert into `user_profiles` table
        $insert_profile = $conn->prepare("INSERT INTO user_profiles (user_id, firstname, middlename, surname, suffix, mobile) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_profile->bind_param("isssss", $new_user_id, $firstname, $middlename, $surname, $suffix, $mobile);
        $insert_profile->execute();
        $insert_profile->close();

        // Commit both inserts!
        $conn->commit();
        
        $_SESSION['success'] = "Success! New $role account created with ID: <strong>$username</strong>";
        header("Location: ../../modules/admin/staff.php");
        exit();

    } catch (Exception $e) {
        // If anything fails, roll back the whole thing
        $conn->rollback();
        $_SESSION['error'] = "System Error: Could not save staff profile to the database.";
        header("Location: ../../modules/admin/add_staff.php");
        exit();
    }
}
?>