<?php
session_start(); 
include "db.php";

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','counselor'])) { 
    header('Location: login.php'); 
    exit; 
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('Invalid ID');
$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT cr.*, s.name AS student_name, s.student_id AS stud_code 
    FROM counseling_records cr 
    JOIN students s ON cr.student_id = s.id 
    WHERE cr.id = ?");
$stmt->bind_param('i',$id); 
$stmt->execute(); 
$rec = $stmt->get_result()->fetch_assoc();

if (!$rec) die('Not found');

$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $session_date = $_POST['session_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $follow = isset($_POST['follow_up'])?1:0;

    $u = $conn->prepare("UPDATE counseling_records 
        SET session_date=?, notes=?, follow_up_required=? 
        WHERE id=?");
    $u->bind_param('ssii',$session_date,$notes,$follow,$id);

    if($u->execute()){ 
        header('Location: list_discipline_counseling.php?msg=updated'); 
        exit; 
    } else $msg='❌ Update failed';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Counseling</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:Inter,Segoe UI,system-ui,sans-serif;
    background:linear-gradient(135deg,#2980b9,#6dd5fa,#ffffff);
    min-height:100vh;
    display:flex
}

/* ---------------------------------------- */
/*  UPDATED GLASS SIDEBAR FROM add_discipline.php */
/* ---------------------------------------- */
.sidebar{
    width:260px;
    height:100vh;
    position:fixed;
    left:0;
    top:0;
    backdrop-filter:blur(12px);
    background:rgba(0,0,0,0.35);
    padding:25px 20px;
    border-right:1px solid rgba(255,255,255,0.25);
    display:flex;
    flex-direction:column;
}
.sidebar h2{
    color:#fff;
    margin-bottom:25px;
    font-size:22px;
    text-align:center;
    letter-spacing:1px;
}
.sidebar a{
    padding:12px 14px;
    margin:6px 0;
    border-radius:10px;
    color:#ffffff;
    text-decoration:none;
    font-size:15px;
    display:flex;
    align-items:center;
    gap:10px;
    transition:0.25s;
    background:rgba(255,255,255,0.05);
}
.sidebar a:hover{
    background:rgba(255,255,255,0.18);
    transform:translateX(5px);
}
.sidebar a i{
    width:20px;
    text-align:center;
}

/* Active menu highlight */
.sidebar .active{
    background:rgba(255,255,255,0.35);
    font-weight:600;
}

/* ---------------------------------------- */
.content{
    margin-left:260px;
    padding:36px;
    flex:1
}
.card{
    background:#fff;
    border-radius:12px;
    padding:22px;
    box-shadow:0 10px 30px rgba(2,6,23,0.12);
    max-width:900px
}
.input{
    display:block;
    width:100%;
    padding:12px;
    border-radius:10px;
    border:1px solid #e3e6ee
}
.actions{
    display:flex;
    gap:10px;
    margin-top:16px
}
.btn-primary{
    background:#2d9cdb;
    color:#fff;
    padding:10px 16px;
    border-radius:10px;
    border:none;
}
.btn{
    padding:10px 16px;
    border-radius:10px;
    background:#eee;
    text-decoration:none;
    color:#333;
}
</style>
</head>

<body>

<!-- UPDATED SIDEBAR -->
<div class="sidebar">
    <h2>Counselor Panel</h2>

    <a href="counselor_dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="student_counselor.php"><i class="fa fa-users"></i> Manage Students</a>
    <a href="list_discipline_counseling.php" class="active"><i class="fa fa-file-alt"></i> Records</a>
    <a href="add_counseling.php"><i class="fa fa-user-plus"></i> Add Counseling</a>
    <a href="add_discipline.php"><i class="fa fa-exclamation-circle"></i> Add Discipline</a>
    <a href="analytics_dashboard.php">
        <i class="fa fa-chart-line"></i> Analytics Dashboard
    <a href="logout.php" id="logoutLink"><i class="fa fa-door-open"></i> Logout</a>
</div>



<div class="content">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div>
        <h2 style="margin:0">Edit Counseling Session</h2>
        <div style="color:#666;font-size:13px">
            Student: <?=htmlspecialchars($rec['student_name']).' ('.htmlspecialchars($rec['stud_code']).')'?>
        </div>
      </div>
      <a href="list_discipline_counseling.php" class="btn">Back</a>
    </div>

    <?php if($msg): ?>
      <div style="padding:10px;background:#fdd;border-radius:8px;margin-bottom:10px">
        <?=htmlspecialchars($msg)?>
      </div>
    <?php endif; ?>

    <form method="post">
      <label>Session Date</label>
      <input class="input" type="date" name="session_date" 
             value="<?=htmlspecialchars($rec['session_date'])?>" required>

      <label style="margin-top:12px">Notes</label>
      <textarea class="input" name="notes" rows="6"><?=htmlspecialchars($rec['notes'])?></textarea>

      <div style="margin-top:10px">
        <label>
          <input type="checkbox" name="follow_up" 
            <?= $rec['follow_up_required'] ? 'checked' : '' ?>> 
            Require Follow-up
        </label>
      </div>

      <div class="actions">
        <button class="btn-primary" type="submit"><i class="fa fa-save"></i> Save</button>
        <a class="btn" href="delete_counseling.php?id=<?=$id?>" 
           onclick="return confirm('Delete this session?')">Delete</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('logoutLink').addEventListener('click', e => {
    if(!confirm('Logout now?')) e.preventDefault();
});
</script>

</body>
</html>
