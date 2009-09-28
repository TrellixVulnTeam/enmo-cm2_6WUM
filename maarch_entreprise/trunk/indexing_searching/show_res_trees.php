<?php 
session_name('PeopleBox');
session_start();
require_once($_SESSION['pathtocoreclass']."class_functions.php");
require_once($_SESSION['pathtocoreclass']."class_db.php");
require_once($_SESSION['pathtocoreclass']."class_request.php");

 require_once($_SESSION['pathtocoreclass']."class_core_tools.php");
 require_once($_SESSION['config']['businessapppath']."class".$_SESSION['slash_env'].'class_list_show.php');

require_once($_SESSION['pathtomodules']."autofoldering".$_SESSION['slash_env']."class".$_SESSION['slash_env']."class_modules_tools.php");
$core_tools = new core_tools();
$core_tools->load_lang();
$func = new functions();
$af  = new autofoldering();

$id = ''; 
if(isset($_GET['id']) && !empty($_GET['id']))
{
	$id = $_GET['id'];
}
$_SESSION['af_current_branch_id'] = $id;
$tree_id = ''; 
if(isset($_GET['tree_id']) && !empty($_GET['tree_id']))
{
	$tree_id = $_GET['tree_id'];
}
$parents = array();
if(count($_GET['parent_id']) > 0)
{
	$parents = $_GET['parent_id'];
}

$children = array();
if(count($_GET['children_id']) > 0)
{
	$children = $_GET['children_id'];
}
$core_tools->load_html();
$core_tools->load_header();	
?>
<body id="iframe">
<?php  if(isset($_GET['script']) && !empty($_GET['script']))
{
	$script = $_GET['script'];
	if(file_exists($_GET['script'].".php"))
	{
		include($_GET['script'].".php");
	}
	else
	{
		echo _SCRIPT_UNKNOWN;
	}

}
else
{
	
}?>
</body>
</html>