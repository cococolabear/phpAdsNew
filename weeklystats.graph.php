<?
/* weeklystats.graph.php,v 1.0 2000/12/29 11:06:00 martin braun*/

/* placed to GNU by martin@braun.cc */
                                 
require ("config.php");
require("kcsm.php");
                            
$where=urldecode($where); 


// get week signs for mySQL queries  
require("weeklystats.inc.php");  

list($php_week_sign, $mysql_week_sign) = GetWeekSigns();

mysql_select_db($GLOBALS["phpAds_db"]);                   
$query="
	SELECT 
		count(*), 
		DATE_FORMAT(t_stamp,'".$mysql_week_sign."'),
		DATE_FORMAT(t_stamp,'%Y".$mysql_week_sign."') AS week 
	FROM
		".$GLOBALS['phpAds_tbl_adviews']."
	WHERE 
		".$where."
	GROUP BY 
		week
	ORDER BY 
		week DESC
	LIMIT ".$max_weeks;

$query2="
	SELECT 
		count(*), 
		DATE_FORMAT(t_stamp,'".$mysql_week_sign."'),
		DATE_FORMAT(t_stamp,'%Y".$mysql_week_sign."') AS week 
	FROM
		".$GLOBALS['phpAds_tbl_adclicks']."
	WHERE
		".$where."
	GROUP BY 
		week
	ORDER BY 
		week DESC
	LIMIT ".$max_weeks;
            
$result = mysql_query($query) or mysql_die();
$result2 = mysql_query($query2) or mysql_die();

$text=array(
	'value1' => $GLOBALS['strViews'],
	'value2' => $GLOBALS['strClicks']);

$items=array();                       
$num2 = mysql_num_rows($result2);
$row2 = mysql_fetch_row($result2);
$i=0;
while ($row = mysql_fetch_row($result))   
{
	$items[$i]=array();
	$items[$i]['value1'] = $row[0];
	$items[$i]['value2'] = 0;
	$items[$i]['text'] = $row[1];
	if ($row[2]==$row2[2])
	{
		$items[$i]['value2'] = $row2[0];
		if ( $i < $num2 - 1 )
			$row2 = mysql_fetch_row($result2);
	}
	else
		$items[$i]['value2'] = 0;
	$i++;
}

include('stats.graph.inc.php');
?>
 