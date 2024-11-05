# twrite
Simple service for publishing text posts. [Telegra.ph](https://telegra.ph/) analogue.

# Requirements
- PHP 7.2+
- MySQL 8.0
- Nginx\Apache 

# Settings

`texting.sql` - The structure of the table that you can import into your database.

In file index.php enter connect to database:
```php
$host = '';         // host 
$user = '';     // user 
$pass = '';      // password
$db_name = '';  // name 
```

In file index.php enter name project:
```php
$name_project = "twrite"; //name
```

# Details
1. All entries will be deleted after a year.
2. You can set the entry to be deleted after 24 hours.
3. You can set the entry to be closed with a password.
4. You can set access to the entry only by IP to the user who creates the entry.
5. You can edit entries after publication.
6. Normal text formatting is available.
7. You can insert images as links and they will be displayed in the entries.

---
The result will be something like this:

<img src="https://raw.githubusercontent.com/rorry47/twrite/refs/heads/main/twrite_pic.jpg">
