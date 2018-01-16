# Permissions

## Getting started
Simply copy the permissions.class.php into your project folder and include the file into your project.
After you created a new Permissions-object you are ready!

```php
require 'path/to/class/permissions.class.php';

$pdo = new PDO(...);
$permissions = new Permissions($pdo, $userID);
```

To use the permissions class you need a [PDO](http://php.net/manual/en/pdo.connections.php) connection to communicate with your database.

The ```$userID``` is simply the ID of the user of your usertable.
You can simply fill in ```0``` if you don't have an ID to use the functions without being logged in for example. If you do so, you need to pass an ID directly to the functions on call.

## Configuration
You can change the table prefix and suffix to use your own naming style.

```php
private $prefix = 'example_';
private $suffix = '_table';
```

## Usage

### Superpermission

As first permission to add you should always use '*' as your superpermission.
Superpermission means that the user to which the permission was granted has ALL permissions.
Some functions also use the superpermission to check if a user is a "superuser" or if a group is a "supergroup". Read more to the supers later in this document...

### Check permission

#### Single permission
You can check if the user has a permission using ```hasPermission()```.

```php
if ($permissions->hasPermission('your.permissions.string')) {
    // Got the permission
} else {
    // Doesn't have the permission
}
```

 If you want to check the permission for another user than the one of which you've given the ID or if you haven't given an ID you can pass the ID directly to the function.

```php
$permissions->hasPermission('your.permissions.string', $userID)) {...}
```

#### Multiple permissions
You can check if the user has multiple permissions using ```hasPermissions()```.
You can pass one permissionstring per parameter and you can use as much parameters as you need.

```php
if ($permissions->hasPermissions('your.permissions.string.1', 'your.permissions.string.2', 'your.permissions.string.3')) {
    // Got the permission
} else {
    // Doesn't have the permission
}
```

#### Single or multiple permissions from a selection
You can check if the user has at least one permission from a selection using ```hasPermissionFromSelection()```.

```php
if ($permissions->hasPermissions('your.permissions.string.1', 'your.permissions.string.2', 'your.permissions.string.3')) {
    // Got the permission
} else {
    // Doesn't have the permission
}
```

##### To be continued...
