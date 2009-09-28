<?php
/**
* File : search_adv.php
*
* Advanced search form
*
* @package  Maarch Framework 3.0
* @version 2.1
* @since 10/2005
* @license GPL
* @author Loïc Vinet  <dev@maarch.org>
*/
session_name('PeopleBox');
session_start();
require_once($_SESSION['pathtocoreclass']."class_functions.php");
require_once($_SESSION['pathtocoreclass']."class_request.php");
require_once($_SESSION['pathtocoreclass']."class_core_tools.php");
require_once($_SESSION['config']['businessapppath']."class".$_SESSION['slash_env'].'class_business_app_tools.php');
$appTools = new business_app_tools();
$core_tools = new core_tools();
$core_tools->test_user();
$core_tools->load_lang();
$core_tools->test_service('search_customer', 'apps');
$_SESSION['indexation'] = false;
/****************Management of the location bar  ************/
$init = false;
if($_REQUEST['reinit'] == "true")
{
	$init = true;
}
$level = "";
if($_REQUEST['level'] == 2 || $_REQUEST['level'] == 3 || $_REQUEST['level'] == 4 || $_REQUEST['level'] == 1)
{
	$level = $_REQUEST['level'];
}
$page_path = $_SESSION['config']['businessappurl'].'index.php?page=search_customer&dir=indexing_searching';
$page_label = _SEARCH_CUSTOMER;
$page_id = "is_search_customer";
$core_tools->manage_location_bar($page_path, $page_label, $page_id, $init, $level);
/***********************************************************/
//Definition de la collection
$_SESSION['collection_id_choice'] = $_SESSION['user']['collections'][0] ;
if ($_GET['erase'] == 'true')
{
	$_SESSION['search'] = array();
}
$_SESSION['origin'] = "search_customer";
if($_REQUEST['num_folder'] <> "")
{
	$_REQUEST['num_folder'] = $appTools->control_abo($_REQUEST['num_folder']);
	$_SESSION['search']['chosen_num_folder'] = $_REQUEST['num_folder'];
}
if($_REQUEST['name_folder'] <> "")
{
	$_SESSION['search']['chosen_name_folder'] = $_REQUEST['name_folder'];
}
//$core_tools->show_array($_REQUEST);
?>
<h1><img src="<?php  echo $_SESSION['config']['businessappurl']."img/search_proj_off.gif";?>" alt="" /> <?php  echo _SEARCH_CUSTOMER_TITLE; ?></h1>
<div id="inner_content" align="center">
	<div class="block">
		<table width="100%" border="0">
			<tr>
				<td align="right"><label><?php  echo _FOLDER_NUM;?> :</td>
				<td>
					<input name="num_folder" type="text" id="num_folder" onchange="javascript:submitForm();" onKeyPress="if(event.keyCode == 13)submitForm();" value="<?php echo $_SESSION['search']['chosen_num_folder'];?>" size="40" />
					<div id="foldersListById" class="autocomplete"></div>
					<script type="text/javascript">
						initList('num_folder', 'foldersListById', '<?php  echo $_SESSION['urltomodules'];?>folder/folders_list_by_id.php', 'folder', '2');
					</script>
				</td>
				<td align="right"><?php  echo _FOLDERNAME;?> :</td>
				<td>
					<input type="text" name="name_folder" id="name_folder" onsubmit="javascript:submitForm();" onKeyPress="if(event.keyCode == 13)submitForm();" value="<?php echo $_SESSION['search']['chosen_name_folder'];?>" size="40" />
					<div id="foldersListByName" class="autocomplete"></div>
					<script type="text/javascript">
						initList('name_folder', 'foldersListByName', '<?php  echo $_SESSION['urltomodules'];?>folder/folders_list_by_name.php', 'folder', '2');
					</script>
				</td>
				<td>
					<input type="button" value="<?php echo _SEARCH;?>" onclick="javascript:submitForm();" class="button">
				</td>
			</tr>
		</table>
	</div>
	<script language="javascript">
		function submitForm()
		{
			window.frames['show_trees'].location.href='<?php echo $_SESSION['config']['businessappurl'];?>indexing_searching/show_trees.php?num_folder='+window.document.getElementById("num_folder").value+'&name_folder='+window.document.getElementById("name_folder").value;
		}
	</script>
	<div class="clearsearch">
		<br>
		<a href="<?php echo $_SESSION['config']['businessappurl'];?>index.php?page=search_customer&dir=indexing_searching&erase=true"><img src="<?php  echo $_SESSION['config']['businessappurl']."img/reset.gif";?>" alt="" height="15px" width="15px" /><?php  echo _NEW_SEARCH; ?></a>
	</div>
	<!-- Display the layout of search_customer -->
	<table width="100%" height="100%" border="1">
		<tr>
			<td width= "55%" height = "720px">
				<iframe name="show_trees" id="show_trees" width="100%" height="720" frameborder="0" scrolling="auto" src="<?php  echo $_SESSION['config']['businessappurl']."indexing_searching/show_trees.php?num_folder=".$_REQUEST['num_folder']."&name_folder=".$_REQUEST['name_folder'];?>"></iframe>
			</td>
			<td>
				<iframe name="view" id="view" width="100%" height="720" frameborder="0" scrolling="no" src="<?php  echo $_SESSION['config']['businessappurl']."indexing_searching/little_details_invoices.php?status=empty";?>"></iframe>
			</td>
		</tr>
	</table>
</div>
