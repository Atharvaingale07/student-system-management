<?php
// Shared settings and helpers. Change the school details here.
session_start();
const SCHOOL='Vidyanjali English Medium School', CITY='Nashik, Maharashtra', YEAR='2026-27', FINE=2, LOAN=14;
const DB_HOST='localhost', DB_USER='root', DB_PASS='', DB_NAME='school_management';
function db(){static $p;return $p??=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function q($s,$a=[]){$t=db()->prepare($s);$t->execute($a);return $t;}
function rows($s,$a=[]){return q($s,$a)->fetchAll();}
function one($s,$a=[]){return q($s,$a)->fetchColumn();}
function row($s,$a=[]){return q($s,$a)->fetch();}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function go($u){header("Location: $u");exit;}
function flash($m,$bad=0){$_SESSION['fl']=[$m,$bad];}
function tok(){return $_SESSION['c']??=bin2hex(random_bytes(16));}
function csrf(){return '<input type="hidden" name="c" value="'.tok().'">';}
function post(){if($_SERVER['REQUEST_METHOD']!=='POST')return false;if(!hash_equals(tok(),$_POST['c']??''))die('This page expired. Please go back and try again.');return true;}
function me(){return $_SESSION['u']??null;}
if(me()){if(time()-($_SESSION['t']??0)>1800){session_destroy();session_start();}else $_SESSION['t']=time();}
function inr($n){$s=(string)(int)round($n);$l=substr($s,-3);$r=substr($s,0,-3);if($r!=='')$l=preg_replace('/\B(?=(\d{2})+(?!\d))/',',',$r).','.$l;return '₹'.$l;}
function dmy($d){return date('d-m-Y',strtotime($d));}
function grade($m,$max){$p=$m/$max*100;foreach([[90,'A+'],[80,'A'],[70,'B+'],[60,'B'],[50,'C'],[40,'D']] as [$l,$g])if($p>=$l)return $g;return 'F';}
function words($n){$a=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];$b=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
 $f=function($n)use(&$f,$a,$b){if($n<20)return $a[$n];if($n<100)return trim($b[intdiv($n,10)].' '.$a[$n%10]);return trim($a[intdiv($n,100)].' Hundred '.$f($n%100));};$o='';
 foreach([[10000000,'Crore'],[100000,'Lakh'],[1000,'Thousand']] as [$d,$nm])if($n>=$d){$o.=$f(intdiv($n,$d)).' '.$nm.' ';$n%=$d;}
 return 'Rupees '.trim($o.$f($n)).' Only';}
function opts($r,$sel=''){$o='';foreach($r as $x){$v=array_values($x);$o.='<option value="'.e($v[0]).'"'.((string)$v[0]===(string)$sel?' selected':'').'>'.e($v[1]).'</option>';}return $o;}
function classes(){return rows("SELECT Class_ID,CONCAT(Class_Name,'-',Section) FROM class ORDER BY Class_Name,Section");}
$ALL=['Admin','Teacher','Accountant','Librarian'];
$NAV=['dashboard'=>['Dashboard',$ALL],'students'=>['Students',$ALL],'teachers'=>['Teachers',['Admin']],'subjects'=>['Subjects',['Admin']],'attendance'=>['Attendance',['Admin','Teacher']],'exams'=>['Examinations',['Admin','Teacher']],'fees'=>['Fees',['Admin','Accountant']],'library'=>['Library',['Admin','Librarian']],'books'=>['Books',['Admin','Librarian']],'reports'=>['Reports',$ALL],'admin'=>['Admin',['Admin']]];
function head($t){global $NAV;$u=me();$p=$_GET['p']??'dashboard';
 echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).' | '.SCHOOL.'</title><link rel="stylesheet" href="style.css"></head><body><div class="app"><aside class="side" id="side"><div class="brand"><b>'.SCHOOL.'</b><small>'.CITY.' · '.YEAR.'</small></div><nav>';
 foreach($NAV as $k=>[$l,$r])if(in_array($u['Role'],$r))echo '<a href="index.php?p='.$k.'"'.($p==$k?' class="on"':'').'>'.$l.'</a>';
 echo '</nav><a class="out" href="index.php?p=logout">Sign out</a></aside><main><header class="top noprint"><button id="mb" class="btn ghost">Menu</button><h1>'.e($t).'</h1><span class="who">'.e($u['Full_Name']).' <em>'.$u['Role'].'</em></span></header><section class="body">';
 if(!empty($_SESSION['fl'])){[$m,$b]=$_SESSION['fl'];unset($_SESSION['fl']);echo '<div class="msg'.($b?' bad':'').'">'.e($m).'</div>';}}
function foot(){echo '</section></main></div><dialog id="dlg"><form method="dialog"><p></p><button value="no" class="btn ghost">Keep it</button> <button value="yes" class="btn bad">Yes, delete</button></form></dialog><script>
document.addEventListener("submit",function(ev){var f=ev.target;if(f.dataset.confirm&&!f.ok){ev.preventDefault();var d=document.getElementById("dlg");d.querySelector("p").textContent=f.dataset.confirm;d.returnValue="";d.showModal();d.onclose=function(){if(d.returnValue=="yes"){f.ok=1;f.submit();}};}});
document.getElementById("mb").onclick=function(){document.getElementById("side").classList.toggle("open");};</script></body></html>';}
function need($roles=null){if(!me())go('index.php?p=login');if($roles&&!in_array(me()['Role'],$roles)){http_response_code(403);head('Not open to you');echo '<div class="card"><h2>That area is not open to your role.</h2><p class="muted">Please ask the admin if you need access.</p></div>';foot();exit;}}
function cell($h,$x){if($x===null||$x==='')return '—';
 if(in_array($h,['Salary','Due','Amount','Fine','Pending','Collected']))return inr($x);
 if(preg_match('/^\d{4}-\d\d-\d\d$/',$x))return dmy($x);
 if(preg_match('/^[6-9]\d{9}$/',$x))return '+91 '.substr($x,0,5).' '.substr($x,5);
 $c=['Present'=>'ok','Paid'=>'ok','Available'=>'ok','Active'=>'ok','Pass'=>'ok','Absent'=>'bad','Overdue'=>'bad','Fail'=>'bad','Pending'=>'warn','Issued'=>'warn','Late'=>'warn','Inactive'=>'warn'][$x]??null;
 return $c?'<span class="pill '.$c.'">'.e($x).'</span>':e($x);}
function tbl($r,$act=null,$empty='Nothing here yet.'){if(!$r){echo '<p class="muted empty">'.e($empty).'</p>';return;}
 echo '<div class="tw"><table><thead><tr>';foreach(array_keys($r[0]) as $h)if($h!='id')echo '<th>'.e($h).'</th>';if($act)echo '<th></th>';echo '</tr></thead><tbody>';
 foreach($r as $x){echo '<tr>';foreach($x as $h=>$v)if($h!='id')echo '<td'.(is_numeric($v)&&!preg_match('/^[6-9]\d{9}$/',$v)?' class="n"':'').'>'.cell($h,$v).'</td>';if($act)echo '<td class="act">'.$act($x).'</td>';echo '</tr>';}
 echo '</tbody></table></div>';}
