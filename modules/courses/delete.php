<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);
$pdo=$db=db();$id=(int)($_GET['id']??0);
if(!$id){header('Location: ' . IMS_URL . '/modules/courses/index.php');exit;}
try{
    $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
    log_activity('delete_course','courses',"Deleted course ID $id");
    set_toast('success','Course deleted.');
}catch(PDOException $e){set_toast('error','Cannot delete -- students may be enrolled.');}
header('Location: ' . IMS_URL . '/modules/courses/index.php');exit;
