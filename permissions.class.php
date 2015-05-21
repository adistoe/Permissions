<?php
/*
* Classname: Permissions
* Author: adistoe
* Website: www.adistoe.ch
* Version: 1.03
* Last Update: Thursday, 21 May 2015
* 
* 
* Copyright by adistoe | All rights reserved.
* 
* ======================================================================================================================================
*
* Simple class to manage rights with usergroups.
* Permissions format examples:
* 
* admin.login		| This is a permission
* admin.*			| This is a "superpermission" -> Grants every permission with admin.something
* 
* *					| This is the superpermission for ALL permissions!
* 
* Example for a newssystem:
* 
* news.read			| Let the group read the news
* news.write		| Let the group write news
* news.edit			| Let the group edit some news
* news.delete		| Let the group delete some news
* news.*			| Let the user read, write, edit, delete the news. Use this instead of news.read, news.write, news.edit, news.delete
* 
*/

class Permissions{
	//Constructor
	function __construct($uid, $mysqli){
		$this->UID = $uid;
		$this->Database = $mysqli;
	}
	
	//Add user to group
	function AddGroup($name){
		$name = $this->Database->real_escape_string(htmlspecialchars($name));
		$this->Database->query("INSERT INTO groups(name) VALUES('".$name."')");
		return true;
	}
	
	//Remove user from group
	function DeleteGroup($gid){
		$gid = $this->Database->real_escape_string($gid);
		$this->Database->query("DELETE FROM user_groups WHERE GID = ".$gid);
		$this->Database->query("DELETE FROM group_permissions WHERE GID = ".$gid);
		$this->Database->query("DELETE FROM groups WHERE GID = ".$gid);
		return true;
	}
	
	//Add new permission
	function AddPermission($name, $description){
		$name = $this->Database->real_escape_string(htmlspecialchars($name));
		$description = $this->Database->real_escape_string(htmlspecialchars($description));
		$this->Database->query("INSERT INTO permissions(name, description) VALUES('".$name."','".$description."')");
		return true;
	}
	
	//Delete permission
	function DeletePermission($pid){
		$gid = $this->Database->real_escape_string($pid);
		$this->Database->query("DELETE FROM group_permissions WHERE PID = ".$pid);
		$this->Database->query("DELETE FROM permissions WHERE PID = ".$pid);
		return true;
	}
	
	//Check permission & superpermission (*)
	function HasPermission($permission){
		$qrycheck = $this->Database->query("SELECT * FROM user_groups AS ug
											JOIN groups AS g ON ug.GID = g.GID
											JOIN group_permissions AS gp ON g.GID = gp.GID
											JOIN permissions AS p ON gp.PID = p.PID
											WHERE ug.UID = ".$this->UID);
											
		while($check = $qrycheck->fetch_object()){
			if (preg_match('/^'.str_replace('*', '', $check->name).'/', $permission) == true){
				return true;
			}
		}
		return false;
	}
	
	//Grant permission to group
	function GrantPermission($gid, $permission){
		$permission = $this->Database->real_escape_string($permission);
		$gid = $this->Database->real_escape_string($gid);
		$qrycheck = $this->Database->query("SELECT * FROM group_permissions WHERE GID = ".$gid." AND PID = ".$permission);
		$check = $qrycheck->num_rows;
		
		//Deny double grant
		if ($check == 0){
			$this->Database->query("INSERT INTO group_permissions(GID,PID) VALUES(".$gid.",".$permission.")");
			return true;
		}
		else{
			return false;
		}
	}
	
	//Revoke permission from group
	function RevokePermission($gid, $permission){
		$gid = $this->Database->real_escape_string($gid);
		$this->Database->query("DELETE FROM group_permissions WHERE GID = ".$gid." AND PID = ".$permission);
		return true;
	}
}
?>