<?php
/*
*    Copyright 2008,2009 Maarch
*
*  This file is part of Maarch Framework.
*
*   Maarch Framework is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   (at your option) any later version.
*
*   Maarch Framework is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*    along with Maarch Framework.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* @brief Displays application logs
*
*
* @file
* @author  Claire Figueras  <dev@maarch.org>
* @date $date$
* @version $Revision$
* @ingroup admin
*/
session_name('PeopleBox');
session_start();

require_once($_SESSION['config']['businessapppath']."class".DIRECTORY_SEPARATOR."class_list_show.php");
require_once($_SESSION['pathtocoreclass']."class_request.php");

$core_tools2 = new core_tools();
$core_tools2->test_admin('view_history', 'apps');
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
$page_path = $_SESSION['config']['businessappurl'].'index.php?page=history&admin=history';
$page_label = _VIEW_HISTORY2;
$page_id = "history";
$core_tools2->manage_location_bar($page_path, $page_label, $page_id, $init, $level);
/***********************************************************/
$db = new dbquery();
$db->connect();
$where = '';
$label = '';
$tab = array();

$modules = array();
$db->query("select DISTINCT id_module from ".$_SESSION['tablename']['history']);

while($res = $db->fetch_object())
{
	if($res->id_module == 'admin')
	{
		array_push($modules, array('id' => 'admin', 'label' => _ADMIN));
	}
	elseif(isset($_SESSION['modules_loaded'][$res->id_module]['comment']) && !empty($_SESSION['modules_loaded'][$res->id_module]['comment']))
	{
		array_push($modules, array('id' => $res->id_module, 'label' => $_SESSION['modules_loaded'][$res->id_module]['comment']));
	}
	else
	{
		array_push($modules, array('id' => $res->id_module, 'label' => $res->id_module));
	}
}

function cmp($a, $b)
{
   	return strcmp(strtolower($a["label"]), strtolower($b["label"]));
}
usort($modules, "cmp");
usort($_SESSION['history_keywords'], "cmp");

$history_action= '';
$history_user = '';
$history_module= '';
$history_datefin =  '';
$history_datestart = '';

if($_REQUEST['search']  ||
(isset($_SESSION['m_admin']['history']['action']) && !empty($_SESSION['m_admin']['history']['action']))  ||
(isset($_SESSION['m_admin']['history']['user']) && !empty($_SESSION['m_admin']['history']['user']))  ||
(isset($_SESSION['m_admin']['history']['module']) && !empty($_SESSION['m_admin']['history']['module']))  ||
(isset($_SESSION['m_admin']['history']['datefin']) && !empty($_SESSION['m_admin']['history']['datefin']))  ||
(isset($_SESSION['m_admin']['history']['datestart']) && !empty($_SESSION['m_admin']['history']['datestart']))
)
{
	$pattern = "/^[0-3][0-9]-[0-1][0-9]-[1-2][0-9][0-9][0-9]$/";
	$_SESSION['chosen_user'] = '';
	if((isset($_REQUEST['action']) ) || (isset($_SESSION['m_admin']['history']['action']) && !empty($_SESSION['m_admin']['history']['action']) ))
	{
		if(isset($_REQUEST['action']))
		{
			$history_action= $_REQUEST['action'];
			$_SESSION['m_admin']['history']['action'] = $history_action;
		}
		else
		{
			$history_action=$_SESSION['m_admin']['history']['action'];
		}
		if(!empty($history_action))
		{
			$where .= "  ".$_SESSION['tablename']['history'].".event_type = '".$history_action."' and ";
		}
	}

	if(isset($_REQUEST['user']) || (isset($_SESSION['m_admin']['history']['user']) && !empty($_SESSION['m_admin']['history']['user'])))
	{
		if(isset($_REQUEST['user']))
		{
			$history_user = $_REQUEST['user'];
			$_SESSION['m_admin']['history']['user'] = $history_user;
		}
		else
		{
			$history_user=$_SESSION['m_admin']['history']['user'];
		}
		if(!empty($history_user))
		{
			$tmp = str_replace(')', '', substr($history_user, strrpos($history_user,'(')+1));
			$where .= "  ".$_SESSION['tablename']['history'].".user_id = '".$tmp."' and";
		}
	}
	if(isset($_REQUEST['module']) || (isset($_SESSION['m_admin']['history']['module']) && !empty($_SESSION['m_admin']['history']['module'])))
	{
		if(isset($_REQUEST['module']))
		{
			$history_module= $_REQUEST['module'];
			$_SESSION['m_admin']['history']['module'] = $history_module;
		}
		else
		{
			$history_module=$_SESSION['m_admin']['history']['module'];
		}
		if(!empty($history_module))
		{
			$where .= "  ".$_SESSION['tablename']['history'].".id_module = '".$history_module."' and";
		}
	}

	if(isset($_REQUEST['datestart']) )
	{
		if(empty($_REQUEST['datestart']))
		{
			$_SESSION['m_admin']['history']['datestart'] = '';
		}
		else
		{
			if( preg_match($pattern,$_REQUEST['datestart'])==false )
			{
				$_SESSION['error'] = _DATE.' '._WRONG_FORMAT;
			}
			else
			{
				$_SESSION['m_admin']['history']['datestart'] = $_REQUEST['datestart'];
				if($_SESSION['config']['databasetype'] == "POSTGRESQL" && (isset($_REQUEST['datestart']) && !empty($_REQUEST['datestart'])))
				{
					$history_datestart = $_REQUEST['datestart'];
				}
				else if(isset($_REQUEST['datestart']) && !empty($_REQUEST['datestart']))
				{
					$history_datestart = str_replace('-','',$_REQUEST['datestart']);
				}
				$where .= " (".$_SESSION['tablename']['history'].".event_date >= '".$history_datestart."') and ";
			}
		}
	}

	if(isset($_REQUEST['datefin']) )
	{
		if(empty($_REQUEST['datefin']))
		{
			$_SESSION['m_admin']['history']['datefin'] = '';
		}
		else
		{

			if( preg_match($pattern,$_REQUEST['datefin'])==false  )
			{
				$_SESSION['error'] = _DATE.' '._WRONG_FORMAT;
			}
			else
			{
				$_SESSION['m_admin']['history']['datefin'] = $_REQUEST['datefin'];
				if($_SESSION['config']['databasetype'] == "POSTGRESQL" && (isset($_REQUEST['datefin']) && !empty($_REQUEST['datefin'])))
				{
					$history_datefin = $_REQUEST['datefin'];
				}
				else if(isset($_REQUEST['datefin']) && !empty($_REQUEST['datefin']))
				{
					$history_datefin = str_replace('-','',$_REQUEST['datefin']);
				}

				$where .= " ( ".$_SESSION['tablename']['history'].".event_date <= '".$history_datefin."') and ";
			}
		}
	}
	$where = trim($where);
	$where = preg_replace('/and$/', '', $where);
}
 	$select[$_SESSION['tablename']['history']] = array();
	array_push($select[$_SESSION['tablename']['history']],"id","event_date","user_id", "table_name", 'event_type', "info" );

	$select[$_SESSION['tablename']['users']] = array();
	array_push($select[$_SESSION['tablename']['users']],"lastname","firstname" );

	$list = new list_show();
	$order = 'desc';
	if(isset($_REQUEST['order']) && !empty($_REQUEST['order']))
	{
		$order = trim($_REQUEST['order']);
	}
	$field = 'event_date';
	if(isset($_REQUEST['order_field']) && !empty($_REQUEST['order_field']))
	{
		$field = trim($_REQUEST['order_field']);
	}

	$orderstr = $list->define_order($order, $field);
	$req = new request();

	$tab = $req->select($select, $where,$orderstr, $_SESSION['config']['databasetype'], $limit="500",true,$_SESSION['tablename']['history'],$_SESSION['tablename']['users'],"user_id");

for ($i=0;$i<count($tab);$i++)
{
	for ($j=0;$j<count($tab[$i]);$j++)
	{
		foreach(array_keys($tab[$i][$j]) as $value)
		{
			if($tab[$i][$j][$value]=="id")
			{
				$tab[$i][$j]["id"]=$tab[$i][$j]['value'];
				$tab[$i][$j]["show"]=false;
			}
			if($tab[$i][$j][$value]=="event_date")
			{
				$tab[$i][$j]["value"]=$req->dateformat($tab[$i][$j]['value'], '-');
				$tab[$i][$j]["label"]=_DATE;
				$tab[$i][$j]["size"]="12";
				$tab[$i][$j]["label_align"]="left";
				$tab[$i][$j]["align"]="left";
				$tab[$i][$j]["valign"]="bottom";
				$tab[$i][$j]["show"]=true;
				$tab[$i][$j]["order"]='event_date';
			}
			if($tab[$i][$j][$value]== "user_id" )
			{
				$tab[$i][$j]["user_id"]= $tab[$i][$j]['value'];
				$tab[$i][$j]["label"]= _USERS;
				$tab[$i][$j]["size"]="10";
				$tab[$i][$j]["label_align"]="left";
				$tab[$i][$j]["align"]="left";
				$tab[$i][$j]["valign"]="bottom";
				$tab[$i][$j]["show"]=true;
				$tab[$i][$j]["order"]='user_id';
			}
			if($tab[$i][$j][$value]=="table_name")
			{
				$tab[$i][$j]["table_name"]= $tab[$i][$j]['value'];
				$tab[$i][$j]["label"]=_TABLE;
				$tab[$i][$j]["size"]="10";
				$tab[$i][$j]["label_align"]="left";
				$tab[$i][$j]["align"]="left";
				$tab[$i][$j]["valign"]="bottom";
				$tab[$i][$j]["show"]=true;
				$tab[$i][$j]["order"]='table_name';
			}
			if($tab[$i][$j][$value]=='event_type')
			{
				$tab[$i][$j]['value']= $this->show_string($tab[$i][$j]['value']);
				$tab[$i][$j]['event_type']= $tab[$i][$j]['value'];
				$tab[$i][$j]["label"]=_ACTION;
				$tab[$i][$j]["size"]="8";
				$tab[$i][$j]["label_align"]="left";
				$tab[$i][$j]["align"]="left";
				$tab[$i][$j]["valign"]="bottom";
				$tab[$i][$j]["show"]=true;
				$tab[$i][$j]["order"]='event_type';
			}
			if($tab[$i][$j][$value]=="info")
			{
				$tab[$i][$j]['value']= $this->show_string($tab[$i][$j]['value']);
				$tab[$i][$j]["info"]= $tab[$i][$j]['value'];
				$tab[$i][$j]["label"]=_COMMENTS;
				$tab[$i][$j]["size"]="40";
				$tab[$i][$j]["label_align"]="left";
				$tab[$i][$j]["align"]="left";
				$tab[$i][$j]["valign"]="bottom";
				$tab[$i][$j]["show"]=true;
				$tab[$i][$j]["order"]='info';
			}
		}
	}
}

for ($i=0;$i<count($tab);$i++)
{
	for ($j=0;$j<count($tab[$i]);$j++)
	{
		foreach(array_keys($tab[$i][$j]) as $value)
		{
			if($value == 'column' and $tab[$i][$j][$value]=='event_type')
			{
				$val = $core_tools2->is_var_in_history_keywords_tab($tab[$i][$j]['event_type']);
				$tab[$i][$j]['value'] = $val;
			}
		}
	}
}


$nb =count($tab);
?>
<h1><img src="<?php  echo $_SESSION['config']['img'].'/view_history_b.gif' ;?>" alt="" /> <?php  echo _HISTORY_TITLE.' : '.	$nb.' '._RESULTS; ?></h1>
<div id="inner_content">
<?php

$list->admin_list($tab, $nb, '', 'id','history','history','id', true, '', '', '', '', '', '', TRUE, FALSE, '', '', '', false, false);

?>
<br/>
<div id="search_hist" class="block" >
<form name="frm_search_hist" id ="frm_search_hist" action="<?php  echo $_SESSION['config']['businessappurl'];?>index.php?page=history&amp;admin=history" method="post" class="form">
<input type="hidden" name="page" value="history"/>
<input type="hidden" name="admin" value="history" />
<table border="0" width="99%" class="forms">
    <tr >
        <td width="33%">
			<p><label><?php  echo _ACTIONS;?> :</label>
			<select name="action" id="action">
			<option value=""><?php  echo _CHOOSE_ACTION;?></option>
			<?php  for($i=0; $i<count($_SESSION['history_keywords']);$i++)
			{?>
				<option value="<?php  echo $_SESSION['history_keywords'][$i]['id'];?>" <?php if($history_action== $_SESSION['history_keywords'][$i]['id']) {echo 'selected="selected"';}?>><?php  echo $_SESSION['history_keywords'][$i]['label'];?></option>
			<?php  } ?>
		</select></p>
        </td>
        <td width="33%">
			<p><label><?php  echo _MODULES;?> :</label>
			 <select name="module" id="module">
			<option value=""><?php  echo _CHOOSE_MODULE;?></option>
			<?php  for($i=0; $i<count($modules);$i++)
			{?>
				<option value="<?php  echo $modules[$i]['id'];?>" <?php if($history_module== $modules[$i]['id']) {echo 'selected="selected"';}?>><?php  echo $modules[$i]['label'];?></option>
			<?php  } ?>
			  </select></p>
        </td>
		<td width="33%">
			 <p> <label><?php  echo _USER;?> :</label>
  			  <input type="text" name="user" id="user" value="<?php if(isset($history_user)){ echo $history_user;}?>"  /><div id="show_user" class="autocomplete"></div>
  			  </p>
        </td>
    </tr>
</table>
<table border="0" width="99%" class="forms">
<tr>
<td >
	<p><span ><?php  echo _SINCE;?> :</span> <input name="datestart" type="text" id="datestart" onclick='showCalender(this);' value="<?php if(isset($history_datestart)){echo $history_datestart;}?>" />
	</p>
</td>
<td colspan="2">
	<p>
	<span ><?php  echo _FOR;?> :</span> <input name="datefin" type="text" id="datefin"  onclick="showCalender(this);" value="<?php if(isset($history_datefin)){echo $history_datefin;}?>"  />
</p>
</td>
</tr>
<tr>
	<td colspan="2" align="left">
		<p >
		<input type="submit" name="search" value="<?php  echo _SEARCH;?>" class="button" />
		<!--<input class="button" name="clear" type="button" value="<?php echo _CLEAR_FORM;?>" onclick="clear_form('frm_search_hist');this.form.submit();"  />-->
 		<input type="button" class="button"  name="cancel" value="<?php  echo _CANCEL; ?>" onclick="javascript:window.location.href='<?php  echo $_SESSION['config']['businessappurl'];?>index.php?page=admin';"/>
		</p>
	</td>
	<td align="right"><a href="#" onclick="clear_form('frm_search_hist');$('frm_search_hist').submit();"><img src="<?php  echo $_SESSION['config']['businessappurl']."img/reset.gif";?>" alt="<?php echo _CLEAR_FORM;?>" /> <?php  echo _CLEAR_FORM; ?></a></td>
</tr>
</table>
</form>
</div>
<div class="block_end">&nbsp;</div>
<br/>
</div>
<script type="text/javascript">launch_autocompleter('<?php echo $_SESSION['config']['businessappurl'];?>users_autocomplete_list.php', 'user', 'show_user');</script>
