<?php
/*
* e107 website system
*
* Copyright 2001-2013 e107 Inc (e107.org)
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Site access logging - 'receiver'

*/

/* File to log page accesses - called with
	e_PLUGIN_ABS."log/log.php?base64encode(referer=' + ref + '&color=' + colord + '&eself=' + eself + '&res=' + res + '\">' );)";
		referer= ref
		color= colord
		eself= eself 
		res= res
		err_direct - optional error flag
		err_referer - referrer if came via error page
		qry = 1 to log query part as well

// Normally the file is 'silent' - if any errors occur, any error message appears in the page header.
*/
//error_reporting(0);
// error_reporting(E_ALL);
define('e_MINIMAL',true);
require_once("../../class2.php"); // More secure to include it. 
header('Cache-Control: no-cache, must-revalidate');		// See if this discourages browser caching
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');		// Date in the past

if (!vartrue($pref['statActivate']))
{
	exit();
}

/**
 *	Set up path to log files.
 *	The log file directory contains a flag file which defines whether logging is enabled.
 */
 /*
function setLogPath()
{
	$siteRoot = realpath(dirname(__FILE__).'./../../').'/';
	@include_once($siteRoot.'e107_config.php');
	if (!isset($mySQLdefaultdb)) return FALSE;
	if (!isset($mySQLprefix)) return FALSE;

	$hash = substr(md5($mySQLdefaultdb.".".$mySQLprefix),0,10);	
	$logDir = $siteRoot.$SYSTEM_DIRECTORY.$hash.'/logs/';
	$logEnable = 0;
	@include_once($logDir.'LogFlag.php');		// See if logging enabled
	define('e_LOG', $logDir);
	return $logEnable;
}
*/

// if (!setLogPath()) exit();				// Could be logging disabled, missing files, all sorts of things. Just do nothing.
//print_r(base64_decode($_GET['lv']));
define('log_INIT', TRUE);



// Array of page names which should have individual query values recorded.
// The top level array index is the page name.
// If the top level value is an array, it must be an array of query string beginnings to match.
$pageUnique = array('page' => 1, 'content' => array('content'));



//$logVals = urldecode(base64_decode($_SERVER['QUERY_STRING']));
//$logVals = urldecode(base64_decode($_GET['lv']));


// --------------- Reworked for v2.x ------------------------


$logVals = base64_decode($_GET['lv']);


$logVals .= "&ip=".USERIP;
$logVals .= "&iphost=". @gethostbyaddr(USERIP);
$logVals .= "&lan=".e_LAN;
$logVals .= "&agent=".$_SERVER['HTTP_USER_AGENT'];

parse_str($logVals, $vals);

$vals['referer'] = urldecode($vals['referer']);
$vals['eself'] = urldecode($vals['eself']);

if(empty($_SESSION['log_userLoggedPages']) || !in_array($vals['eself'],$_SESSION['log_userLoggedPages']))
{
	$_SESSION['log_userLoggedPages'][] = $vals['eself'];
	$logVals .= "&unique=1";
}
else
{
	$logVals .= "&unique=0";
}


$logVals = str_replace('%3A',':',$logVals); // make the URLs a bit cleaner, while keeping any urlqueries encoded.

$lg = e107::getAdminLog();
$lg->addDebug(print_r($logVals, true));
$lg->toFile('SiteStats','Statistics Log', true);

e107::getEvent()->trigger('user_log_stats',$vals);


// ------------------------------------ ---------------------





// We MUST have a timezone set in PHP >= 5.3. This should work for PHP >= 5.1:
// @todo may be able to remove this check once minimum PHP version finalised
if (function_exists('date_default_timezone_get')) 
{
	date_default_timezone_set(@date_default_timezone_get()); // Just set a default - it should default to UTC if no timezone set
}



//$logfp = fopen(e_LOG.'rcvstring.txt', 'a+'); fwrite($logfp, $logVals."\n"); fclose($logfp);
//$logfp = fopen(e_LOG.'rcvstring.txt', 'a+'); fwrite($logfp, print_r($vals, TRUE)."\n"); fclose($logfp);

$colour 		= strip_tags((isset($vals['colour']) ? $vals['colour'] : ''));
$res 			= strip_tags((isset($vals['res']) ? $vals['res'] : ''));
$self 			= strip_tags((isset($vals['eself']) ? $vals['eself'] : ''));
$ref 			= addslashes(strip_tags((isset($vals['referer']) ? $vals['referer'] : '')));
$logQry 		= isset($vals['qry']) && $vals['qry'];
$date			 = date('z.Y', time());
$logPfile 		= e_LOG.'logp_'.$date.'.php';

//$logString = "Colour: {$colour}  Res: {$res}  Self: {$self} Referrer: {$ref} ErrCode: {$vals['err_direct']}\n";
//$logfp = fopen(e_LOG.'rcvstring.txt', 'a+'); fwrite($logfp, $logString); fclose($logfp);


// vet resolution and colour depth some more - avoid dud values
if ($res && preg_match("#.*?((\d+)\w+?(\d+))#", $res, $match))
{
	$res = $match[2].'x'.$match[3];
}
else
{
	$res = '??';			// Can't decode resolution
}

if ($colour && preg_match("#.*?(\d+)#",$colour,$match))
{
	$colour = intval($match[1]);
}
else
{
	$colour='??';
}


if ($err_code = strip_tags((isset($vals['err_direct']) ? $vals['err_direct'] : '')))
{
	$ref = addslashes(strip_tags(isset($vals['err_referer']) ? $vals['err_referer'] : ''));
// Uncomment the next two lines to create a separate CSV format log of invalid accesses - error code, entered URL, referrer
//	$log_string = $err_code.",".$self.",".$ref;
//  $logfp = fopen(e_LOG."errpages.csv", 'a+'); fwrite($logfp, $log_string."\n\r"); fclose($logfp);
	$err_code .= ':';
}

if(strstr($ref, 'admin')) 
{
	$ref = FALSE;
}

$screenstats = $res.'@'.$colour;
$agent = $_SERVER['HTTP_USER_AGENT'];
$ip = getip();

$oldref = $ref; // backup for search string being stripped off for referer
if($ref && !strstr($ref, $_SERVER['HTTP_HOST'])) 
{
	if(preg_match("#http://(.*?)($|/)#is", $ref, $match)) 
	{
		$ref = $match[0];
	}
}


$pageDisallow = "cache|file|eself|admin";
$tagRemove = "(\\\)|(\s)|(\')|(\")|(eself)|(&nbsp;)|(\.php)|(\.html)";
$tagRemove2 = "(\\\)|(\s)|(\')|(\")|(eself)|(&nbsp;)";

preg_match("#/(.*?)(\?|$)(.*)#si", $self, $match);
$match[1] = isset($match[1]) ? $match[1] : '';
$pageName = substr($match[1], (strrpos($match[1], "/")+1));
$PN = $pageName;
$pageName = preg_replace("/".$tagRemove."/si", "", $pageName);
if($pageName == "") $pageName = "index";

if(preg_match("/".$pageDisallow."/i", $pageName)) return;


if ($logQry)
{
	$pageName .= '+'.$match[3];			// All queries match
}
$pageName = $err_code.$pageName;			// Add the error code at the beginning, so its treated uniquely
//$logfp = fopen(e_LOG.'rcvstring.txt', 'a+'); fwrite($logfp, $pageName."\n"); fclose($logfp);

$p_handle = fopen($logPfile, 'r+');
if($p_handle && flock( $p_handle, LOCK_EX ) ) 
{
	$log_file_contents = '';
	while (!feof($p_handle)) // Assemble a string of data
	{  
		$log_file_contents.= fgets($p_handle,1000);
	}
	$log_file_contents = str_replace(array('<'.'?php','?'.'>'),'',$log_file_contents);
	if (eval($log_file_contents) === FALSE && getperms('0'))
	{
		 echo "Error in log file contents: ".$logPfile;
	}
}
elseif(getperms('0'))
{
	echo "Couldn't log data to: ".$logPfile; // returned to js popup. 
	exit;
}


$flag = FALSE;
if(array_key_exists($pageName, $pageInfo)) 
{  // Existing page - just increment stats
	$pageInfo[$pageName]['ttl'] ++;
}
else 
{  // First access of page
	$url = preg_replace("/".$tagRemove2."/si", "", $self);
	if(preg_match("/".$pageDisallow."/i", $url)) return;
	$pageInfo[$pageName] = array('url' => $url, 'ttl' => 1, 'unq' => 1);
	$flag = TRUE;
}

if(!strstr($ipAddresses, $ip)) 
{	/* unique visit */
	if(!$flag) 
	{
		$pageInfo[$pageName]['unq'] ++;
	}
	$siteUnique ++;
	$ipAddresses .= $ip.".";		// IP address is stored as hex string
	require_once('loginfo.php');
}


$siteTotal ++;
$info_data = var_export($pageInfo, true);
//$date_stamp = date("z:Y", time());			// Same as '$date' variable

$data = "<?php

/* e107 website system: Log file: {$date} */

\$ipAddresses = '{$ipAddresses}';
\$siteTotal = '{$siteTotal}';
\$siteUnique = '{$siteUnique}';

\$pageInfo = {$info_data};

?>";

if ($p_handle)
{
	ftruncate($p_handle, 0 );
	fseek( $p_handle, 0 );
	fwrite($p_handle, $data);
	fclose($p_handle);
}


// Get current IP address - return as a hex-encoded string
/*
function getip() 
{
	$ip = $_SERVER['REMOTE_ADDR'];
	if (getenv('HTTP_X_FORWARDED_FOR')) 
	{
		if (preg_match("#^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})#", getenv('HTTP_X_FORWARDED_FOR'), $ip3)) 
		{  
			$ip2 = array('#^0\..*#', 
				   '#^127\..*#', 							// Local loopbacks
				   '#^192\.168\..*#', 						// RFC1918 - Private Network
				   '#^172\.(?:1[6789]|2\d|3[01])\..*#', 	// RFC1918 - Private network
				   '#^10\..*#', 							// RFC1918 - Private Network
				   '#^169\.254\..*#', 						// RFC3330 - Link-local, auto-DHCP 
				   '#^2(?:2[456789]|[345][0-9])\..*#'		// Single check for Class D and Class E
				   );
			$ip = preg_replace($ip2, $ip, $ip3[1]);
		}
	}
	if ($ip == "") 
	{
		$ip = "x.x.x.x";
	}
	if (strpos($ip, ':') === FALSE)
	{	// Its an IPV4 address - return it as 32-character packed hex string
		$ipa = explode(".", $ip);
		return str_repeat('0000',5).'ffff'.sprintf('%02x%02x%02x%02x', $ipa[0], $ipa[1], $ipa[2], $ipa[3]);
	}
	else
	{	// Its IPV6
		if (strpos($ip,'.') !== FALSE)
		{  // IPV4 'tail' to deal with
			$temp = strrpos($ip,':') +1;
			$ipa = explode('.',substr($ip,$temp));
			$ip = substr($ip,0, $temp).sprintf('%02x%02x:%02x%02x', $ipa[0], $ipa[1], $ipa[2], $ipa[3]);
		}
		// Now 'normalise' the address
		$temp = explode(':',$ip);
		$s = 8 - count($temp);		// One element will of course be the blank
		foreach ($temp as $f)
		{
			if ($f == '')
			{
				$ret .= '0000';		// Always put in one set of zeros for the blank
				if ($s > 0)
				{
					$ret .= str_repeat('0000',$s);
					$s = 0;
				}
			}
			else
			{
				$ret .= sprintf('%04x',hexdec($f));
			}
		}
		return $ret;
	}
}
*/

?>