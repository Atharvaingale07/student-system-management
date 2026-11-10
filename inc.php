<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

const SCHOOL = 'Vidyanjali English Medium School';
const CITY = 'Nashik, Maharashtra';
const YEAR = '2026-27';
const FINE = 2;
const LOAN = 14;

function db() {
    static $p = null;
    if ($p instanceof PDO) return $p;
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $name = getenv('DB_NAME') ?: 'school_management';
    $p = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    return $p;
}

function q($s, $a = []) {
    $t = db()->prepare($s);
    $t->execute($a);
    return $t;
}

function rows($s, $a = []) {
    return q($s, $a)->fetchAll();
}

function one($s, $a = []) {
    return q($s, $a)->fetchColumn();
}

function row($s, $a = []) {
    return q($s, $a)->fetch();
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function go($u) {
    header('Location: ' . $u);
    exit;
}

function flash($m, $bad = 0) {
    $_SESSION['fl'] = [$m, $bad];
}

function tok() {
    if (empty($_SESSION['c'])) {
        $_SESSION['c'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['c'];
}

function csrf() {
    return '<input type="hidden" name="c" value="' . e(tok()) . '">';
}

function post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!hash_equals(tok(), $_POST['c'] ?? '')) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }
    return true;
}

function me() {
    return $_SESSION['u'] ?? null;
}

/* SESSION TIMEOUT */

if (me()) {
    if (time() - ($_SESSION['t'] ?? time()) > 1800) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cp = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cp['path'],
                $cp['domain'],
                $cp['secure'],
                $cp['httponly']
            );
        }
        session_destroy();
        go('index.php?p=login');
    }
    $_SESSION['t'] = time();
}

/* CURRENCY AND DATE */

function inr($n) {
    $n = (int)round((float)$n);
    $s = (string)$n;
    $l = substr($s, -3);
    $r = substr($s, 0, -3);
    if ($r !== '') {
        $l = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $r) . ',' . $l;
    }
    return '₹' . $l;
}

function dmy($d) {
    if (!$d || strtotime($d) === false) return '—';
    return date('d-m-Y', strtotime($d));
}

/* GRADING */

function grade($m, $max) {
    if ($max <= 0) return 'F';
    $p = (float)$m / (float)$max * 100;
    foreach ([[90,'A+'],[80,'A'],[70,'B+'],[60,'B'],[50,'C'],[40,'D']] as [$l,$g]) {
        if ($p >= $l) return $g;
    }
    return 'F';
}

/* NUMBER TO WORDS */

function words($n) {
    $n = max(0, (int)$n);
    if ($n === 0) return 'Rupees Zero Only';

    $a = [
        '', 'One', 'Two', 'Three', 'Four', 'Five',
        'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen',
        'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
    ];

    $b = [
        '', '', 'Twenty', 'Thirty', 'Forty',
        'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
    ];

    $f = function($n) use (&$f, $a, $b) {
        if ($n < 20) return $a[$n];
        if ($n < 100) return trim($b[intdiv($n,10)] . ' ' . $a[$n % 10]);
        return trim($a[intdiv($n,100)] . ' Hundred ' . $f($n % 100));
    };

    $o = '';
    foreach ([[10000000,'Crore'],[100000,'Lakh'],[1000,'Thousand']] as [$d,$nm]) {
        if ($n >= $d) {
            $o .= $f(intdiv($n,$d)) . ' ' . $nm . ' ';
            $n %= $d;
        }
    }

    return 'Rupees ' . trim($o . $f($n)) . ' Only';
}

/* DROPDOWNS */

function opts($r, $sel = '') {
    $o = '';
    foreach ($r as $x) {
        $v = array_values($x);
        $o .= '<option value="' . e($v[0]) . '"' .
            ((string)$v[0] === (string)$sel ? ' selected' : '') .
            '>' . e($v[1]) . '</option>';
    }
    return $o;
}

function classes() {
    return rows(
        "SELECT Class_ID, CONCAT(Class_Name,'-',Section)
         FROM class ORDER BY Class_Name,Section"
    );
}

/* USER ROLES */

$ALL = ['Admin','Teacher','Accountant','Librarian'];

$NAV = [
    'dashboard' => ['Dashboard',$ALL],
    'students' => ['Students',$ALL],
    'teachers' => ['Teachers',['Admin']],
    'subjects' => ['Subjects',['Admin']],
    'attendance' => ['Attendance',['Admin','Teacher']],
    'exams' => ['Examinations',['Admin','Teacher']],
    'fees' => ['Fees',['Admin','Accountant']],
    'library' => ['Library',['Admin','Librarian']],
    'books' => ['Books',['Admin','Librarian']],
    'reports' => ['Reports',$ALL],
    'admin' => ['Admin',['Admin']]
];

/* PAGE HEADER */

function head($t) {
    global $NAV;
    $u = me();
    if (!$u) go('index.php?p=login');
    $p = $_GET['p'] ?? 'dashboard';

    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . e($t) . ' | ' . e(SCHOOL) . '</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
<aside class="side" id="side">
<div class="brand">
<b>' . e(SCHOOL) . '</b>
<small>' . e(CITY) . ' · ' . e(YEAR) . '</small>
</div>
<nav>';

    foreach ($NAV as $k => [$l,$r]) {
        if (in_array($u['Role'] ?? '', $r, true)) {
            echo '<a href="index.php?p=' . e($k) . '"' .
                ($p === $k ? ' class="on"' : '') . '>' . e($l) . '</a>';
        }
    }

    echo '</nav>
<a class="out" href="index.php?p=logout">Sign out</a>
</aside>
<main>
<header class="top noprint">
<button id="mb" class="btn ghost" type="button">Menu</button>
<h1>' . e($t) . '</h1>
<span class="who">' . e($u['Full_Name'] ?? '') .
' <em>' . e($u['Role'] ?? '') . '</em></span>
</header>
<section class="body">';

    if (!empty($_SESSION['fl'])) {
        [$m,$b] = $_SESSION['fl'];
        unset($_SESSION['fl']);
        echo '<div class="msg' . ($b ? ' bad' : '') . '">' . e($m) . '</div>';
    }
}

/* PAGE FOOTER */

function foot() {
    echo '</section>
</main>
</div>
<dialog id="dlg">
<form method="dialog">
<p></p>
<button value="no" class="btn ghost">Keep it</button>
<button value="yes" class="btn bad">Yes, delete</button>
</form>
</dialog>
<script>
document.addEventListener("submit",function(ev){
    var f=ev.target;
    if(f.dataset.confirm && !f.ok){
        ev.preventDefault();
        var d=document.getElementById("dlg");
        d.querySelector("p").textContent=f.dataset.confirm;
        d.returnValue="";
        d.showModal();
        d.onclose=function(){
            if(d.returnValue==="yes"){
                f.ok=1;
                f.submit();
            }
        };
    }
});
var mb=document.getElementById("mb");
if(mb){
    mb.onclick=function(){
        document.getElementById("side").classList.toggle("open");
    };
}
</script>
</body>
</html>';
}

/* ROLE AUTHORIZATION */

function need($roles = null) {
    if (!me()) go('index.php?p=login');

    if ($roles && !in_array(me()['Role'] ?? '', $roles, true)) {
        http_response_code(403);
        head('Not open to you');
        echo '<div class="card">
        <h2>That area is not open to your role.</h2>
        <p class="muted">Please ask the admin if you need access.</p>
        </div>';
        foot();
        exit;
    }
}

/* TABLE CELL FORMATTING */

function cell($h, $x) {
    if ($x === null || $x === '') return '—';

    if (in_array($h, ['Salary','Due','Amount','Fine','Pending','Collected'], true)) {
        return inr($x);
    }

    if (preg_match('/^\d{4}-\d\d-\d\d$/', (string)$x)) {
        return dmy($x);
    }

    if (preg_match('/^[6-9]\d{9}$/', (string)$x)) {
        return '+91 ' . substr($x,0,5) . ' ' . substr($x,5);
    }

    $statuses = [
        'Present'=>'ok','Paid'=>'ok','Available'=>'ok',
        'Active'=>'ok','Pass'=>'ok',
        'Absent'=>'bad','Overdue'=>'bad','Fail'=>'bad',
        'Pending'=>'warn','Issued'=>'warn','Late'=>'warn',
        'Inactive'=>'warn'
    ];

    $c = $statuses[(string)$x] ?? null;

    return $c
        ? '<span class="pill ' . $c . '">' . e($x) . '</span>'
        : e($x);
}

/* TABLE DISPLAY */

function tbl($r, $act = null, $empty = 'Nothing here yet.') {
    if (!$r) {
        echo '<p class="muted empty">' . e($empty) . '</p>';
        return;
    }

    echo '<div class="tw"><table><thead><tr>';

    foreach (array_keys($r[0]) as $h) {
        if ($h !== 'id') echo '<th>' . e($h) . '</th>';
    }

    if ($act) echo '<th></th>';

    echo '</tr></thead><tbody>';

    foreach ($r as $x) {
        echo '<tr>';
        foreach ($x as $h => $v) {
            if ($h !== 'id') {
                echo '<td' .
                    (is_numeric($v) && !preg_match('/^[6-9]\d{9}$/',(string)$v)
                        ? ' class="n"' : '') .
                    '>' . cell($h,$v) . '</td>';
            }
        }

        if ($act) echo '<td class="act">' . $act($x) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}