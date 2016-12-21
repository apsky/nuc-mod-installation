<?php

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_nuc.' . $phpEx);


// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

$err = '';
$sql = '';
$debugg = true;
$out = array();
$out['ncounter'] = '';
$out['done'] = false;
$out['registered'] = false;
$get_concept_id = request_var('concept_id',0);
$get_nuc = request_var('nuc',0);
$show_error = false;

if(!is_numeric($get_concept_id) or !is_numeric($get_nuc)) die(json_encode($out['ncounter'] = 'concept or nuc is not numeric'));

if($user->data['is_registered']) {

$out['registered'] = true;

$sql = 'select id,ncounter from phpbb_nuc_dna where concept_id ='.$get_concept_id.' and user_id='.$user->data['user_id'].' and nuc='.$get_nuc.';';

er($sql,$show_error);

// нужен для проверки конца списка концептов
$user->nuc_concept_count--;

if ($result = $db->sql_query($sql)) {
    $row = $db->sql_fetchrow($result);
    $dna_id = $row['id'];
    er('kuku2',$show_error);
    $cnt = $db->sql_affectedrows();
    er('cnt='.$cnt,$show_error);
    if($cnt>1) {
        //   errrrrrooooooorrr
        er('error: cnt>1 ',$show_error);
        $out['ncounter'] = 'more 1 dna found';
    } else {
        // после отправки dna на сервер она уничтожается 
        //а тут даты обновляются чтобы после записи дна концепты снова не появлялись на экране
        //$sql = sprintf( 'update phpbb_nuc_concepts set ndate=%d where concept_id=%d and user_id=%d;',
        //                time(), $get_concept_id, $user->data['user_id']  );
        
        // после голосования концепт уничтожается (новый тренд 08.08.2013)
        $sql = sprintf( 'delete from phpbb_nuc_concepts where concept_id=%d and user_id=%d;', 
                        $get_concept_id, $user->data['user_id']  );
        
        er($sql,$show_error);
        $result = $db->sql_query($sql);
        
        $out['ncounter'] = '';
        if($cnt==1){
            $user->nuc_concept_count--;
            $out['ncounter'] = 'ncounter='.$row['ncounter'];
            $sql = sprintf('update phpbb_nuc_dna set ndate=%d,ncounter=ifnull(ncounter,0)+1 where id=%d', time(),$dna_id );
            er('cnt=1',$show_error);
            $result = $db->sql_query($sql);
        } elseif($cnt==0) {
            $user->nuc_concept_count--;
            $out['ncounter'] = 'insert new';
            $sql = sprintf('insert into phpbb_nuc_dna (concept_id,user_id,nuc,ndate,ncounter) values (%d,%d,%d,%d,1);',
                $get_concept_id,$user->data['user_id'],$get_nuc,time());
            er($sql,$show_error);    
            $result = $db->sql_query($sql);
        } else { er('error: cnt<0',$show_error); $out['ncounter'] = 'strange: found -x dna'; }             
        
    }
    $db->sql_freeresult($result);
}

} // if is_registered
else {
    
    //$sql = sprintf('select count(*) from phpbb_nuc_concepts where user_id = 1;');
    //$result = $db->sql_query($sql);
    //$row = $db->sql_fetchrow($result);
    if (!isset($_SESSION['nuc_concept_count'])) 
    {
        session_start(); // читаем сессию для анонимусов (движок не создает сесси для них)
        er('nuc.php: session_start nuc_concept_count='.$_SESSION['nuc_concept_count'],$show_error);
    }
    
    $sql = sprintf('insert into phpbb_nuc_newers (session_id,concept_id,nuc,session_time) values (\'%s\',%d,%d,%d) 
                   ON DUPLICATE KEY UPDATE session_time=%d;',
            session_id(),$get_concept_id,$get_nuc,time(),time());
    //$user->nuc_concept_count--;
    er($sql,$show_error);        
    $result = $db->sql_query($sql);

    $sql = sprintf('insert into phpbb_nuc_dna (concept_id,user_id,nuc,ndate,ncounter) values (%d,1,%d,%d,1)
                    ON DUPLICATE KEY UPDATE ncounter=ncounter+1,ndate=%d;',
           $get_concept_id,$get_nuc,time(),time());
    $result = $db->sql_query($sql);

    er(sprintf('%d',$_SESSION['nuc_concept_count']),$show_error);
    
    if(--$_SESSION['nuc_concept_count']<1) {
        $out['ncounter'] = 'Тест пройден. Вы можете зарегистрироваться.';
        $out['done'] = true;
        setcookie('nuc_newer_sid',session_id());
        //unset($_SESSION['nuc_concept_count']);
        session_start();
        session_unset(); 
        session_destroy(); 
        //redirect( append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=register') ); не тут 
    }
    
    $db->sql_freeresult($result);
} // not registered

if($fp) fclose($fp); //Закрытие файла

//die(json_encode(array('ncounter'=>$out['ncounter'])));
  die(json_encode($out));
?>