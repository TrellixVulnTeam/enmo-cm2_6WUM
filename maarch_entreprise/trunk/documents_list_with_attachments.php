<?php
/*
*
*   Copyright 2008,2013 Maarch
*
*   This file is part of Maarch Framework.
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
*   along with Maarch Framework.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* @brief   Displays document extended list in baskets
*
* @file
* @author Yves Christian Kpakpo <dev@maarch.org>
* @date $date$
* @version $Revision$
* @ingroup apps
*/
require_once 'core/class/class_request.php';
require_once 'core/class/class_security.php';
require_once 'apps/' . $_SESSION['config']['app_id'] . '/class/class_contacts.php';
require_once 'core/class/class_manage_status.php';
require_once 'apps/' . $_SESSION['config']['app_id'] . '/class/class_lists.php';
            
$status_obj = new manage_status();
$security   = new security();
$core_tools = new core_tools();
$request    = new request();
$contact    = new contacts();
$list       = new lists();

//Include definition fields
include_once('apps/' . $_SESSION['config']['app_id'] . '/definition_mail_categories.php');

//Basket information
if(!empty($_SESSION['current_basket']['view'])) {
    $table = $_SESSION['current_basket']['view'];
} else {
    $table = $_SESSION['current_basket']['table'];
}
$_SESSION['origin'] = 'basket';
$_SESSION['collection_id_choice'] = $_SESSION['current_basket']['coll_id'];//Collection

//Table
$select[$table]= array(); 

//Fields
array_push($select[$table],"res_id", "status", "category_id", "category_id as category_img", 
                        "contact_firstname", "contact_lastname", "contact_society", "user_lastname", 
                        "user_firstname", "priority", "creation_date", "admission_date", "subject", 
                        "process_limit_date", "entity_label", "dest_user", "type_label", 
                        "exp_user_id", "count_attachment");
                        
if($core_tools->is_module_loaded("cases") == true) {
    array_push($select[$table], "case_id", "case_label", "case_description");
}

//Where clause
$where_tab = array();
//From basket
if (!empty($_SESSION['current_basket']['clause'])) $where_tab[] = stripslashes($_SESSION['current_basket']['clause']); //Basket clause
//From filters
$filterClause = $list->getFilters(); 
if (!empty($filterClause)) $where_tab[] = $filterClause;//Filter clause
//Build where
$where = implode(' and ', $where_tab);
//Keep where clause
if(isset($_REQUEST['origin']) && $_REQUEST['origin'] == 'searching') {
    $where = $_SESSION['searching']['where_request'] . ' '. $where;
}

//Order
$order = $order_field = '';
$order = $list->getOrder();
$order_field = $list->getOrderField();
if (!empty($order_field) && !empty($order)) 
    $orderstr = "order by ".$order_field." ".$order;
else  {
    $list->setOrder();
    $list->setOrderField('creation_date');
    $orderstr = "order by creation_date desc";
}

//Request
$tab=$request->select($select, $where, $orderstr, $_SESSION['config']['databasetype'], $_SESSION['config']['databasesearchlimit'], false, "", "", "", false, false, 'distinct');
// $request->show();

//Templates
$defaultTemplate = 'documents_list_with_attachments';
$selectedTemplate = $list->getTemplate();
if  (empty($selectedTemplate)) {
    if (!empty($defaultTemplate)) {
        $list->setTemplate($defaultTemplate);
        $selectedTemplate = $list->getTemplate();
    }
}
$template_list = array();
array_push($template_list, 'documents_list_with_attachments');
if($core_tools->is_module_loaded('cases')) array_push($template_list, 'cases_list');

//For status icon
$extension_icon = '';
if($selectedTemplate <> 'none') $extension_icon = "_big"; 

//Result Array
for ($i=0;$i<count($tab);$i++)
{
    for ($j=0;$j<count($tab[$i]);$j++)
    {
        foreach(array_keys($tab[$i][$j]) as $value)
        {
            if($tab[$i][$j][$value]=="res_id")
            {
                $tab[$i][$j]["res_id"]=$tab[$i][$j]['value'];
                $tab[$i][$j]["label"]=_GED_NUM;
                $tab[$i][$j]["size"]="4";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='res_id';
                $_SESSION['mlb_search_current_res_id'] = $tab[$i][$j]['value'];
            }
            if($tab[$i][$j][$value]=="creation_date")
            {
                $tab[$i][$j]["value"]=$core_tools->format_date_db($tab[$i][$j]["value"], false);
                $tab[$i][$j]["label"]=_CREATION_DATE;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='creation_date';
            }
            if($tab[$i][$j][$value]=="admission_date")
            {
                $tab[$i][$j]["value"]=$core_tools->format_date_db($tab[$i][$j]["value"], false);
                $tab[$i][$j]["label"]=_ADMISSION_DATE;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["order"]='admission_date';
            }
            if($tab[$i][$j][$value]=="process_limit_date")
            {
                $tab[$i][$j]["value"]=$core_tools->format_date_db($tab[$i][$j]["value"], false);
                $compareDate = "";
                if($tab[$i][$j]["value"] <> "" && ($statusCmp == "NEW" || $statusCmp == "COU" || $statusCmp == "VAL" || $statusCmp == "RET"))
                {
                    $compareDate = $core_tools->compare_date($tab[$i][$j]["value"], date("d-m-Y"));
                    if($compareDate == "date2")
                    {
                        $tab[$i][$j]["value"] = "<span style='color:red;'><b>".$tab[$i][$j]["value"]."<br><small>(".$core_tools->nbDaysBetween2Dates($tab[$i][$j]["value"], date("d-m-Y"))." "._DAYS.")<small></b></span>";
                    }
                    elseif($compareDate == "date1")
                    {
                        $tab[$i][$j]["value"] = $tab[$i][$j]["value"]."<br><small>(".$core_tools->nbDaysBetween2Dates(date("d-m-Y"), $tab[$i][$j]["value"])." "._DAYS.")<small>";
                    }
                    elseif($compareDate == "equal")
                    {
                        $tab[$i][$j]["value"] = "<span style='color:blue;'><b>".$tab[$i][$j]["value"]."<br><small>("._LAST_DAY.")<small></b></span>";
                    }
                }
                $tab[$i][$j]["label"]=_PROCESS_LIMIT_DATE;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='process_limit_date';
            }
            if($tab[$i][$j][$value]=="category_id")
            {
                $_SESSION['mlb_search_current_category_id'] = $tab[$i][$j]["value"];
                // $tab[$i][$j]["value"] = $_SESSION['mail_categories'][$tab[$i][$j]["value"]];
                $tab[$i][$j]["label"]=_CATEGORY;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='category_id';
            }
            if($tab[$i][$j][$value]=="priority")
            {
                $tab[$i][$j]["value"] = $_SESSION['mail_priorities'][$tab[$i][$j]["value"]];
                $tab[$i][$j]["label"]=_PRIORITY;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["order"]='priority';
            }
            if($tab[$i][$j][$value]=="subject")
            {
                $tab[$i][$j]["value"] = $request->cut_string($request->show_string($tab[$i][$j]["value"]), 250);
                $tab[$i][$j]["label"]=_SUBJECT;
                $tab[$i][$j]["size"]="12";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='subject';
            }
            if($tab[$i][$j][$value]=="category_id")
            {
                $_SESSION['mlb_search_current_category_id'] = $tab[$i][$j]["value"];
                $tab[$i][$j]["value"] = $_SESSION['mail_categories'][$tab[$i][$j]["value"]];
                $tab[$i][$j]["label"]=_CATEGORY;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='category_id';
            }
            if($tab[$i][$j][$value]=="contact_firstname")
            {
                $contact_firstname = $tab[$i][$j]["value"];
                $tab[$i][$j]["show"]=false;
            }
            if($tab[$i][$j][$value]=="contact_lastname")
            {
                $contact_lastname = $tab[$i][$j]["value"];
                $tab[$i][$j]["show"]=false;
            }
            if($tab[$i][$j][$value]=="contact_society")
            {
                $contact_society = $tab[$i][$j]["value"];
                $tab[$i][$j]["show"]=false;
            }
            if($tab[$i][$j][$value]=="user_firstname")
            {
                $user_firstname = $tab[$i][$j]["value"];
                $tab[$i][$j]["show"]=false;
            }
            if($tab[$i][$j][$value]=="user_lastname")
            {
                $user_lastname = $tab[$i][$j]["value"];
                $tab[$i][$j]["show"]=false;
            }
            if($tab[$i][$j][$value]=="exp_user_id")
            {
                $tab[$i][$j]["label"]=_CONTACT;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["value_export"] = $tab[$i][$j]['value'];
                $tab[$i][$j]["value"] = $contact->get_contact_information_from_view($_SESSION['mlb_search_current_category_id'], $contact_lastname, $contact_firstname, $contact_society, $user_lastname, $user_firstname);
                $tab[$i][$j]["order"]=false;
            }
            if($tab[$i][$j][$value]=="type_label")
            {
                $tab[$i][$j]["value"] = $request->show_string($tab[$i][$j]["value"]);
                $tab[$i][$j]["label"]=_TYPE;
                $tab[$i][$j]["size"]="12";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='type_label';
            }
            if($tab[$i][$j][$value]=="status")
            {
                $res_status = $status_obj->get_status_data($tab[$i][$j]['value'],$extension_icon);
                $statusCmp = $tab[$i][$j]['value'];
                $tab[$i][$j]['value'] = "<img src = '".$res_status['IMG_SRC']."' alt = '".$res_status['LABEL']."' title = '".$res_status['LABEL']."'>";
                $tab[$i][$j]["label"]=_STATUS;
                $tab[$i][$j]["size"]="4";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=true;
                $tab[$i][$j]["order"]='status';
            }
            if($tab[$i][$j][$value]=="category_img")
            {
                $tab[$i][$j]["label"]=_CATEGORY;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["value_export"] = $tab[$i][$j]['value'];
                $my_imgcat = get_img_cat($tab[$i][$j]['value'],$extension_icon);
                $tab[$i][$j]['value'] = "<img src = '".$my_imgcat."' alt = '' title = ''>";
                $tab[$i][$j]["value"] = $tab[$i][$j]['value'];
                $tab[$i][$j]["order"]="category_id";
            }
            if($tab[$i][$j][$value]=="count_attachment")
            {
                $tab[$i][$j]["label"]=_ATTACHMENTS;
                $tab[$i][$j]["size"]="12";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["order"]='count_attachment';
            }
            if($tab[$i][$j][$value]=="case_id" && $core_tools->is_module_loaded("cases") == true)
            {
                $tab[$i][$j]["label"]=_CASE_NUM;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["value_export"] = $tab[$i][$j]['value'];
                $tab[$i][$j]["value"] = "<a href='".$_SESSION['config']['businessappurl']."index.php?page=details_cases&module=cases&id=".$tab[$i][$j]['value']."'>".$tab[$i][$j]['value']."</a>";
                $tab[$i][$j]["order"]="case_id";
            }
            if($tab[$i][$j][$value]=="case_label" && $core_tools->is_module_loaded("cases") == true)
            {
                $tab[$i][$j]["label"]=_CASE_LABEL;
                $tab[$i][$j]["size"]="10";
                $tab[$i][$j]["label_align"]="left";
                $tab[$i][$j]["align"]="left";
                $tab[$i][$j]["valign"]="bottom";
                $tab[$i][$j]["show"]=false;
                $tab[$i][$j]["value_export"] = $tab[$i][$j]['value'];
                $tab[$i][$j]["order"]="case_label";
            }
        }
    }
}

//Cl� de la liste
$listKey = 'res_id';

//Initialiser le tableau de param�tres
$paramsTab = array();
$paramsTab['pageTitle'] =  _RESULTS." : ".count($tab).' '._FOUND_DOCS;              //Titre de la page
$paramsTab['bool_sortColumn'] = true;                                               //Affichage Tri
$paramsTab['bool_bigPageTitle'] = false;                                            //Affichage du titre en grand
$paramsTab['bool_showIconDocument'] = true;                                         //Affichage de l'icone du document
$paramsTab['bool_showIconDetails'] = true;                                          //Affichage de l'icone de la page de details
$paramsTab['urlParameters'] = 'baskets='.$_SESSION['current_basket']['id'];         //Parametres d'url supplementaires
$paramsTab['filters'] = array('entity', 'category', 'contact');                     //Filtres    
if (count($template_list) > 0 ) {                                                   //Templates
    $paramsTab['templates'] = array();
    $paramsTab['templates'] = $template_list;
}
$paramsTab['bool_showTemplateDefaultList'] = true;                                  //Default list (no template)
$paramsTab['defaultTemplate'] = $defaultTemplate;                                   //Default template
$paramsTab['tools'] = array();                                                      //Icones dans la barre d'outils
$export = array(
        "script"        =>  "window.open('".$_SESSION['config']['businessappurl']."index.php?display=true&page=export', '_blank');",
        "icon"          =>  $_SESSION['config']['businessappurl']."static.php?filename=tool_export.gif",
        "tooltip"       =>  _EXPORT_LIST,
        "disabledRules" =>  count($tab)." == 0"
        );
array_push($paramsTab['tools'],$export);

//Afficher la liste
$status = 0;
$content = $list->showList($tab, $paramsTab, $listKey, $_SESSION['current_basket']);
// $debug = $list->debug(false);
echo "{'status' : " . $status . ", 'content' : '" . addslashes($debug.$content) . "', 'error' : '" . addslashes($error) . "'}";
