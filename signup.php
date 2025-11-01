<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['name'];
    $email = $_POST['email'];
    $password = md5($_POST['password']); // encrypt password
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    $role = 'customer'; // default role

    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $check_email);

    if (mysqli_num_rows($result) > 0) {
        echo "<script>alert('Email already exists!'); window.location='signup.html';</script>";
    } else {
        $sql = "INSERT INTO users (full_name, email, password, phone, address, role)
                VALUES ('$full_name', '$email', '$password', '$phone', '$address', '$role')";
        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('Account created successfully! Please log in.'); window.location='index.html';</script>";
        } else {
            echo 'Error: ' . mysqli_error($conn);
        }
    }
}
?>
