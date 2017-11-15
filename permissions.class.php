<?php
/**
 * Classname: Permissions
 * Author: adistoe
 * Website: https://www.adistoe.ch
 * Version: 1.2.5
 * Creation date: Wednesday, 11 February 2015
 * Last Update: Wednesday, 15 November 2017
 * Description:
 *    Permissions is a simple class to manage user rights with groups.
 *
 * Copyright by adistoe | All rights reserved.
 */
class Permissions
{
    private $uid;
    private $db;

    // Database tables - Can be renamed to use in own database
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
     * Get group by ID
     *
     * @param int $gid ID of the group to get
     *
     * @return string[] Returns the group
     */
    public function getGroup($gid) {
        $stmt = $this->db->prepare("
            SELECT
                *
            FROM " . $this->tables['groups'] . "
            WHERE
                GID = :GID
        ");

        $stmt->bindParam(':GID', $gid);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row;
        }

        return false;
    }

    /**
     * Get permissions which are not granted to the given group
     *
     * @param int $gid ID of the group to get the missing permissions from
     *
     * @return string[] Returns permissions which the group does not have
     */
    public function getGroupMissingPermissions($gid) {
        $groupPermissions = $this->getGroupPermissions($gid);
        $permissions = $this->getPermissions();

        foreach($permissions as $key => $permission) {
            if (array_key_exists($key, $groupPermissions)) {
                unset($permissions[$key]);
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
                JOIN ' . $this->tables['group_permissions'] . ' AS gp
                    ON g.GID = gp.GID
                JOIN ' . $this->tables['permissions'] . ' AS p
                    ON gp.PID = p.PID
            WHERE g.GID = :GID
        ');

        $stmt->bindParam(':GID', $gid);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['PID']] = $row;
        }

        return $permissions;
    }

    /**
     * Get groups
     *
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all groups
     */
    public function getGroups($orderColumn = 'GID', $orderDirection = 'ASC') {
            $groups = Array();

            foreach (
                $this->db->query("
                    SELECT
                        *
                    FROM " . $this->tables['groups'] . "
                    ORDER BY $orderColumn $orderDirection",
                    PDO::FETCH_ASSOC
                ) as $row
            ) {
                $groups[$row['GID']] = $row;
            }

            return $groups;
    }

    /**
     * Get permission by id
     *
     * @return string[] Returns the given permission
     */
    public function getPermission($pid) {
            $permission = Array();

            $stmt = $this->db->prepare('
                SELECT
                    *
                FROM ' . $this->tables['permissions'] . '
                WHERE
                    PID = :PID
            ');

            $stmt->bindParam(':PID', $pid);
            $stmt->execute();

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $row;
            }

            return false;
    }

    /**
     * Get permissions
     *
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all permissions
     */
    public function getPermissions(
        $orderColumn = 'PID',
        $orderDirection = 'ASC'
    ) {
            $permissions = Array();

            foreach (
                $this->db->query("
                    SELECT
                        *
                    FROM " . $this->tables['permissions'] . "
                    ORDER BY $orderColumn $orderDirection",
                    PDO::FETCH_ASSOC
                ) as $row
            ) {
                $permissions[$row['PID']] = $row;
            }

            return $permissions;
    }

    /**
     * Get all permissions to which the user has access to
     *
     * @param int $uid ID of the user to get the permissions from
     *
     * @return string[] Returns all permissions to which the user has access to
     */
    public function getUserAccessPermissions($uid = 0) {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        $permissions = $this->getPermissions();
        $userAccessPermissions = Array();

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission['name'], $uid)) {
                $userAccessPermissions[$permission['PID']] = $permission;
            }
        }

        return $userAccessPermissions;
    }

    /**
     * Get user groups
     *
     * @param int $uid ID of the user to get the groups from
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all groups of the given user
     */
    public function getUserGroups(
        $uid,
        $orderColumn = 'g.GID',
        $orderDirection = 'ASC'
    ) {
        $groups = Array();
        $stmt = $this->db->prepare("
            SELECT
                g.GID,
                name
            FROM " . $this->tables['user_groups'] . " AS ug
                JOIN " . $this->tables['groups'] . " AS g
                    ON ug.GID = g.GID
            WHERE ug.UID = :UID
            ORDER BY $orderColumn $orderDirection
        ");

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $groups[$row['GID']] = $row;
        }

        return $groups;
    }

    /**
     * Get groups to which the user is not associated to
     *
     * @param int $uid ID of the user to get the missing groups from
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all groups to which the user
     *    is not associated to
     */
    public function getUserMissingGroups(
        $uid,
        $orderColumn = 'GID',
        $orderDirection = 'ASC'
    ) {
        $userGroups = $this->getUserGroups($uid, $orderColumn, $orderDirection);
        $groups = $this->getGroups($orderColumn, $orderDirection);

        foreach($groups as $key => $group) {
            if (array_key_exists($key, $userGroups)) {
                unset($groups[$key]);
            }
        }

        return $groups;
    }

    /**
     * Get permissions to which the user is not associated to
     *
     * @param int $uid ID of the user to get the missing permissions from
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all permissions to which the user
     *    is not associated to
     */
    public function getUserMissingPermissions(
        $uid = 0,
        $orderColumn = 'PID',
        $orderDirection = 'ASC'
    ) {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        $userPermissions = $this->getUserPermissions(
            $uid,
            'p.' . $orderColumn,
            $orderDirection
        );
        $permissions = $this->getPermissions($orderColumn, $orderDirection);

        foreach($permissions as $key => $permission) {
            if (array_key_exists($key, $userPermissions)) {
                unset($permissions[$key]);
            }
        }

        return $permissions;
    }

    /**
     * Get user permissions
     *
     * @param int $uid ID of the user to get the permissions from
     * @param string $orderColumn Order results by given column
     * @param string $orderDirection Order results in given direction
     *
     * @return string[] Returns all permissions of the given user
     */
    public function getUserPermissions(
        $uid = 0,
        $orderColumn = 'p.PID',
        $orderDirection = 'ASC'
    ) {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        $permissions = Array();
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                p.PID,
                name,
                description
            FROM " . $this->tables['user_groups'] . " AS ug
                JOIN " . $this->tables['group_permissions'] . " AS gp
                    ON ug.GID = gp.GID
                JOIN " . $this->tables['permissions'] . " AS p
                    ON gp.PID = p.PID
            WHERE ug.UID = :UID
            ORDER BY $orderColumn $orderDirection
        ");

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['PID']] = $row;
        }

        return $permissions;
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
            $stmt = $this->db->prepare('
                SELECT
                    GID
                FROM ' . $this->tables['groups'] . '
                WHERE
                    name = :name
                ');

            $stmt->bindParam(':name', $name);
            $stmt->execute();

            // Check if the group already exists
            if ($row = $stmt->fetchObject()) {
                return false;
            }

            $stmt = $this->db->prepare('
                INSERT INTO ' . $this->tables['groups'] . '(
                    name
                ) VALUES(
                    :name
                )
            ');

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
                LEFT JOIN ' . $this->tables['user_groups'] . ' AS ug
                    ON g.GID = ug.GID
                LEFT JOIN ' . $this->tables['group_permissions'] . ' AS gp
                    ON g.GID = gp.GID
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

        $stmt = $this->db->prepare('
            SELECT
                GID
            FROM ' . $this->tables['groups'] . '
            WHERE
                GID <> :GID AND
                name = :name
            ');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        // Check if the group already exists
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('
            UPDATE ' . $this->tables['groups'] . ' SET
                name = :name
            WHERE
                GID = :GID
        ');

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
        $stmt = $this->db->prepare('
            SELECT
                PID
            FROM ' . $this->tables['group_permissions'] . '
            WHERE
                GID = :GID AND
                PID = :PID
            ');

        $stmt->bindParam(':GID', $gid);
        $stmt->bindParam(':PID', $pid);
        $stmt->execute();

        // Check if the group already has the permission
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('
            INSERT INTO ' . $this->tables['group_permissions'] . '(
                GID,
                PID
            ) VALUES(
                :GID,
                :PID
            )
        ');

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
        $stmt = $this->db->prepare('
            DELETE FROM ' . $this->tables ['group_permissions']. '
            WHERE
                GID = :GID AND
                PID = :PID
            ');

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
     * Check if the user has a permission
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
                JOIN ' . $this->tables['groups'] . ' AS g
                    ON ug.GID = g.GID
                JOIN ' . $this->tables['group_permissions'] . ' AS gp
                    ON g.GID = gp.GID
                JOIN ' . $this->tables['permissions'] . ' AS p
                    ON gp.PID = p.PID
            WHERE ug.UID = :UID
        ');

        $stmt->bindParam(':UID', $uid);
        $stmt->execute();

        while ($row = $stmt->fetchObject()) {
            if (
                preg_match(
                    '/^' .
                    str_replace(
                        '*',
                        '.*',
                        str_replace(
                            '.',
                            '\.',
                            $row->name
                        )
                    ) . '$/',
                    $name
                ) == true
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has at least one of the given permissions
     *
     * @return boolean Returns if the user has at least one of the permissions
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
     * Check if the user has some specific permissions
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
     * Check if the user is in the given group
     *
     * @param int $gid ID of the group to check
     * @param int $uid ID of the user to check
     *
     * @return boolean Returns if the user is in the given group
     */
    public function isInGroup($gid, $uid = 0)
    {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        $groups = $this->getUserGroups($uid);

        if (array_key_exists($gid, $groups)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the group is a supergroup (superpermission)
     *
     * @param int $gid ID of the group to check
     *
     * @return boolean Returns if the group is a supergroup
     */
    public function isSupergroup($gid)
    {
        $permissions = $this->getGroupPermissions($gid);

        foreach ($permissions as $permission) {
            if ($permission['name'] == '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the permission is the superpermission
     *
     * @param int $pid ID of the permission to check
     *
     * @return boolean Returns if it's the superpermission
     */
    public function isSuperpermission($pid)
    {
        $permissions = $this->getPermissions();

        if ($permissions[$pid]['name'] == '*') {
            return true;
        }

        return false;
    }

    /**
     * Check if the user is a superuser (superpermission)
     *
     * @param int $uid ID of the user to check
     *
     * @return boolean Returns if the user is a superuser
     */
    public function isSuperuser($uid = 0)
    {
        if ($uid == 0) {
            $uid = $this->UID;
        }

        if ($this->hasPermission('*', $uid)) {
            return true;
        }

        return false;
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
            $stmt = $this->db->prepare('
                SELECT
                    *
                FROM ' . $this->tables['permissions'] . '
                WHERE
                    name = :name
            ');

            $stmt->bindParam(':name', $name);
            $stmt->execute();

            // Check if the permission already exists
            if ($row = $stmt->fetchObject()) {
                return false;
            }

            $stmt = $this->db->prepare('
                INSERT INTO ' . $this->tables['permissions'] . '(
                    name,
                    description
                ) VALUES(
                    :name,
                    :description
                )
            ');

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
                LEFT JOIN ' . $this->tables['group_permissions'] . ' AS gp
                    ON p.PID = gp.PID
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

        $stmt = $this->db->prepare('
            SELECT
                GID
            FROM ' . $this->tables['permissions'] . '
            WHERE
                PID <> :PID AND
                name = :name
            ');

        $stmt->bindParam(':PID', $pid);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        // Check if the group already exists
        if ($row = $stmt->fetchObject()) {
            return false;
        }

        $stmt = $this->db->prepare('
            UPDATE ' . $this->tables['permissions'] . ' SET
                name = :name,
                description = :description
            WHERE
                PID = :PID
        ');

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
        $stmt_user = $this->db->prepare('
            SELECT
                UID
            FROM ' . $this->tables['users'] . '
            WHERE
                UID = :UID
        ');

        $stmt_user->bindParam(':UID', $uid);
        $stmt_user->execute();

        // Get group with given ID
        $stmt_group = $this->db->prepare('
            SELECT
                GID FROM ' . $this->tables['groups'] . '
            WHERE
                GID = :GID
        ');

        $stmt_group->bindParam(':GID', $gid);
        $stmt_group->execute();

        // Check if given user and group exists
        if (
            $row_user = $stmt_user->fetchObject() &&
            $row_group = $stmt_group->fetchObject()
        ) {
            $stmt = $this->db->prepare('
                INSERT INTO ' . $this->tables['user_groups'] . '(
                    UID,
                    GID
                ) VALUES(
                    :UID,
                    :GID
                )
            ');

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
        $stmt = $this->db->prepare('
            DELETE FROM ' . $this->tables['user_groups'] . '
            WHERE
                UID = :UID AND
                GID = :GID
        ');

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
