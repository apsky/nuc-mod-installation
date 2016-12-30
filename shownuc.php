<?php

/**
 * @author PISK
 * @copyright 2014
 * 
 */

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include_once($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_nuc.' . $phpEx);


// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

if($fp) ftruncate($fp, 0); // очищаем файл
//if (!isset($GLOBALS['config'])) 
global $config, $template, $portal_config, $phpEx, $db, $user, $auth, $phpbb_root_path;

// анонимус не имеет сессии поэтому создаем новую 
// если уже есть сессия то и ладно
session_start();

//er('huhu');

// Begin SEO phpBB  это чтобы боты не имели сессий
if ($user->data['is_bot'] )
{
$session_id = false;
}
// End SEO phpBB
// если страница не указана, выводим первую
//er('koko');
$page = request_var('page',0);
//er('hoho');
// число объявлений на странице
$qry_range = 12;
$qry_start = $page * $qry_range;

$cook = session_id();

// общее число концептов
$sql =  'select count(*) as a from phpbb_nuc_concepts where phpbb_nuc_concepts.user_id='.$user->data['user_id'].
        " and not exists ( select concept_id from phpbb_nuc_newers where session_id = '$cook' and 
          phpbb_nuc_newers.concept_id = phpbb_nuc_concepts.concept_id )";
$result = $db->sql_query($sql);
$row = $result->fetch_assoc();
$rec = $row['a'];
$db->sql_freeresult($result);

// выкачиваем порциями
$sql = 'select concept_id, concept, forum_id, topic_id, link_url from phpbb_nuc_concepts where phpbb_nuc_concepts.user_id='
       .$user->data['user_id'].
       " and not exists ( select concept_id from phpbb_nuc_newers where session_id = '$cook' and 
          phpbb_nuc_newers.concept_id = phpbb_nuc_concepts.concept_id )".
       ' order by phpbb_nuc_concepts.irnd, phpbb_nuc_concepts.ndate limit '.$qry_start.','.$qry_range.';';

//er($sql);
 
 
$temp = array();
$conc_ary = array();

$html = '<div id="nuc_block">';

if ($result = $db->sql_query($sql)) {
    
    er(session_id());
    $_SESSION['nuc_concept_count'] = 2;//$rec; //22.11.2013
    er('$_SESSION["nuc_concept_count"]='.$_SESSION['nuc_concept_count']);
    
    $html .= '<ul class="list_coll" id="news" style="list-style: none;">';
    //извлечение ассоциативного массива 
    while ($row = $result->fetch_assoc()) {
        $html .= '<li class="nuclist">';
        $CONCEPT_ID = $row['concept_id'];
		$CONCEPT_CAPTION = $row['concept'];
        $CONCEPT_AJAX = $phpbb_root_path.'nuc.php?concept_id='.$row['concept_id'];
		$CONCEPT_URL = $row['link_url'];
        $CONCEPT_TOPIC = ($row['topic_id']>0)?
            $phpbb_root_path."viewtopic.$phpEx?f=".$row['forum_id']."&amp;t=".$row['topic_id']:
            $phpbb_root_path."posting.php?mode=post&f=3";
        $CONCEPT_TOPIC_CAPTION = ($row['topic_id']>0)?"Обсуждение":"Создать обсуждение";
        $SMILIES_PATH = $phpbb_root_path.$config['smilies_path'].'/';
        $html .= '
    <a class="nuclink" id="no_hvr" data-qry="'.$CONCEPT_AJAX.'&nuc=0" data-hr="'.$CONCEPT_TOPIC.'" target="_blank">'.$CONCEPT_CAPTION.'</a>
    <br style="line-height: 18px;"/>
    <span class="voteline" style="display: none;" >  
    <img class="nuc" id="nuc1" title="'.$user->lang('NUC_NUC1').'" src="'.$SMILIES_PATH.'nuc1.png" data-qry="'.$CONCEPT_AJAX.'&nuc=1" data-id="'.$CONCEPT_ID.'" data-checked="0" ></img>
    <img class="nuc" id="nuc2" title="'.$user->lang('NUC_NUC2').'" src="'.$SMILIES_PATH.'nuc2.png" data-qry="'.$CONCEPT_AJAX.'&nuc=2" data-id="'.$CONCEPT_ID.'" data-checked="0" ></img>
    <img class="nuc" id="nuc3" title="'.$user->lang('NUC_NUC3').'" src="'.$SMILIES_PATH.'nuc3.png" data-qry="'.$CONCEPT_AJAX.'&nuc=3" data-id="'.$CONCEPT_ID.'" data-checked="0" ></img>
    <img class="nuc" id="nuc4" title="'.$user->lang('NUC_NUC4').'" src="'.$SMILIES_PATH.'nuc4.png" data-qry="'.$CONCEPT_AJAX.'&nuc=4" data-id="'.$CONCEPT_ID.'" data-checked="0" ></img>
    <a class="nuc" id="forum_link" href="'.$CONCEPT_TOPIC.'" style="display: none;" >'.$CONCEPT_TOPIC_CAPTION.'</a>';
        $html .= '<br style="line-height: 18px;"/> </span> <hr/> </li>';
    }
    $html .= '</ul>';
    
    // удаление выборки 
    $db->sql_freeresult($result);
}

// ------------  линейка навигации  -------------------

// вычисляем к-во страниц
$pages = ceil($rec/$qry_range);
$ku = $qry_start + $qry_range;

//if ($rec%$qry_range==0) $pages++;
for($i=1;$i<$pages;$i++) { 
    $st = ($i==$page) ? 'style="color: red;"' : '';  
    $lineyka .= "<a href=shownuc.php?page=$i  $st><u>$i</u></a> ";
}
er('$rec='.$rec.' $qry_range='.$qry_range.' $qry_start='.$qry_start.' $pages='.$pages.' ku='.$ku);
// если страница не первая, выводим ссылку "Назад"
if ($page > 0) {
$p = $page - 1; 
$lineyka = "<a href=shownuc.php?page=$p>Назад</a>&nbsp".$lineyka;
}
$page++;                                   // увеличиваем страницу

// выводим ссылку на следующие пять записей (на след. страницу),
// если она есть, то есть число записей, которые нужно вывести, и 
// смещение не превышают общего числа записей
if (($rec>0)&&($ku <= $rec)) { 
    $lineyka .= "<a href=shownuc.php?page=$page>Далее</a>";
}

if($lineyka) $html .= '<div id="nuc_navigator">'.$lineyka.'</div>';


$html .= '</div>';
die($html);
//die(json_encode(array( 'lineyka' => $lineyka, 'concepts' => $conc_ary)));

?>