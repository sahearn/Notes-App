# Notes-App
Personal notes app with API

## Background
There are a thousand of these custom implementations online, but I was curious and bit bored.  Nothing groundbreaking here. It's a fairly basic personal notes app, with an additional API for example usage. (Currently only with GET method implemented.) I originally made it for myself, so is a single-user app. 

## What You'll Need
- a web host
- PHP
- MySQL

## My Approach
I started with inspiration and core code from fundaofwebit.com's ["PHP Ajax CRUD with Bootstrap Modal without page reload"](https://www.fundaofwebit.com/post/php-ajax-crud-operations-without-page-reload-using-jQuery-Ajax-mySQL), and cleaned it up to better adhere to PHP and MySQL security and best practices. I also made some corrections for W3C validation. As the referenced article suggests, this uses Bootstrap and jQuery for modal operations.

## Installation
1. Create a database table "notes" with 5 columns:
   - id (int, auto-increment)
   - title (text, nullable)
   - note (text)
   - dt_created (datetime)
   - dt_updated (datetime)
2. Generate a random encryption key using PHP Sodium, and store it in a separate file:
   ```// Generate a random 256-bit key (store this securely!)
$key = sodium_crypto_secretbox_keygen();
// Encode for display/storage
echo "key: " . bin2hex($key) . PHP_EOL; // hex — 64 printable chars```
2. Edit `dbutil.php`
   - add mysql connection details
