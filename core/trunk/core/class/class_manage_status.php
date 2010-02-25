<?php
/**
* Core class for status
*
*  Contains all the functions to manage status
*
* @package  maarch
* @version 3.0
* @since 10/2005
* @license GPL v3
* @author  Claire Figueras  <dev@maarch.org>
*
*/

class manage_status extends dbquery
{
	public $statusArr;

	function __construct()
	{
		parent::__construct();
		$this->statusArr = array();
		$this->get_status_data_array();
	}

	public function get_searchable_status()
	{
		$status = array();
		$this->connect();
		$this->query("select id, label_status from ".$_SESSION['tablename']['status']." where can_be_searched = 'Y'");
		while($res = $this->fetch_object())
		{
			array_push($status, array('ID' => $res->id, 'LABEL' => $res->label_status));
		}
		return $status;
	}

	public function get_not_searchable_status()
	{
		$status = array();
		$this->connect();
		$this->query("select id, label_status from ".$_SESSION['tablename']['status']." where can_be_searched = 'N'");
		while($res = $this->fetch_object())
		{
			array_push($status, array('ID' => $res->id, 'LABEL' => $res->label_status));
		}
		return $status;
	}
	
	public function get_status_data_array()
	{
		$this->connect();
		$this->query("select * from ".$_SESSION['tablename']['status']."");
		while($res = $this->fetch_object())
		{
			$id_status = $res->id;
			$status_txt = $this->show_string($res->label_status);
			$maarch_module = $res->maarch_module;
			$img_name = $res->img_filename;
			if(!empty($img_name))
			{
				//For standard
				//$temp_explode = explode( ".", $img_name);
				//$temp_explode[0] = $temp_explode[0].$extension;
				//$img_name = implode(".", $temp_explode);
				
				//For big
				$big_temp_explode = explode( ".", $img_name);
				$big_temp_explode[0] = $big_temp_explode[0]."_big";
				$big_img_name = implode(".", $big_temp_explode);
			}
			if($maarch_module == 'apps' && isset($img_name) && !empty($img_name))
			{
				$img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$img_name;
				$big_img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$big_img_name;
			}
			else if(!empty($maarch_module) && isset($maarch_module)&& isset($img_name) && !empty($img_name))
			{
				$img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$img_name."&module=".$maarch_module;
				$big_img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$big_img_name."&module=".$maarch_module;
			}
			else
			{
				$img_path = $_SESSION['config']['businessappurl'].'static.php?filename=default_status'.$extension.'.gif';
				$big_img_path = $_SESSION['config']['businessappurl'].'static.php?filename=default_status_big.gif';
			}
			if(empty($status_txt) || !isset($status_txt))
			{
				$status_txt = $id_status;
			}
			array_push($this->statusArr, array('ID' => $id_status, 'LABEL' => $status_txt, 'IMG_SRC' => $img_path , 'IMG_SRC_BIG' => $big_img_path));
		}
	}
	
	public function get_status_data($id_status, $extension = '')
	{
		for($cptStatusArr=0;$cptStatusArr<count($this->statusArr);$cptStatusArr++)
		{
			if($id_status == $this->statusArr[$cptStatusArr]['ID'])
			{
				$status_txt = $this->statusArr[$cptStatusArr]['LABEL'];
				if ($extension == "_big")
					$img_path = $this->statusArr[$cptStatusArr]['IMG_SRC_BIG'];
				else
					$img_path = $this->statusArr[$cptStatusArr]['IMG_SRC'];
			}
		}
		return array('ID'=> $id_status, 'LABEL'=> $status_txt, 'IMG_SRC' => $img_path);
	}
	
	/*public function get_status_data($id_status,$extension = '')
	{
		$this->connect();
		$this->query("select label_status, maarch_module, img_filename from ".$_SESSION['tablename']['status']." where id = '".$id_status."'");
		$res = $this->fetch_object();
		$status_txt = $this->show_string($res->label_status);
		$maarch_module = $res->maarch_module;
		$img_name = $res->img_filename;
		if(!empty($img_name))
		{
			$temp_explode = explode( ".", $img_name);
			$temp_explode[0] = $temp_explode[0].$extension;
			$img_name = implode(".", $temp_explode);
		}
		if($maarch_module == 'apps' && isset($img_name) && !empty($img_name))
		{
			$img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$img_name;
		}
		else if(!empty($maarch_module) && isset($maarch_module)&& isset($img_name) && !empty($img_name))
		{
			$img_path = $_SESSION['config']['businessappurl'].'static.php?filename='.$img_name."&module=".$maarch_module;
		}
		else
		{
			$img_path = $_SESSION['config']['businessappurl'].'static.php?filename=default_status'.$extension.'.gif';
		}

		if(empty($status_txt) || !isset($status_txt))
		{
			$status_txt = $id_status;
		}

		return array('ID'=> $id_status, 'LABEL'=> $status_txt, 'IMG_SRC' => $img_path);
	}*/

	public function can_be_modified($id_status)
	{
		$this->connect();
		$this->query("select can_be_modified from ".$_SESSION['tablename']['status']." where id = '".$id_status."'");
		if($this->nb_result() == 0)
		{
			return false;
		}
		$res = $this->fetch_object();
		if($res->can_be_modified == 'N')
		{
			return false;
		}
		return true;
	}
}
