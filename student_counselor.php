<?php
session_start();
include "db.php";

// Only counselors and admins can access
if (!isset($_SESSION['user']) || 
    ($_SESSION['user']['role'] != 'counselor' && $_SESSION['user']['role'] != 'admin')) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$success = "";
$error = "";

/* -----------------------
        ADD ACCOUNT
----------------------- */
if (isset($_POST['add_account'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO logs(name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if ($stmt->execute()) $success = "Account added successfully!";
    else $error = "Failed to add account. Email may already exist.";
}

/* -----------------------
        EDIT ACCOUNT
----------------------- */
if (isset($_POST['edit_account'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE logs SET name=?, email=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $role, $password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE logs SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }

    if ($stmt->execute()) $success = "Account updated successfully!";
    else $error = "Failed to update account.";
}

/* -----------------------
        DELETE ACCOUNT
----------------------- */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id == $user['id']) {
        $error = "You cannot delete your own account!";
    } else {
        $conn->query("DELETE FROM logs WHERE id = $id");
        $success = "Account deleted successfully!";
    }
}

/* -----------------------
        FETCH ACCOUNTS
----------------------- */
$accounts = $conn->query("SELECT * FROM logs ORDER BY role ASC, name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Accounts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* Global Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #2980b9, #6dd5fa, #ffffff);
    color: #333;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    transition: 0.3s;
}

body.dark {
    background: #111;
    color: #fff;
}

.sidebar {
    width: 280px;
    height: 100vh;
    position: fixed;
    background: rgba(0,0,0,0.35);
    color: #fff;
    padding-top: 30px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
    z-index: 10;
}

.sidebar h2 {
    text-align: center;
    font-size: 24px;
    margin-bottom: 40px;
}

.sidebar a {
    display: block;
    padding: 15px;
    color: #fff;
    text-decoration: none;
    font-size: 16px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.sidebar a:hover {
    background: rgba(255, 255, 255, 0.2);
    border-left: 4px solid #fff;
}

.sidebar a.active {
    background: rgba(255, 255, 255, 0.15);
    border-left: 4px solid #2980b9;
}

.sidebar #darkModeToggle {
    position: absolute;
    bottom: 20px;
    width: 90%;
    padding: 10px;
    background-color: #444;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

/* Content Styles */
.content {
    margin-left: 280px;
    padding: 40px;
    width: 100%;
    max-width: calc(100% - 280px);
    flex-grow: 1;
}

.page-title {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 40px;
    color: #333;
}

.card {
    background: rgba(255, 255, 255, 0.9);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: 0.3s;
}

.card.dark {
    background: rgba(40, 40, 40, 0.75);
    color: #fff;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
}

th {
    background-color: #2980b9;
    color: #fff;
    font-weight: bold;
}

tr:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

/* UNIFORM ACTION BUTTONS */
.action-btn {
    padding: 8px 12px;
    border-radius: 6px;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    transition: 0.2s;
}
.action-btn:hover {
    opacity: 0.85;
}
.btn-view { background: #3498db; }
.btn-edit { background: #f39c12; }
.btn-delete { background: #c0392b; }

</style>
</head>
<body>

<div class="sidebar">
    <h2>Counselor Panel</h2>

    <a href="counselor_dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="student_counselor.php" class="active"><i class="fa fa-users"></i> Manage Students</a>
    <a href="list_discipline_counseling.php"><i class="fa fa-book"></i> Counseling & Discipline Records</a>
    <a href="add_counseling.php"><i class="fa fa-plus-circle"></i> Add Counseling</a>
    <a href="add_discipline.php"><i class="fa fa-plus"></i> Add Discipline Case</a>
    <a href="analytics_dashboard.php"><i class="fa fa-chart-line"></i> Analytics Dashboard</a>
    <a href="logout.php" id="logoutLink"><i class="fa fa-sign-out"></i> Logout</a>

    <button id="darkModeToggle"><i class="fa fa-moon"></i> Dark Mode</button>
</div>

<div class="content">
    <div class="page-title">Manage Accounts</div>

    <?php if ($success): ?>
        <div class="card" style="background: #27ae60; color: #fff;"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card" style="background: #c0392b; color: #fff;"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>All Registered Accounts</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($a = $accounts->fetch_assoc()): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= $a['name'] ?></td>
                        <td><?= $a['email'] ?></td>
                        <td><?= ucfirst($a['role']) ?></td>
                        <td>

                            <!-- VIEW PROFILE -->
                            <a href="student_profile.php?id=<?= $a['id'] ?>" 
                               class="action-btn btn-view">
                                <i class="fa fa-id-card"></i>
                            </a>

                            

                            <!-- DELETE -->
                            <?php if ($a['id'] != $user['id']): ?>
                            <button class="action-btn btn-delete" onclick="deleteAccount(<?= $a['id'] ?>)">
                                <i class="fa fa-trash"></i>
                            </button>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Dark Mode Toggle
const darkToggle = document.getElementById("darkModeToggle");
if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark");
}
darkToggle.addEventListener("click", () => {
    document.body.classList.toggle("dark");
    localStorage.setItem("theme", document.body.classList.contains("dark") ? "dark" : "light");
});

// Logout Confirmation
document.getElementById("logoutLink").addEventListener("click", function(e){
    if (!confirm("Logout now?")) e.preventDefault();
});

// Delete Account
function deleteAccount(id) {
    if (confirm("Are you sure to delete this account?")) {
        window.location = "?delete=" + id;
    }
}
</script>

</body>
</html>
