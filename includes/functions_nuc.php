<?php

/**
 * @author MESMERiZE
 * @copyright 2014
 */

if (!defined('IN_PHPBB'))
{
	exit;
}


function console_log( $data ){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}

function er( $str, $show=true )
{
    //if (!isset($GLOBALS['debugg'])) 
    //global $debugg;
    if (!$show) return false;
    $err = date( 'd.m.Y H:i:s ', time() ).$str.PHP_EOL;
    console_log($err);
    return true;
}

function set_info($name, $value='', $comment='')
{
	global $db;

    $n = $db->sql_escape($name);
    $v = $db->sql_escape($value);
    $c = $db->sql_escape($comment);
	
    $sql = sprintf( 
        "INSERT INTO %s (name,value,comment) VALUES ('%s','%s','%s') ON DUPLICATE KEY UPDATE value='%s',comment='%s';", 
        NUC_INFO_TABLE, $n, $v, $c, $v, $c ); 
    
	return $db->sql_query($sql);
}

function get_info($name)
{
	global $db;

    $n = $db->sql_escape($name);

    $sql = sprintf( 
        "SELECT value,comment FROM %s WHERE name='%s';", 
        NUC_INFO_TABLE, $n ); 
    
	$result = $db->sql_query($sql);
    return $db->sql_fetchrow($result);
}


?>