<?php

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : dirname(__FILE__).'/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
//require(dirname(__FILE__). '/includes/functions_nuc.' . $phpEx);
require($phpbb_root_path . 'includes/functions_nuc.' . $phpEx);

$eol = "</br>"."\n";
$debug = false;
$debug = date("w")==0||$debug; //в воскресенье полный апдейт
$debug = true; //первое заполнение
$dbg = $debug?'Full update Mode':''; 

echo "----- Schedule ----- ".$dbg.$eol;
echo 'root path = '.$phpbb_root_path.$eol;

// читаем в инфо время последней работы
//$last_time 

function delta(){
    global $t;
    return ' delta='.(time() - $t); 
}

function insertbb($res,$iuser,$irnd)
{
    global $db, $debug;
    
    $n = 0;
    //if($debug) echo 'insertbb: n='. $db->sql_affectedrows($res).$eol;
    while($row = $db->sql_fetchrow($res))
    {
      //if($debug) echo 'insertbb: row='.print_r($row,true)."<br>\n";
      $conc = $row['concept'];
      $url = $row['link_url'];
      //if($debug) echo 'insertbb: conc='.print_r($conc,true)."<br>\n";
      
      if (!empty($conc))
      {
        $sql = sprintf(
          "insert into phpbb_nuc_concepts (user_id,concept_id,concept,irnd,link_url,forum_id,topic_id) values (%d,%d,'%s',%d,'%s',%d,%d);",
            $iuser,
            $row['concept_id'],
            $conc,
            $irnd + $n,
            $url,
            $row['forum_id'],
            $row['topic_id']
        );
        $result = $db->sql_query($sql);
        //if($debug) echo 'insertbb: result='. $db->sql_affectedrows($result)."<br>\n";
        $db->sql_freeresult($result);
        $n = $n + 1;
      }
    }
    return $n;
}

// получает время последней
$t = time();
$time_row = get_info('schedule_ndate');
$time_last = $debug ? 0: $time_row['value'];
echo "Last time: ".$time_last." (".date( 'd.m.Y H:i:s', $time_last ).")".delta().$eol;

// пишем в инфо время последней работы
$t = time();
$time_curr = time();
set_info('schedule_ndate',time());
echo "Current time: ".$time_curr." (".date( 'd.m.Y H:i:s', $time_curr ).")".delta().$eol;
$interval = $time_curr - $time_last;
$h=floor($interval/3600);
$m=floor(($interval-$h*3600)/60);
echo "Time interval: ".$h.'h:'.$m.'m'.$eol;

// количество юзеров
$t = time();
$sql = sprintf('select count(*) as cnt from '.USERS_TABLE);
$result = $db->sql_query($sql);
$row = $result->fetch_assoc();
$users_count = $row['cnt'];
$db->sql_freeresult($result);
echo 'Users: '.$users_count.delta().$eol;

// количество новых юзеров
$t = time();
$sql = sprintf('select count(*) as cnt from '.USERS_TABLE.' where user_regdate > %d',$time_last);
$result = $db->sql_query($sql);
$row = $result->fetch_assoc();
$new_users_count = $row['cnt'];
$db->sql_freeresult($result);
echo 'New users: '.$new_users_count.delta().$eol;

// всего концептов
$t = time();
$sql = sprintf('select count(*) as cnt from phpbb_nuc_all_concepts');
$result = $db->sql_query($sql);
$row = $result->fetch_assoc();
$concepts_count = $row['cnt'];
$db->sql_freeresult($result);
echo 'Concepts count: '.$concepts_count.delta().$eol;

// определить есть ли новые концепты
$t = time();
$sql = sprintf('select count(*) as cnt from phpbb_nuc_all_concepts where ndate >= %d',$time_last);
$result = $db->sql_query($sql);
$row = $result->fetch_assoc();
$new_concepts_count = $row['cnt'];
$db->sql_freeresult($result);
echo 'New concepts count: '.$new_concepts_count.delta().$eol;

// определить есть ли новые голоса
$t = time();
$sql = sprintf('select count(*) as cnt, nuc from phpbb_nuc_dna where ndate >= %d group by nuc',$time_last);
$result = $db->sql_query($sql);
echo 'New nucs: ';
while($row = $db->sql_fetchrow($result))
{
    echo 'nuc='.$row['nuc'].'('.$row['cnt'].') ';
}
$db->sql_freeresult($result);
echo delta().$eol;
    
/////////////////////////// обновить рекомендации ///////////////////////////////
///////////////////////////////////////////////////////////////////////  
// отправить концепты и рекомендации юзерам
////////////////////////////////////////////////////////////////////////
$t = time();
$number = 100; // запуливаем пул из 100 концептов
//if ($new_concepts_count > 0) // если есть что запуливать
{
    
    // очистить на форуме для анонимуса
    $sql = sprintf('delete from phpbb_nuc_concepts where user_id = 1;');
    $db->sql_query($sql);
    $sql = sprintf('delete from phpbb_nuc_recom_links  where user_id = 1;');
    $db->sql_query($sql);
    $sql = sprintf('delete from phpbb_nuc_recom_topics  where user_id = 1;');
    $db->sql_query($sql);
    $sql = sprintf('delete from phpbb_nuc_find  where user_id = 1;');
    $db->sql_query($sql);
    $sql = sprintf('delete from phpbb_nuc_friends  where user1_id = 1 or user2_id = 1;');
    $db->sql_query($sql);
    // статистика по анонимусу
    $sql = sprintf('select 
        count(if(nuc=0,1,null)) as nuc0,
        count(if(nuc=1,1,null)) as nuc1, 
        count(if(nuc=2,1,null)) as nuc2,
        count(if(nuc=3,1,null)) as nuc3,
        count(if(nuc=4,1,null)) as nuc4
        from phpbb_nuc_dna where ndate >= %d and user_id = 1',$time_last);
    $res = $db->sql_query($sql);
    $row = $res->fetch_assoc();
    $db->sql_freeresult($res);
    // анонимус тупо получает новые концепты
    $sql = sprintf(
        'select concept_id, concept, link_url, forum_id, topic_id from nuc_concepts_view 
        order by ndate desc limit %d;',  $number  );
    $res = $db->sql_query($sql);
    $iuser = 1; //anonimous
    $n = insertbb($res,$iuser,0);
    $db->sql_freeresult($res);
    
    //популярные рекомендации для всех
    $sql = sprintf('delete from phpbb_nuc_recom_links  where user_id = 1 or user_id is null;');
    $db->sql_query($sql);
    $sql = 'set @temp=0;';
    $db->sql_query($sql);
    $sql = sprintf( 
                ' 
                insert into phpbb_nuc_recom_links 
                (user_id,link_id,link_name,link_url,ind) 
                select user_id,link_id,link_name,link_url,@temp:=@temp+1 
                from  recom_links_view where user_id = 1;', 
                $iuser );
    $db->sql_query($sql);
    
    // статистика по анонимусу
    printf( "Anonimous: nuc0(%d) nuc1(%d) nuc2(%d) nuc3(%d) nuc4(%d)<br>\n", 
        $row['nuc0'],$row['nuc1'],$row['nuc2'],$row['nuc3'],$row['nuc4'] );
    
    // активные юзеры  
    $sql = $debug?
        sprintf('select user_id from phpbb_users') :
        // молчунов не обслуживать (или всех обслуживать?) за исключением анонимуса (union без all)
        sprintf('select user_id, count(nuc) as nucs from phpbb_nuc_dna where ndate >= %d group by user_id',$time_last);
    $result = $db->sql_query($sql);
    echo 'Active users: '. $db->sql_affectedrows($result).$eol;

    // перебираем всех остальных
    while($row = $db->sql_fetchrow($result))
    {
        // запулить свежие ndate-desc концепты
        
        $iuser = $row['user_id'];

        if ($iuser == 1) continue; // анонимуса пропускаем (обработан выше)

        $nucs = $row['nucs'];
        $n=0;$n1=0;

        // очистить концепты на форуме для данного юзера
        $sql = sprintf('delete from phpbb_nuc_concepts where user_id=%d;', $iuser );
        $db->sql_query($sql);
        
         
            // ----------- не анонимус --------------------
            
            //////////// запулить number концептов (обновить) для данного юзера ///////

            // запулить свежие ndate-desc концепты на которых нет ответов
            $sql = sprintf( 
                'select concept_id, concept, link_url, forum_id, topic_id from nuc_concepts_view as ncw 
                where not exists (select concept_id from phpbb_nuc_dna where user_id=%d and concept_id = ncw.concept_id)
                order by ndate desc limit %d;', $iuser, $number );
            $res = $db->sql_query($sql);
            $n = insertbb($res,$iuser,0); //засунуть от начала
            $db->sql_freeresult($res);
        
            // запулить оставшиеся от number отвеченные самые старые концепты ndate-asc
            if ($n < $number)
            {    
                $sql = sprintf(
                    "select concepts.concept,concepts.concept_id,concepts.link_url,
                    concepts.forum_id,concepts.topic_id,concepts.post_id,
                    max(if(isnull(dna.NDate),concepts.NDate,dna.NDate)) AS MaxDNADate,
                    dna.user_id from (phpbb_nuc_dna as dna left join phpbb_nuc_concepts as concepts on((concepts.concept_id = 
                    dna.concept_id)))
                    where (dna.user_id = %d) group by concepts.concept_id
                    order by MaxDNADate limit %d;",
                    $iuser, $number - $n
                );
                $res = $db->sql_query($sql);
                $n1 = insertbb($res,$iuser,$n); // засунуть следом
                $db->sql_freeresult($res);
            }
            
            // запулить рекомендованные ссылки
            $sql = sprintf('delete from phpbb_nuc_recom_links where user_id=%d', $iuser );
            $db->sql_query($sql);
            $sql = 'set @temp=0;';
            $db->sql_query($sql); 
            $sql = sprintf( 
                ' 
                insert into phpbb_nuc_recom_links 
                (user_id,link_id,link_name,link_url,ind) 
                select user_id,link_id,link_name,link_url,@temp:=@temp+1 
                from  recom_links_view where user_id = %d;', 
                $iuser );
            $lnk = $db->sql_affectedrows($db->sql_query($sql));
    
            // запулить рекомендованные топики
            $sql = sprintf('delete from phpbb_nuc_recom_topics where user_id=%d', $iuser );
            $db->sql_query($sql);
            $sql = 'set @temp=0;';
            $db->sql_query($sql); 
            $sql = sprintf(
                ' 
                insert into phpbb_nuc_recom_topics (user_id,topic_id,ind) 
                select user_id,topic_id,@temp:=@temp+1 
                from recom_topics_view where user_id = %d;', 
                $iuser );
            $tpc = $db->sql_affectedrows($db->sql_query($sql));
  
            ////////////////// новые друзья //////////////////////////////////////
            
            //-- грохаем друзей
            $sql = sprintf('delete from phpbb_nuc_friends where user1_id=%d', $iuser );
            $db->sql_query($sql);
            
            // оновляем друзей
                $sql = sprintf(  
                'insert into phpbb_nuc_friends
                select * from friends_plus_view where friends_plus_view.user1_id = %d
                order by friends_plus_view.friend_k desc limit 10
                ', $iuser );
            
            /* //этот запрос был раньше. не понял почему не грохаем несуществующих друзей (которых нет в запросе)
                $sql = sprintf(  
                'insert into phpbb_nuc_friends
                select * from friends_plus_view where friends_plus_view.user1_id = %d
                order by friends_plus_view.friend_k desc limit 10
                ON DUPLICATE KEY UPDATE phpbb_nuc_friends.friend_k=friends_plus_view.friend_k ', $iuser );*/
                
            $frc = $db->sql_affectedrows($db->sql_query($sql));    

            $out = sprintf("uid%'-8s--nuc%'-5s  nconc%'-6s oldconc%'-6s links%'-6s topics%'-7s friends%'-8s",$iuser,$nucs,$n,$n1,$lnk,$tpc,$frc);
            echo $out.$eol;
            
            //-- не синхронизировать таблицк users и грохать не существующих юзеров по всем фронтам
  
    } // while
    
        
    $db->sql_freeresult($result);
    echo 'End cycle users: '.delta().$eol;
} // new concepts

 
?>