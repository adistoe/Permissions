<?php
/*
* Classname: Permissions
* Author: adistoe
* Website: http://www.adistoe.ch
* Version: 1.06
* Last Update: Tuesday, 07 February 2017
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
	function AddGroup($uid, $gid){
		$uid = $this->Database->real_escape_string($uid);
		$gid = $this->Database->real_escape_string($gid);
		if ($this->Database->query("INSERT INTO user_groups(UID, GID) VALUES($uid,$gid)")){
			return true;
		}
		else{
			return false;
		}
	}
	
	//Create group
	function CreateGroup($name){
		$name = $this->Database->real_escape_string(htmlspecialchars($name));
		$qrycheck = $this->Database->query("SELECT * FROM groups WHERE name = '$name'");
		$check = $qrycheck->num_rows;
		
		//Deny double create
		if ($check == 0){
			if ($this->Database->query("INSERT INTO groups(name) VALUES('$name')")){
				return true;
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}
	
	//Create permission
	function CreatePermission($name, $description){
		//Deny from empty creation
		if (strlen($name) == 0 || strlen($description) == 0){
			return false;
		}
		
		$name = $this->Database->real_escape_string($name);
		$description = $this->Database->real_escape_string(htmlspecialchars($description));
		$qrycheck = $this->Database->query("SELECT * FROM permissions WHERE name = '$name'");
		$check = $qrycheck->num_rows;
		
		//Deny double create
		if ($check == 0){
			if ($this->Database->query("INSERT INTO permissions(name, description) VALUES('$name','$description')")){
				return true;
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}
	
	//Delete group
	function DeleteGroup($gid){
		$gid = $this->Database->real_escape_string($gid);
		$qrycheck = $this->Database->query("SELECT * FROM groups WHERE GID = $gid");
		$check = $qrycheck->num_rows;
		
		if ($check > 0){
			if (!$this->Database->query("DELETE FROM user_groups WHERE GID = $gid")){
				return false;
			}
			if (!$this->Database->query("DELETE FROM group_permissions WHERE GID = $gid")){
				return false;
			}
			if (!$this->Database->query("DELETE FROM groups WHERE GID = $gid")){
				return false;
			}
			return true;
		}
		else{
			return false;
		}
	}
	
	//Delete permission
	function DeletePermission($pid){
		$gid = $this->Database->real_escape_string($pid);
		if (!$this->Database->query("DELETE FROM group_permissions WHERE PID = $pid")){
			return false;
		}
		if (!$this->Database->query("DELETE FROM permissions WHERE PID = $pid")){
			return false;
		}
		return true;
	}
	
	//Grant permission to group
	function GrantPermission($gid, $permission){
		$permission = $this->Database->real_escape_string($permission);
		$gid = $this->Database->real_escape_string($gid);
		$qrycheck = $this->Database->query("SELECT * FROM group_permissions WHERE GID = $gid AND PID = $permission");
		$check = $qrycheck->num_rows;
		
		//Deny double grant
		if ($check == 0){
			if ($this->Database->query("INSERT INTO group_permissions(GID,PID) VALUES($gid,$permission)")){
				return true;
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}
	
	//Check permission & superpermission (*)
	function HasPermission($permission){
		$qrycheck = $this->Database->query("SELECT * FROM user_groups AS ug
											JOIN groups AS g ON ug.GID = g.GID
											JOIN group_permissions AS gp ON g.GID = gp.GID
											JOIN permissions AS p ON gp.PID = p.PID
											WHERE ug.UID = $this->UID");
											
		while($check = $qrycheck->fetch_object()){
			if (preg_match('/^'.str_replace('*', '', $check->name).'/', $permission) == true){
				return true;
			}
		}
		return false;
	}
	
	//Remove user from group
	function RevokeGroup($uid, $gid){
		$uid = $this->Database->real_escape_string($uid);
		$gid = $this->Database->real_escape_string($gid);
		if ($this->Database->query("DELETE FROM user_groups WHERE UID = $uid AND GID = $gid")){
			return true;
		}
		else{
			return false;
		}
	}
	
	//Revoke permission from group
	function RevokePermission($gid, $permission){
		$gid = $this->Database->real_escape_string($gid);
		$permission = $this->Database->real_escape_string($permission);
		if ($this->Database->query("DELETE FROM group_permissions WHERE GID = $gid AND PID = $permission")){
			return true;
		}
		else{
			return false;
		}
	}
}
?>