<?php
/**
 * Classname: Permissions
 * Author: adistoe
 * Website: https://www.adistoe.ch
 * Version: 1.2.1
 * Creation date: Wednesday, 11 February 2015
 * Last Update: Thursday, 5 October 2017
 * Description: Permissions is a simple class to manage user rights with groups.
 *
 * Copyright by adistoe | All rights reserved.
 */
class Permissions
{
    private $uid;
    private $db;

    // Set to true, if all in- and outputs should be encoded / decoded with utf8
    private $utf8_conversion = true;

    // Database tables - Can be renamed (Must be the same as the tables in the database!)
    private $tables = Array(
        'group_permissions' => 'group_permissions',
        'groups'            => 'groups',
        'permissions'       => 'permissions',
        'user_groups'       => 'user_groups',
        'users'             => 'user'
    );

    /**
     * Constructor
     * Initializes the class
     *
     * @param int $uid ID of the user to handle
     * @param object $pdo Database object
     */
    public function __construct($pdo, $uid)
    {
        $this->db = $pdo;
        $this->UID = $uid;
    }

    /**
     * Get permissions which are not granted to the given group
     *
     * @param int $gid ID of the group to get the missing permissions from
     *
     * @return string[] Returns all permissions which are not granted to the given group
     */
    public function getGroupMissingPermissions($gid) {
        $permissions = Array();
        $stmt = $this->db->prepare('
            SELECT
                p.PID,
                p.name,
                description
            FROM ' . $this->tables['permissions'] . ' AS p
                LEFT JOIN ' . $this->tables['group_permissions'] . ' AS gp ON p.PID = gp.PID
                LEFT JOIN ' . $this->tables['groups'] . ' AS g ON gp.GID = g.GID
            WHERE g.GID <> :GID OR g.GID IS NULL
        ');

        $stmt->bindParam(':GID', $gid);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $permissions[$row['PID']] = $row;

            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $permissions[$row['PID']]['name'] = utf8_encode($row['name']);
                $permissions[$row['PID']]['description'] = utf8_encode($row['description']);
            }
        }

        return $permissions;
    }

    /**
     * Get group permissions
     *
     * @param int $gid ID of the group to get the permissions from
     *
     * @return string[] Returns all permissions of the given group
     */
    public function getGroupPermissions($gid) {
        $permissions = Array();
        $stmt = $this->db->prepare('
            SELECT
                p.PID,
                p.name,
                description
            FROM ' . $this->tables['groups'] . ' AS g
                JOIN ' . $this->tables['group_permissions'] . ' AS gp ON g.GID = gp.GID
                JOIN ' . $this->tables['permissions'] . ' AS p ON gp.PID = p.PID
            WHERE g.GID = :GID
        ');

        $stmt->bindParam(':GID', $gid);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $permissions[$row['PID']] = $row;

            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $permissions[$row['PID']]['name'] = utf8_encode($row['name']);
                $permissions[$row['PID']]['description'] = utf8_encode($row['description']);
            }
        }

        return $permissions;
    }

    /**
     * Get groups
     *
     * @return string[] Returns all groups
     */
    public function getGroups() {
            $groups = Array();

            foreach ($this->db->query('SELECT * FROM ' . $this->tables['groups']) as $row) {
                $groups[$row['GID']] = $row;

                // Correct encoding where necessary
                if ($this->utf8_conversion) {
                    $groups[$row['GID']]['name'] = utf8_encode($row['name']);
                }
            }

            return $groups;
    }

    /**
     * Get permissions
     *
     * @return string[] Returns all permissions
     */
    public function getPermissions() {
            $permissions = Array();

            foreach ($this->db->query('SELECT * FROM ' . $this->tables['permissions']) as $row) {
                $permissions[$row['PID']] = $row;

                // Correct encoding where necessary
                if ($this->utf8_conversion) {
                    $permissions[$row['PID']]['name'] = utf8_encode($row['name']);
                    $permissions[$row['PID']]['description'] = utf8_encode($row['description']);
                }
            }

            return $permissions;
    }

    /**
     * Get user groups
     *
     * @param int $uid ID of the user to get the groups from
     *
     * @return string[] Returns all groups of the given user
     */
    public function getUserGroups($uid) {
        $groups = Array();
        $stmt = $this->db->prepare('
            SELECT
                g.GID,
                name
            FROM ' . $this->tables['user_groups'] . ' AS ug
                JOIN ' . $this->tables['groups'] . ' AS g ON ug.GID = g.GID
            WHERE ug.UID = :UID
        ');

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $groups[$row['GID']] = $row;

            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $groups[$row['GID']]['name'] = utf8_encode($row['name']);
            }
        }

        return $groups;
    }

    /**
     * Get groups to which the user is not associated to
     *
     * @param int $uid ID of the user to get the missing groups from
     *
     * @return string[] Returns all groups to which the user is not associated to
     */
    public function getUserMissingGroups($uid) {
        $groups = Array();
        $stmt = $this->db->prepare('
            SELECT
                g.GID,
                name
            FROM ' . $this->tables['groups'] . ' AS g
                LEFT JOIN ' . $this->tables['user_groups'] . ' AS ug ON g.GID = ug.GID
            WHERE ug.UID <> :UID OR ug.UID IS NULL
        ');

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $groups[$row['GID']] = $row;

            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $groups[$row['GID']]['name'] = utf8_encode($row['name']);
            }
        }

        return $groups;
    }

    /**
     * Create a group
     *
     * @param string $name Name for the new group
     *
     * @return boolean Returns if the group was created
     */
    public function groupCreate($name)
    {
        if (strlen($name) > 0) {
            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $name = utf8_decode($name);
            }

            $stmt = $this->db->prepare('SELECT GID FROM ' . $this->tables['groups'] . ' WHERE name = :name');

            $stmt->bindParam(':name', $name);
            $stmt->execute();

            // Check if the group already exists
            if ($row = $stmt->fetchObject()) {
                return false;
            }

            $stmt = $this->db->prepare('INSERT INTO ' . $this->tables['groups'] . '(name) VALUES(:name)');

            $stmt->bindParam(':name', $name);

            // Create the group
            if ($stmt->execute()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a group
     *
     * @param int $gid ID of the group to delete
     *
     * @return boolean Returns if the group was deleted
     */
    public function groupDelete($gid)
    {
        $stmt = $this->db->prepare('
            DELETE
                g,
                ug,
                gp
            FROM ' . $this->tables['groups'] . ' AS g
                LEFT JOIN ' . $this->tables['user_groups'] . ' AS ug ON g.GID = ug.GID
                LEFT JOIN ' . $this->tables['group_permissions'] . ' AS gp ON g.GID = gp.GID
            WHERE g.GID = :GID
        ');

        $stmt->bindParam(':GID', $gid);

        // Delete the group with all relations
        if ($stmt->execute()) {
            // Check if there was a deletion
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Edit a group
     *
     * @param int $gid ID of the group to edit
     * @param string $name Name of the group to edit
     *
     * @return boolean Returns if the group was edited
     */
    public function groupEdit($gid, $name)
    {
        if ($gid == '' || $name == '') {
            return false;
        }

        // Correct encoding where necessary
        if ($this->utf8_conversion) {
            $name = utf8_decode($name);
        }

        $stmt = $this->db->prepare('SELECT GID FROM ' . $this->tables['groups'] . ' WHERE GID <> :GID AND name = :name');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        // Check if the group already exists
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE ' . $this->tables['groups'] . ' SET name = :name WHERE GID = :GID');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':name', $name);

        // Edit the group
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    /**
     * Grant permission to a group
     *
     * @param int $gid ID of the group to grant the permission to
     * @param int $pid ID of the permission to grant
     *
     * @return boolean Returns if the permission was granted
     */
    public function groupPermissionGrant($gid, $pid)
    {
        $stmt = $this->db->prepare('SELECT PID FROM ' . $this->tables['group_permissions'] . ' WHERE GID = :GID AND PID = :PID');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':PID', $pid);
        $stmt->execute();

        // Check if the group already has the permission
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO ' . $this->tables['group_permissions'] . '(GID, PID) VALUES(:GID, :PID)');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':PID', $pid);

        // Create the permission
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    /**
     * Revoke permission from group
     *
     * @param int $gid ID of the group to revoke the permission from
     * @param int $pid ID of the permission to revoke from the group
     *
     * @return boolean Returns if the preission was revoked
     */
    public function groupPermissionRevoke($gid, $pid)
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->tables ['group_permissions']. ' WHERE GID = :GID AND PID = :PID');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':PID', $pid);

        // Revoke the permission
        if ($stmt->execute()) {
            // Check if there was a deletion
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user / group has a permission
     *
     * @param string $name Permission name to check
     * @param int $uid ID of the user to check, current user as default
     *
     * @return boolean Returns if the user has the permission
     */
    public function hasPermission($name, $uid = 0)
    {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        $stmt = $this->db->prepare('
            SELECT
                p.name
            FROM ' . $this->tables['user_groups'] . ' AS ug
                JOIN ' . $this->tables['groups'] . ' AS g ON ug.GID = g.GID
                JOIN ' . $this->tables['group_permissions'] . ' AS gp ON g.GID = gp.GID
                JOIN ' . $this->tables['permissions'] . ' AS p ON gp.PID = p.PID
            WHERE ug.UID = :UID
        ');

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetchObject()) {
            if (preg_match('/^' . str_replace('*', '.*', str_replace('.', '\.', $row->name)) . '$/', $name) == true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user / group has at least one of the given permissions
     *
     * @return boolean Returns if the user has at least one of the the permissions
     */
    public function hasPermissionFromSelection()
    {
        $args = func_get_args();

        if (count($args) > 0) {
            foreach ($args as $arg) {
                if ($this->hasPermission($arg)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user / group has some specific permissions
     *
     * @return boolean Returns if the user has the permission
     */
    public function hasPermissions()
    {
        $args = func_get_args();

        if (count($args) > 0) {
            foreach ($args as $arg) {
                if (!$this->hasPermission($arg)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a permission
     *
     * @param string $name Name for the new permission
     * @param string $description Description for the new permission
     *
     * @return boolean Returns if the permission was created
     */
    public function permissionCreate($name, $description)
    {
        if (strlen($name) > 0 && strlen($description) > 0) {
            // Correct encoding where necessary
            if ($this->utf8_conversion) {
                $name = utf8_decode($name);
                $description = utf8_decode($description);
            }

            $stmt = $this->db->prepare('SELECT * FROM ' . $this->tables['permissions'] . ' WHERE name = :name');

            $stmt->bindParam(':name', $name);
            $stmt->execute();

            // Check if the permission already exists
            if ($row = $stmt->fetchObject()) {
                return false;
            }

            $stmt = $this->db->prepare('INSERT INTO ' . $this->tables['permissions'] . '(name, description) VALUES(:name, :description)');

            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);

            // Create the permission
            if ($stmt->execute()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a permission
     *
     * @param int $pid ID of the permission to delete
     *
     * @return boolean Returns if the permission was deleted
     */
    public function permissionDelete($pid)
    {
        $stmt = $this->db->prepare('
            DELETE
                p,
                gp
            FROM ' . $this->tables['permissions'] . ' AS p
                LEFT JOIN ' . $this->tables['group_permissions'] . ' AS gp ON p.PID = gp.PID
            WHERE p.PID = :PID
        ');

        $stmt->bindParam(':PID', $pid);

        // Delete the permission with all relations
        if ($stmt->execute()) {
            // Check if there was a deletion
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Edit a permission
     *
     * @param int $pid ID of the permission to edit
     * @param string $name Name of the permission to edit
     * @param string $description Description of the permission to edit
     *
     * @return boolean Returns if the permission was edited
     */
    public function permissionEdit($pid, $name, $description)
    {
        if ($pid == '' || $name == '' || $description == '') {
            return false;
        }

        // Correct encoding where necessary
        if ($this->utf8_conversion) {
            $name = utf8_decode($name);
            $description = utf8_decode($description);
        }

        $stmt = $this->db->prepare('SELECT GID FROM ' . $this->tables['permissions'] . ' WHERE PID <> :PID AND name = :name');

        $stmt->bindParam(':PID', $pid);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        // Check if the group already exists
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE ' . $this->tables['permissions'] . ' SET name = :name, description = :description WHERE PID = :PID');

        $stmt->bindParam(':PID', $pid);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);

        // Edit the group
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    /**
     * Add a user to a group
     *
     * @param int $uid ID of the user to handle
     * @param int $gid ID of the group to add the user to
     *
     * @return boolean Returns if the user was added to the group
     */
    public function userGroupAdd($uid, $gid)
    {
        // Get user with given ID
        $stmt_user = $this->db->prepare('SELECT UID FROM ' . $this->tables['users'] . ' WHERE UID = :UID');

        $stmt_user->bindParam(':UID', $uid);
        $stmt_user->execute();

        // Get group with given ID
        $stmt_group = $this->db->prepare('SELECT GID FROM ' . $this->tables['groups'] . ' WHERE GID = :GID');

        $stmt_group->bindParam(':GID', $gid);
        $stmt_group->execute();

        // Check if given user and group exists
        if ($row_user = $stmt_user->fetchObject() && $row_group = $stmt_group->fetchObject()) {
            $stmt = $this->db->prepare('INSERT INTO ' . $this->tables['user_groups'] . '(UID, GID) VALUES(:UID, :GID)');

            $stmt->bindParam(':UID', $uid);
            $stmt->bindParam(':GID', $gid);

            // Add the group to the user
            if ($stmt->execute()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove group from user
     *
     * @param int $uid ID of the user to remove the group from
     * @param int $gid ID of the group to remove from the user
     *
     * @return boolean Returns if the user was removed from the group
     */
    public function userGroupRemove($uid, $gid)
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->tables['user_groups'] . ' WHERE UID = :UID AND GID = :GID');

        $stmt->bindParam(':UID', $uid);
        $stmt->bindParam(':GID', $gid);

        // Revoke group from user
        if ($stmt->execute()) {
            // Check if there was a deletion
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }

        return false;
    }
}
