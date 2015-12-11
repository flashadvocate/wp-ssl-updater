#WordPress SSL Updater Thingy
This is a very primitive script intended to be used to update specific options (siteurl and home) in a WordPress multisite configuration. Specifically, it looks for 'http://' values and makes them 'https://'. That's it.

###Usage
Intended only for CLI use. You will need a credentials file. An example file is included.

- Update the example file
```sh
vim credentials.ex.php
```
- Rename it
```sh
mv credentials.ex.php credentials.php
```
- Run the script:
```sh
php update_options_for_ssl databaseNameHere
```
