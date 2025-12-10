<?php
session_start();
include "db.php";

// ------------------- ACCESS CONTROL -------------------
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'counselor'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$message = "";

// ------------------- HANDLE FORM SUBMISSION -------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id       = (int)($_POST["student_id"] ?? 0);
    $counselor_id  = (int)$user['id'];
    $session_date  = $_POST["session_date"] ?? '';
    $notes         = trim($_POST["notes"] ?? '');
    $follow_up     = isset($_POST["follow_up"]) ? 1 : 0;

    if (!$user_id || !$session_date) {
        $message = "⚠️ Student and Session Date are required.";

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['error' => $message]);
            exit;
        }
    } else {

        // ------------------- ENSURE STUDENT EXISTS -------------------
        $stmtCheck = $conn->prepare("SELECT id FROM students WHERE id = ?");
        $stmtCheck->bind_param("i", $user_id);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows === 0) {
            // Fetch name from logs
            $row = $conn->query("SELECT name FROM logs WHERE id = $user_id")->fetch_assoc();
            $student_name = $row['name'] ?? 'Unknown';

            $stmtInsert = $conn->prepare("INSERT INTO students (id, name) VALUES (?, ?)");
            $stmtInsert->bind_param("is", $user_id, $student_name);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
        $stmtCheck->close();

        // ------------------- INSERT COUNSELING RECORD -------------------
        $stmt = $conn->prepare("
            INSERT INTO counseling_records (student_id, counselor_id, session_date, notes, follow_up_required)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $message = "❌ Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("iissi", $user_id, $counselor_id, $session_date, $notes, $follow_up);

            if ($stmt->execute()) {
                $message = "✅ Counseling session added successfully!";
            } else {
                $message = "❌ Database error: " . $stmt->error;
            }
            $stmt->close();
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(strpos($message, 'successfully') !== false ? ['success'=>$message] : ['error'=>$message]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Counseling Record</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* ------------------------- GLOBAL STYLING ------------------------- */
body { margin:0; padding:0; display:flex; min-height:100vh; font-family:"Segoe UI", sans-serif; background: linear-gradient(135deg,#2980b9,#6dd5fa,#ffffff); transition:.3s; }
body.dark { background:#111 !important; color:#eee; }
.sidebar { width:260px; height:100vh; background: rgba(0,0,0,0.35); backdrop-filter: blur(12px); padding-top:30px; position:fixed; box-shadow:0 0 25px rgba(0,0,0,0.3); }
.sidebar h2 { color:white; text-align:center; margin-bottom:25px; font-size:22px; }
.sidebar a { display:block; padding:14px 25px; color:white; font-size:16px; text-decoration:none; border-left:4px solid transparent; transition:.2s; }
.sidebar a:hover { background: rgba(255,255,255,0.15); border-left:4px solid #fff; }
.sidebar i { margin-right:10px; }
#darkModeToggle { margin:20px; padding:10px; width:calc(100% - 40px); background:#444; color:white; border:none; border-radius:8px; }
.content { margin-left:260px; padding:40px; width:calc(100% - 260px); }
.page-title { color:white; font-size:30px; font-weight:bold; }
.form-card { margin-top:30px; background: rgba(255,255,255,0.60); padding:30px; border-radius:15px; backdrop-filter: blur(12px); box-shadow:0 10px 25px rgba(0,0,0,0.15); max-width:600px; }
body.dark .form-card { background: rgba(40,40,40,0.75); }
input, select, textarea { width:100%; padding:12px; margin-top:6px; border-radius:10px; border:none; outline:none; }
button { padding:10px 20px; border:none; border-radius:10px; cursor:pointer; }
.alert { padding:12px; margin-bottom:15px; border-radius:8px; font-weight:bold; }
.alert-success { background:#2ecc71; color:white; }
.alert-error { background:#c0392b; color:white; }
</style>
</head>

<body>

<!-- SIDEBAR -->
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

<!-- MAIN CONTENT -->
<div class="content">
    <div class="page-title">Add Counseling Record</div>

    <?php if($message): ?>
        <div class="alert <?= strpos($message,'successfully')!==false ? 'alert-success' : 'alert-error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" id="counselingForm">

            <label>Student</label>
            <input type="text" placeholder="Search students..." id="studentSearch">
            <select name="student_id" id="studentSelect" required>
                <option value="">-- Select Student --</option>
                <?php
                $students = $conn->query("SELECT id,name FROM logs WHERE role='user' ORDER BY name ASC");
                while($row=$students->fetch_assoc()):
                ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>

            <label style="margin-top:15px;">Session Date</label>
            <input type="date" name="session_date" id="sessionDate" required value="<?= date('Y-m-d') ?>">

            <label style="margin-top:15px;">Counseling Notes</label>
            <textarea name="notes" rows="4"></textarea>

            <div style="margin-top:15px;">
                <input type="checkbox" name="follow_up" id="followUpCheck"> Require Follow-Up
            </div>

            <div style="margin-top:20px; display:flex; gap:10px;">
                <a href="counselor_dashboard.php" style="background:#bbb;padding:10px 20px;border-radius:10px;color:#000;text-decoration:none;">Back</a>
                <button type="submit" style="background:#3498db;color:white;">Save Record</button>
            </div>

        </form>
    </div>
</div>

<script>
// DARK MODE
if(localStorage.getItem("theme")==="dark") document.body.classList.add("dark");
document.getElementById("darkModeToggle").onclick=function(){
    document.body.classList.toggle("dark");
    localStorage.setItem("theme", document.body.classList.contains("dark")?"dark":"light");
};

// LOGOUT CONFIRM
document.getElementById('logoutLink').addEventListener('click',e=>{
    if(!confirm("Logout now?")) e.preventDefault();
});

// AUTO TODAY DATE
const sessionDate=document.getElementById("sessionDate");
const today=new Date().toISOString().split('T')[0];
sessionDate.value=today;
sessionDate.min=today;

// STUDENT SEARCH FILTER
const studentSearch=document.getElementById("studentSearch");
const studentSelect=document.getElementById("studentSelect");
studentSearch.addEventListener("input",()=>{
    const filter=studentSearch.value.toLowerCase();
    Array.from(studentSelect.options).forEach(opt=>{
        opt.style.display=opt.text.toLowerCase().includes(filter)?"block":"none";
    });
});

// TOAST NOTIFICATIONS
function showToast(message,type="success"){
    const toast=document.createElement("div");
    toast.innerText=message;
    toast.style.cssText="position:fixed;bottom:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;font-weight:bold;box-shadow:0 5px 15px rgba(0,0,0,0.3);z-index:9999;opacity:0;transition:all 0.5s ease;background:"+(type==="success"?"#2ecc71":"#e74c3c");
    document.body.appendChild(toast);
    setTimeout(()=>{toast.style.opacity="1";},100);
    setTimeout(()=>{toast.style.opacity="0"; setTimeout(()=>toast.remove(),500);},3000);
}

// AJAX FORM SUBMISSION
document.getElementById("counselingForm").addEventListener("submit",function(e){
    e.preventDefault();
    const studentId=studentSelect.value;
    const sessionDateVal=sessionDate.value;
    const notes=document.querySelector("textarea[name='notes']").value;
    const followUp=document.getElementById("followUpCheck").checked?1:0;
    if(!studentId) return showToast("Please select a student.","error");
    if(!sessionDateVal) return showToast("Please select a session date.","error");

    const xhr=new XMLHttpRequest();
    xhr.open("POST","add_counseling.php",true);
    xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
    xhr.onload=function(){
        if(xhr.status===200){
            try{
                const res=JSON.parse(xhr.responseText);
                if(res.success){ showToast(res.success,"success"); document.getElementById("counselingForm").reset(); sessionDate.value=today; }
                else if(res.error){ showToast(res.error,"error"); }
            }catch(e){ showToast("Unexpected server response.","error"); console.error(e); }
        } else { showToast("Server error. Try again later.","error"); }
    };
    const params=`student_id=${encodeURIComponent(studentId)}&session_date=${encodeURIComponent(sessionDateVal)}&notes=${encodeURIComponent(notes)}&follow_up=${encodeURIComponent(followUp)}`;
    xhr.send(params);
});

// FOLLOW-UP ALERT
document.getElementById("followUpCheck").addEventListener("change",function(){
    if(this.checked) showToast("Follow-up enabled.","success");
});
</script>

</body>
</html>
