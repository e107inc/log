<?php
/*
+ ----------------------------------------------------------------------------------------------+
|     e107 website system  : http://e107.org
|     Steve Dunstan 2001-2002 : jalist@e107.org
|     Released under the terms and conditions of the GNU General Public License (http://gnu.org).
|
|     $Source: /cvs_backup/e107_0.8/e107_plugins/log/e_meta.php,v $
|     $Revision: 1.1.1.1 $
|     $Date: 2006-12-02 04:35:27 $
|     $Author: mcfly_e107 $
+-----------------------------------------------------------------------------------------------+
*/
if (!defined('e107_INIT')) { exit; }

if (isset($pref['statActivate']) && $pref['statActivate'])
{
	if(!$pref['statCountAdmin'] && ADMIN)
	{
		/* don't count admin visits */
	}
	else
	{
		require_once(e_PLUGIN."log/consolidate.php");
		echo "<script type='text/javascript'>\n";
		echo "<!--\n";
		echo "document.write( '<link rel=\"stylesheet\" type=\"text/css\" href=\"".e_PLUGIN_ABS."log/log.php?referer=' + ref + '&color=' + colord + '&eself=' + eself + '&res=' + res + '\">' );\n";
		echo "// -->\n";
		echo "</script>";
	}
}



?>