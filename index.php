/* FEES */

function fees() {
    $s=trim($_GET['s']??'');
    $st=$_GET['st']??'';
    $w=['1=1'];
    $a=[];

    if($s!=='') {
        $w[]='(s.Name LIKE :q OR f.Receipt_No LIKE :q)';
        $a['q']="%$s%";
    }

    if(in_array($st,['Paid','Pending'],true)) {
        $w[]='f.Status=:st';
        $a['st']=$st;
    }

    head('Fees');

    $tot=row("SELECT COALESCE(SUM(CASE WHEN Status='Pending' THEN Amount ELSE 0 END),0) d,COALESCE(SUM(CASE WHEN Status='Paid' THEN Amount ELSE 0 END),0) c FROM fees");

    echo '<div class="stats">
    <div class="stat"><b>'.inr($tot['c']).'</b><span>Collected so far</span></div>
    <div class="stat"><b>'.inr($tot['d']).'</b><span>Still due</span></div>
    </div>
    <form class="bar noprint">
    <input type="hidden" name="p" value="fees">
    <label>Search<input name="s" value="'.e($s).'" placeholder="Student or receipt number"></label>
    <label>Status<select name="st">
    <option value="">All</option>
    <option value="Pending"'.($st==='Pending'?' selected':'').'>Pending</option>
    <option value="Paid"'.($st==='Paid'?' selected':'').'>Paid</option>
    </select></label>
    <button class="btn">Filter</button></form>';

    tbl(rows("SELECT f.Fee_ID id,s.Name Student,f.Fee_Type `Fee`,f.Amount,f.Due_Date `Due on`,IF(f.Status='Pending' AND f.Due_Date<CURDATE(),'Overdue',f.Status) Status,f.Payment_Date `Paid on`,f.Payment_Mode Mode,f.Receipt_No Receipt FROM fees f JOIN student s USING(Student_ID) WHERE ".implode(' AND ',$w)." ORDER BY f.Status DESC,f.Due_Date,s.Name LIMIT 200",$a),fn($x)=>$x['Status']==='Paid'?'<a href="index.php?p=receipt&id='.(int)$x['id'].'">Receipt</a>':'<a href="index.php?p=pay&id='.(int)$x['id'].'">Collect</a>','No fee records match.');

    foot();
}

/* COLLECT FEE */

function pay() {
    $id=(int)($_GET['id']??0);
    $f=row("SELECT f.*,s.Name FROM fees f JOIN student s USING(Student_ID) WHERE Fee_ID=? AND Status='Pending'",[$id]);

    if(!$f) {
        flash('That instalment is already paid or does not exist.',1);
        go('index.php?p=fees');
    }

    if(post()) {
        $mode=$_POST['m']??'';

        if(!in_array($mode,['Cash','UPI','Cheque','Card'],true)) {
            flash('Select a valid payment mode.',1);
            go("index.php?p=pay&id=$id");
        }

        try {
            db()->beginTransaction();

            $updated=q("UPDATE fees SET Status='Paid',Payment_Date=CURDATE(),Payment_Mode=?,Receipt_No=? WHERE Fee_ID=? AND Status='Pending'",[
                $mode,
                'VEMS/'.date('y').'/'.str_pad($id,4,'0',STR_PAD_LEFT),
                $id
            ]);

            if($updated->rowCount()!==1) {
                throw new RuntimeException('Payment was already processed.');
            }

            db()->commit();
            flash('Payment recorded. The receipt is ready.');
            go("index.php?p=receipt&id=$id");

        } catch(Throwable $x) {
            if(db()->inTransaction()) db()->rollBack();
            flash('Payment could not be recorded. Please check the fee status.',1);
            go('index.php?p=fees');
        }
    }

    head('Collect fee');

    echo '<form method="post" class="card grid">'.csrf().'
    <p class="full"><b>'.e($f['Name']).'</b><br>
    '.e($f['Fee_Type']).' · '.inr($f['Amount']).'
    · due '.e(dmy($f['Due_Date'])).'</p>
    <label>Payment mode<select name="m">
    <option>Cash</option><option>UPI</option>
    <option>Cheque</option><option>Card</option>
    </select></label>
    <div class="full">
    <button class="btn">Record payment of '.inr($f['Amount']).'</button>
    <a class="btn ghost" href="index.php?p=fees">Cancel</a>
    </div></form>';

    foot();
}

/* FEE RECEIPT */

function receipt() {
    $f=row("SELECT f.*,s.Name,s.Admission_No,CONCAT(c.Class_Name,'-',c.Section) Cl FROM fees f JOIN student s USING(Student_ID) JOIN class c USING(Class_ID) WHERE Fee_ID=? AND Status='Paid'",[(int)($_GET['id']??0)]);

    head('Fee receipt');

    if(!$f) {
        echo '<p class="msg bad">Receipt not found.</p>';
        foot();
        return;
    }

    echo '<div class="rc">
    <h2>'.e(SCHOOL).'</h2>
    <p class="c">'.e(CITY).'<br><b>Fee receipt</b></p>
    <p>Receipt no. <b>'.e($f['Receipt_No']).'</b>
    · Date '.e(dmy($f['Payment_Date'])).'<br>
    Received from <b>'.e($f['Name']).'</b>,
    Class '.e($f['Cl']).'
    (Adm. no. '.e($f['Admission_No']).')<br>
    Towards '.e($f['Fee_Type']).'
    · Paid by '.e($f['Payment_Mode']).'</p>
    <p style="font:600 24px var(--serif)">'.inr($f['Amount']).'</p>
    <p>'.e(words((int)$f['Amount'])).'</p>
    </div>
    <p class="noprint">
    <button class="btn" onclick="window.print()">Print receipt</button>
    </p>';

    foot();
}

/* LIBRARY */

function lib() {
    if(post()) {
        try {
            db()->beginTransaction();

            if(isset($_POST['ret'])) {
                $id=(int)$_POST['ret'];

                $l=row("SELECT * FROM library WHERE Issue_ID=? AND Return_Date IS NULL FOR UPDATE",[$id]);

                if(!$l) {
                    throw new RuntimeException('Book already returned.');
                }

                $late=max(0,(int)floor((strtotime(date('Y-m-d'))-strtotime($l['Due_Date']))/86400));
                $fine=$late*FINE;

                q("UPDATE library SET Return_Date=CURDATE(),Fine_Amount=? WHERE Issue_ID=?",[$fine,$id]);
                q("UPDATE book SET Status='Available' WHERE Book_ID=?",[$l['Book_ID']]);

                db()->commit();

                flash($fine
                    ?"Book returned. It was $late day(s) late, so the fine is ".inr($fine).'.'
                    :'Book returned on time. No fine.'
                );

            } else {
                $bid=(int)($_POST['b']??0);
                $sid=(int)($_POST['s']??0);

                $book=row("SELECT * FROM book WHERE Book_ID=? AND Status='Available' FOR UPDATE",[$bid]);

                if(!$book||!one("SELECT COUNT(*) FROM student WHERE Student_ID=?",[$sid])) {
                    throw new RuntimeException('Book or student is not available.');
                }

                q("INSERT INTO library(Book_ID,Student_ID,Issue_Date,Due_Date) VALUES(?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL ".LOAN." DAY))",[$bid,$sid]);

                q("UPDATE book SET Status='Issued' WHERE Book_ID=?",[$bid]);

                db()->commit();

                flash('Book issued. It is due back in '.LOAN.' days.');
            }

        } catch(Throwable $x) {
            if(db()->inTransaction()) db()->rollBack();
            flash($x->getMessage()?:'Library operation failed.',1);
        }

        go('index.php?p=library');
    }

    head('Library');

    echo '<form method="post" class="bar card">'.csrf().'
    <label>Book<select name="b" required>
    '.opts(rows("SELECT Book_ID,Book_Name FROM book WHERE Status='Available' ORDER BY Book_Name")).'
    </select></label>
    <label>Student<select name="s" required>'.opts(rows($GLOBALS['SC'])).'</select></label>
    <button class="btn">Issue book</button>
    </form>
    <h2 style="margin-bottom:12px">Books currently out</h2>';

    tbl(rows("SELECT l.Issue_ID id,b.Book_Name Title,s.Name Student,l.Issue_Date `Issued on`,l.Due_Date `Due on`,IF(l.Due_Date<CURDATE(),'Overdue','Issued') Status,IF(l.Due_Date<CURDATE(),DATEDIFF(CURDATE(),l.Due_Date)*".FINE.",0) Fine FROM library l JOIN book b USING(Book_ID) JOIN student s USING(Student_ID) WHERE l.Return_Date IS NULL ORDER BY l.Due_Date"),fn($x)=>'<form method="post">'.csrf().'<input type="hidden" name="ret" value="'.(int)$x['id'].'"><button class="lnk">Mark returned</button></form>','No books are out right now. Everything is back on the shelf.');

    foot();
}

/* REPORTS */

function reps() {
    head('Reports');

    echo '<p class="noprint">
    <button class="btn" onclick="window.print()">Print these reports</button>
    </p><div class="cols">';

    $reports=[
        'Fee defaulters (overdue)' => "SELECT s.Name,CONCAT(c.Class_Name,'-',c.Section) Class,SUM(f.Amount) Due FROM fees f JOIN student s USING(Student_ID) JOIN class c USING(Class_ID) WHERE f.Status='Pending' AND f.Due_Date<CURDATE() GROUP BY s.Student_ID ORDER BY Due DESC,s.Name LIMIT 15",

        'Attendance below 75%' => "SELECT s.Name,CONCAT(c.Class_Name,'-',c.Section) Class,ROUND(SUM(a.Status='Present')/COUNT(*)*100) `Attendance %` FROM attendance a JOIN student s USING(Student_ID) JOIN class c USING(Class_ID) GROUP BY s.Student_ID HAVING `Attendance %`<75 ORDER BY `Attendance %`",

        'Toppers, latest exam' => "SELECT s.Name,CONCAT(c.Class_Name,'-',c.Section) Class,ROUND(SUM(r.Marks)/SUM(e.Max_Marks)*100,1) `Percentage` FROM result r JOIN student s USING(Student_ID) JOIN examination e USING(Exam_ID) JOIN class c USING(Class_ID) WHERE r.Exam_ID=(SELECT MAX(Exam_ID) FROM result) GROUP BY s.Student_ID ORDER BY `Percentage` DESC LIMIT 10",

        'Overdue library books' => "SELECT b.Book_Name Title,s.Name Student,DATEDIFF(CURDATE(),l.Due_Date)*".FINE." Fine FROM library l JOIN book b USING(Book_ID) JOIN student s USING(Student_ID) WHERE l.Return_Date IS NULL AND l.Due_Date<CURDATE()"
    ];

    foreach($reports as $t=>$s) {
        echo '<div class="card"><h2>'.e($t).'</h2>';
        tbl(rows($s),null,'Nothing to report. All good here.');
        echo '</div>';
    }

    echo '</div>';
    foot();
}

/* ADMINISTRATION */

function adm() {
    if(post()) {
        try {
            if(isset($_POST['tg'])) {
                $id=(int)$_POST['tg'];
                if($id==me()['Admin_ID']) {
                    throw new RuntimeException('You cannot deactivate your own account.');
                }

                q("UPDATE admin SET Status=IF(Status='Active','Inactive','Active') WHERE Admin_ID=?",[$id]);

            } elseif(isset($_POST['rp'])) {
                $id=(int)$_POST['rp'];
                $pw=$_POST['np']??'';

                if(strlen($pw)<6) throw new RuntimeException('Password must contain at least 6 characters.');

                q("UPDATE admin SET Password=? WHERE Admin_ID=?",[password_hash($pw,PASSWORD_DEFAULT),$id]);

            } elseif(isset($_POST['opt'])) {
                foreach(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    db()->query("OPTIMIZE TABLE `$t`")->fetchAll();
                }

            } elseif(isset($_POST['un'])) {
                $un=trim($_POST['un']??'');
                $fn=trim($_POST['fn']??'');
                $pw=$_POST['pw']??'';
                $role=$_POST['r']??'';

                if(strlen($pw)<6||!in_array($role,['Admin','Teacher','Accountant','Librarian'],true)||$un===''||$fn==='') {
                    throw new RuntimeException('Please fill every field. Password needs at least 6 characters.');
                }

                q("INSERT INTO admin(Username,Password,Full_Name,Role) VALUES(?,?,?,?)",[
                    $un,password_hash($pw,PASSWORD_DEFAULT),$fn,$role
                ]);
            }

            flash('Done. The change has been saved.');

        } catch(Throwable $x) {
            flash($x->getCode()==='23000'?'That username is already taken.':$x->getMessage(),1);
        }

        go('index.php?p=admin');
    }

    head('Admin');

    echo '<div class="cols">
    <form method="post" class="card grid">
    <h2 class="full">Add a user</h2>'.csrf().'
    <label>Full name<input name="fn" required></label>
    <label>Username<input name="un" required></label>
    <label>Role<select name="r">
    <option>Teacher</option><option>Accountant</option>
    <option>Librarian</option><option>Admin</option>
    </select></label>
    <label>Password<input name="pw" type="password" minlength="6" required></label>
    <div class="full"><button class="btn" name="create" value="1">Add user</button></div>
    </form>

    <div class="card">
    <h2>Database</h2>
    <p class="muted">Download a full copy of every table. To restore it, import the file in phpMyAdmin.</p>
    <a class="btn" href="index.php?p=backup">Download backup</a>
    <form method="post" style="display:inline">'.csrf().'
    <button class="btn ghost" name="opt" value="1">Optimize tables</button>
    </form>
    </div></div>
    <h2 style="margin-bottom:12px">Users</h2>';

    tbl(
        rows("SELECT Admin_ID id,Full_Name Name,Username,Role,Status FROM admin ORDER BY Role,Full_Name"),
        fn($x)=>'<form method="post" style="display:inline">'.csrf().'
        <input type="hidden" name="tg" value="'.(int)$x['id'].'">
        <button class="lnk">'.($x['Status']==='Active'?'Deactivate':'Activate').'</button>
        </form>
        <form method="post">'.csrf().'
        <input type="hidden" name="rp" value="'.(int)$x['id'].'">
        <input name="np" type="password" placeholder="New password" minlength="6" required style="width:130px;display:inline;padding:4px 8px">
        <button class="lnk">Reset</button></form>'
    );

    echo '<h2 style="margin:22px 0 12px">Rows in each table</h2>';

    $r=[];
    foreach(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $r[]=['Table'=>$t,'Rows'=>one("SELECT COUNT(*) FROM `$t`")];
    }

    tbl($r);
    foot();
}

/* ROUTING AND ACCESS CONTROL */

$p=$_GET['p']??'dashboard';

if($p==='logout') {
    $_SESSION=[];
    session_destroy();
    go('index.php?p=login');
}

if($p==='login') login();

$gate=[
    'marks'=>'exams',
    'card'=>'exams',
    'pay'=>'fees',
    'receipt'=>'fees',
    'backup'=>'admin'
];

$g=$gate[$p]??$p;

if(!isset($NAV[$g])) {
    if($p!=='backup') {
        need();
        head('Page not found');
        echo '<div class="card">
        <h2>We could not find that page.</h2>
        <a href="index.php">Back to the dashboard</a>
        </div>';
        foot();
        exit;
    }
}

need($NAV[$g][1]??['Admin']);

/* DATABASE BACKUP */

if($p==='backup') {
    try {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="school_backup_'.date('Ymd_His').'.sql"');

        echo "SET FOREIGN_KEY_CHECKS=0;\n";

        foreach(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $create=row("SHOW CREATE TABLE `$t`");

            echo "DROP TABLE IF EXISTS `$t`;\n";
            echo $create['Create Table'].";\n";

            foreach(rows("SELECT * FROM `$t`") as $r) {
                $values=array_map(
                    fn($v)=>$v===null?'NULL':db()->quote((string)$v),
                    array_values($r)
                );

                echo "INSERT INTO `$t` VALUES(".implode(',',$values).");\n";
            }
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        exit;

    } catch(Throwable $x) {
        http_response_code(500);
        exit('Backup could not be generated. Please check the database.');
    }
}

/* PAGE MAP */

$map=[
    'dashboard'=>'dash',
    'attendance'=>'att',
    'marks'=>'marks',
    'card'=>'card',
    'fees'=>'fees',
    'pay'=>'pay',
    'receipt'=>'receipt',
    'library'=>'lib',
    'reports'=>'reps',
    'admin'=>'adm'
];

if(isset($E[$p])) {
    crud($p);
} elseif(isset($map[$p])) {
    $map[$p]();
} else {
    head('Page not found');
    echo '<div class="card">
    <h2>We could not find that page.</h2>
    <a href="index.php">Back to the dashboard</a>
    </div>';
    foot();
}