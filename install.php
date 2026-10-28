<?php
// Run once: creates the database, tables and sample data.
require 'inc.php';
$pdo=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS '.DB_NAME.' CHARACTER SET utf8mb4');$pdo->exec('USE '.DB_NAME);
if($pdo->query("SHOW TABLES LIKE 'student'")->fetch())die('Already installed. Delete the school_management database in phpMyAdmin to start fresh.');
foreach(array_filter(array_map('trim',explode(';',file_get_contents('schema.sql')))) as $s)$pdo->exec($s);
mt_srand(11);$ins=function($t,$r)use($pdo){$pdo->prepare("INSERT INTO $t(".implode(',',array_keys($r)).") VALUES(".implode(',',array_fill(0,count($r),'?')).")")->execute(array_values($r));};
$ph=fn()=>mt_rand(6,9).mt_rand(100000000,999999999);$pick=fn($a)=>$a[mt_rand(0,count($a)-1)];
foreach([['admin','Admin@123','Mr. Atharva Ingale','Admin'],['teacher1','Teacher@123','Mr. Rajendra Pawar','Teacher'],['accounts1','Accounts@123','Mr. Dinesh Mehta','Accountant'],['library1','Library@123','Mrs. Kavita Joshi','Librarian']] as [$u,$p,$n,$r])$ins('admin',['Username'=>$u,'Password'=>password_hash($p,PASSWORD_DEFAULT),'Full_Name'=>$n,'Role'=>$r]);
foreach([[8,'A'],[8,'B'],[9,'A'],[9,'B'],[10,'A']] as [$c,$s])$ins('class',['Class_Name'=>$c,'Section'=>$s]);
foreach([['Mrs. Sunita Kulkarni','M.A., B.Ed.',41500],['Mr. Rajendra Pawar','M.Sc., B.Ed.',46000],['Dr. Anjali Deshpande','Ph.D. (Chemistry)',58000],['Mrs. Farzana Sheikh','M.A., B.Ed.',40000],['Mr. Venkatesh Iyer','M.Sc. (Mathematics)',47500],['Mr. Harpreet Singh','MCA, B.Ed.',44000]] as [$n,$q,$s]){$m=strtolower(preg_replace('/^(Mr|Mrs|Dr)\. /','',$n));$ins('teacher',['Name'=>$n,'Qualification'=>$q,'Contact'=>$ph(),'Email'=>str_replace(' ','.',$m).'@vidyanjali.edu.in','Salary'=>$s]);}
$subs=['English'=>4,'Hindi'=>1,'Marathi'=>1,'Mathematics'=>5,'Science'=>3,'Social Science'=>2,'Computer Science'=>6];foreach($subs as $n=>$t)$ins('subject',['Subject_Name'=>$n,'Teacher_ID'=>$t]);
$boys=['Aarav','Rohan','Arjun','Kabir','Vihaan','Siddharth','Aditya','Gurpreet','Yash','Omkar','Ishaan','Tanmay'];$girls=['Ananya','Sneha','Meera','Diya','Fatima','Riya','Kavya','Isha','Pooja','Tanvi','Aditi','Simran'];
$sur=['Deshmukh','Patil','Kulkarni','Iyer','Sheikh','Singh','Nair','Joshi','Khan','Banerjee','Jadhav','Shinde','Gupta','Pawar','Mehta','Reddy','Chavan','Bhosale','Kapoor','Menon'];
$dads=['Rajesh','Sunil','Anil','Prakash','Vikram','Suresh','Manoj','Dinesh'];$soc=['Shivneri Society','Ganesh Heights','Sai Residency','Om Apartments','Lokmanya Nagar'];
$area=[['College Road','422005'],['Gangapur Road','422013'],['Panchavati','422003'],['Indira Nagar','422009'],['Satpur','422007']];
$days=[];for($d=0;count($days)<15;$d++){$t=strtotime("-$d day");if(date('N',$t)<6)$days[]=date('Y-m-d',$t);}
$pdo->beginTransaction();$n=0;
for($c=1;$c<=5;$c++)for($i=0;$i<8;$i++){$n++;$g=$i%2?'Female':'Male';$sn=$pick($sur);$a=$pick($area);$cl=[8,8,9,9,10][$c-1];
 $ins('student',['Admission_No'=>'VEMS26'.str_pad($n,3,'0',STR_PAD_LEFT),'Name'=>$pick($g=='Male'?$boys:$girls).' '.$sn,'Gender'=>$g,'DOB'=>(2021-$cl).'-'.sprintf('%02d-%02d',mt_rand(1,12),mt_rand(1,28)),'Address'=>'Flat '.mt_rand(1,24).', '.$pick($soc).', '.$a[0].', Nashik, Maharashtra '.$a[1],'Contact'=>$ph(),'Parent_Name'=>$pick($dads).' '.$sn,'Parent_Contact'=>$ph(),'Class_ID'=>$c]);
 $bad=$n%11==0;foreach($days as $d){$x=mt_rand(1,100);$ins('attendance',['Student_ID'=>$n,'Date'=>$d,'Status'=>$x<=($bad?65:92)?'Present':($x<=($bad?95:96)?'Absent':'Late')]);}
 $amt=[8=>12500,9=>13500,10=>15000][$cl];foreach([['Tuition Term 1','2026-06-15',85],['Tuition Term 2','2026-10-15',40]] as [$ft,$du,$pc]){$paid=mt_rand(1,100)<=$pc;
  $ins('fees',['Student_ID'=>$n,'Fee_Type'=>$ft,'Amount'=>$amt,'Due_Date'=>$du,'Payment_Date'=>$paid?date('Y-m-d',strtotime($du)-mt_rand(0,9)*86400):null,'Status'=>$paid?'Paid':'Pending','Payment_Mode'=>$paid?$pick(['Cash','UPI','Cheque','Card']):null]);}}
$pdo->exec("UPDATE fees SET Receipt_No=CONCAT('VEMS/26/',LPAD(Fee_ID,4,'0')) WHERE Status='Paid'");
$ins('examination',['Exam_Name'=>'Unit Test 1','Date'=>'2026-07-20','Max_Marks'=>50]);$ins('examination',['Exam_Name'=>'First Term Exam','Date'=>'2026-10-05','Max_Marks'=>100]);
for($s=1;$s<=$n;$s++){$ab=mt_rand(45,92);foreach([1=>50,2=>100] as $ex=>$mx)for($b=1;$b<=7;$b++){$m=max(8,min(100,$ab+mt_rand(-12,12)))*$mx/100;$m=round($m*2)/2;$ins('result',['Student_ID'=>$s,'Exam_ID'=>$ex,'Subject_ID'=>$b,'Marks'=>$m,'Grade'=>grade($m,$mx)]);}}
$bk=[['Wings of Fire','A. P. J. Abdul Kalam'],['Malgudi Days','R. K. Narayan'],['The Discovery of India','Jawaharlal Nehru'],['Panchatantra','Vishnu Sharma'],["Harry Potter and the Philosopher's Stone",'J. K. Rowling'],['Swami and Friends','R. K. Narayan'],['Shyamchi Aai','Sane Guruji'],['The Jungle Book','Rudyard Kipling'],['Gitanjali','Rabindranath Tagore'],['Ignited Minds','A. P. J. Abdul Kalam']];
foreach($bk as [$t,$a])$ins('book',['Book_Name'=>$t,'Author'=>$a]);
foreach([[1,3,-40],[2,12,-6],[5,20,-3]] as [$b,$s,$o]){$is=date('Y-m-d',strtotime("$o day"));$ins('library',['Book_ID'=>$b,'Student_ID'=>$s,'Issue_Date'=>$is,'Due_Date'=>date('Y-m-d',strtotime("$is +".LOAN.' day'))]);$pdo->exec("UPDATE book SET Status='Issued' WHERE Book_ID=$b");}
$pdo->commit();?><!doctype html><meta charset="utf-8"><link rel="stylesheet" href="style.css"><div class="body"><div class="card"><h2>All set. The database is ready.</h2><p>Sign in with <b>admin</b> / <b>Admin@123</b>. Other users: teacher1 / Teacher@123, accounts1 / Accounts@123, library1 / Library@123. Please change these passwords from the Admin page.</p><a class="btn" href="index.php">Go to sign in</a></div></div>
