<?php
session_start();
include "db.php";

// ROLE CHECK
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['counselor', 'admin'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Get filter/search input
$search     = trim($_GET['search'] ?? "");
$filterDate = trim($_GET['date'] ?? "");
$type       = trim($_GET['type'] ?? "all");

// Escape for safety
$search_esc = $conn->real_escape_string($search);
$date_esc   = $conn->real_escape_string($filterDate);

// ---------------------- DISCIPLINE RECORDS ----------------------
$discipline_sql = "
    SELECT dr.id, s.student_id AS stud_id, s.name AS student_name, u.name AS counselor_name,
           dr.incident_date, dr.incident_type, dr.description, dr.action_taken
    FROM discipline_records dr
    JOIN students s ON dr.student_id = s.id
    JOIN logs u ON dr.counselor_id = u.id
    WHERE 1
";

if (($type === "all" || $type === "discipline") && $search_esc !== "") {
    $discipline_sql .= " AND (s.name LIKE '%{$search_esc}%' OR s.student_id LIKE '%{$search_esc}%' OR dr.incident_type LIKE '%{$search_esc}%' OR dr.description LIKE '%{$search_esc}%')";
}
if (($type === "all" || $type === "discipline") && $date_esc !== "") {
    $discipline_sql .= " AND dr.incident_date = '{$date_esc}'";
}
$discipline_sql .= " ORDER BY dr.incident_date DESC";
$discipline_res = $conn->query($discipline_sql);

// ---------------------- COUNSELING RECORDS ----------------------
$counseling_sql = "
    SELECT cr.id, s.student_id AS stud_id, s.name AS student_name, u.name AS counselor_name,
           cr.session_date, cr.notes, cr.follow_up_required
    FROM counseling_records cr
    JOIN students s ON cr.student_id = s.id
    JOIN logs u ON cr.counselor_id = u.id
    WHERE 1
";

if (($type === "all" || $type === "counseling") && $search_esc !== "") {
    $counseling_sql .= " AND (s.name LIKE '%{$search_esc}%' OR s.student_id LIKE '%{$search_esc}%' OR cr.notes LIKE '%{$search_esc}%')";
}
if (($type === "all" || $type === "counseling") && $date_esc !== "") {
    $counseling_sql .= " AND cr.session_date = '{$date_esc}'";
}
$counseling_sql .= " ORDER BY cr.session_date DESC";
$counseling_res = $conn->query($counseling_sql);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Discipline & Counseling Records</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body {
    margin:0; padding:0; font-family:"Segoe UI", sans-serif; display:flex; min-height:100vh;
    background: linear-gradient(135deg,#2980b9,#6dd5fa,#ffffff); transition:.3s;
}
body.dark { background:#111; color:#eee; }

.sidebar {
    width:260px; height:100vh; background:rgba(0,0,0,0.35); backdrop-filter:blur(12px);
    padding-top:30px; position:fixed; box-shadow:0 0 25px rgba(0,0,0,0.3);
}
.sidebar h2 { color:white; text-align:center; margin-bottom:25px; font-size:22px; }
.sidebar a { display:block; padding:14px 25px; color:white; font-size:16px; text-decoration:none; border-left:4px solid transparent; transition:.2s; }
.sidebar a:hover { background:rgba(255,255,255,0.15); border-left:4px solid #fff; }
.sidebar i { margin-right:10px; }
#darkModeToggle { margin:20px; padding:10px; width:calc(100% - 40px); background:#444; color:white; border:none; border-radius:8px; cursor:pointer; }

.content { margin-left:260px; padding:30px; width:calc(100% - 260px); }
.page-title { font-size:30px; font-weight:bold; color:#fff; margin-bottom:20px; }

.filter-box {
    display:flex; flex-wrap:wrap; gap:10px; background:rgba(255,255,255,0.85);
    padding:15px; border-radius:10px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
.filter-box input, .filter-box select, .filter-box button {
    padding:8px; border-radius:6px; border:1px solid #aaa; font-size:14px;
}
.filter-box button { border:none; background:#3498db; color:white; cursor:pointer; }

table {
    width:100%; border-collapse:collapse; background:rgba(255,255,255,0.9); border-radius:8px; overflow:hidden; margin-bottom:40px;
}
th, td { padding:12px; border-bottom:1px solid #ccc; text-align:left; }
th { background:#2980b9; color:white; }
tr:hover { background: rgba(0,0,0,0.05); }

.btn-edit, .btn-delete { padding:6px 12px; border-radius:6px; text-decoration:none; color:white; font-size:14px; margin-right:5px; }
.btn-edit { background:#27ae60; }
.btn-delete { background:#c0392b; }

body.dark table { background:rgba(40,40,40,0.9); }
body.dark th { background:#333; color:#eee; }
body.dark td { color:#ddd; border-bottom:1px solid #555; }
body.dark .filter-box { background: rgba(40,40,40,0.9); }
body.dark input, body.dark select { background:#3c3c3c; color:#eee; border:1px solid #555; }
body.dark .btn-edit { background:#2ecc71; }
body.dark .btn-delete { background:#e74c3c; }

</style>
</head>
<body>

<div class="sidebar">
    <h2>Counselor Panel</h2>
    <a href="counselor_dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="student_counselor.php"><i class="fa fa-users"></i> Manage Students</a>
    <a href="list_discipline_counseling.php"><i class="fa fa-book"></i> Counseling & Discipline Records</a>
    <a href="add_counseling.php"><i class="fa fa-plus-circle"></i> Add Counseling</a>
    <a href="add_discipline.php"><i class="fa fa-plus"></i> Add Discipline Case</a>
    <a href="analytics_dashboard.php">
        <i class="fa fa-chart-line"></i> Analytics Dashboard
    <a href="logout.php" id="logoutLink"><i class="fa fa-sign-out"></i> Logout</a>
    <button id="darkModeToggle"><i class="fa fa-moon"></i> Dark Mode</button>
</div>

<div class="content">
    <div class="page-title">Records</div>

    <form method="get" class="filter-box">
        <input type="text" name="search" placeholder="Search student name / ID / offense / notes" value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        <select name="type">
            <option value="all" <?= $type==="all"?"selected":"" ?>>All</option>
            <option value="discipline" <?= $type==="discipline"?"selected":"" ?>>Discipline</option>
            <option value="counseling" <?= $type==="counseling"?"selected":"" ?>>Counseling</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php if ($type==="all" || $type==="discipline"): ?>
    <h2>Discipline Records</h2>
    <table>
        <tr>
            <th>ID</th><th>Student ID</th><th>Student Name</th><th>Counselor</th><th>Date</th><th>Type</th><th>Description</th><th>Action Taken</th><th>Action</th>
        </tr>
        <?php while($r=$discipline_res->fetch_assoc()): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['stud_id']) ?></td>
            <td><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['counselor_name']) ?></td>
            <td><?= htmlspecialchars($r['incident_date']) ?></td>
            <td><?= htmlspecialchars($r['incident_type']) ?></td>
            <td><?= htmlspecialchars($r['description']) ?></td>
            <td><?= htmlspecialchars($r['action_taken']) ?></td>
            <td>
                <a href="edit_discipline.php?id=<?= $r['id'] ?>" class="btn-edit">Edit</a>
                <a href="delete_discipline.php?id=<?= $r['id'] ?>" class="btn-delete" onclick="return confirm('Delete this record?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>

    <?php if ($type==="all" || $type==="counseling"): ?>
    <h2>Counseling Records</h2>
    <table>
        <tr>
            <th>ID</th><th>Student ID</th><th>Student Name</th><th>Counselor</th><th>Date</th><th>Notes</th><th>Follow-up</th><th>Action</th>
        </tr>
        <?php while($r=$counseling_res->fetch_assoc()): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['stud_id']) ?></td>
            <td><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['counselor_name']) ?></td>
            <td><?= htmlspecialchars($r['session_date']) ?></td>
            <td><?= htmlspecialchars($r['notes']) ?></td>
            <td><?= $r['follow_up_required'] ? "Yes" : "No" ?></td>
            <td>
                <a href="edit_counseling.php?id=<?= $r['id'] ?>" class="btn-edit">Edit</a>
                <a href="delete_counseling.php?id=<?= $r['id'] ?>" class="btn-delete" onclick="return confirm('Delete this session?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>

</div>

<script>
document.getElementById('logoutLink').addEventListener('click', function(e){
    if(!confirm("Logout now?")) e.preventDefault();
});

const darkToggle = document.getElementById("darkModeToggle");
if(localStorage.getItem("theme")==="dark"){ document.body.classList.add("dark"); }
darkToggle.onclick = function(){
    document.body.classList.toggle("dark");
    localStorage.setItem("theme", document.body.classList.contains("dark")?"dark":"light");
}
</script>

</body>
</html>
