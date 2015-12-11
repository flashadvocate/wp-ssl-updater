<?php

/**
 * WP SSL Updater
 *
 * Very primitive script to update wp_options tables for
 * WordPress multisite environments.
 */

require_once __DIR__ . '/credentials.php';

// require cli execution
if (PHP_SAPI === 'cli') {

    // ensure an argument was provided
    if (count($argv) > 1) {

        // verify database name
        print "The database name you provided was: {$argv[1]}" . PHP_EOL;
        print "Are you sure you wish to continue? [y/n]: ";

        // get response from user
        $confirm = trim(fgets(STDIN));

        if ($confirm !== 'y') {
            print "Exiting now";
            exit(0);
        }

        try {

            // database connection
            $db = new PDO("mysql:host=localhost;dbname=". DB_NAME, DB_USER, DB_PASS);

            // pick all wp_*_options tables
            $query = "SELECT `table_name` FROM `information_schema`.`tables` WHERE `table_type` = 'base table' AND `table_name` LIKE 'wp_%_options'";
            $result = $db->query($query);
            $result->setFetchMode(PDO::FETCH_OBJ);
            $tables = $result->fetchAll();

            // did we find any relevant tables?
            if ($result->rowCount() < 1) {
                print "No wp_options tables were found. Exiting.";
                exit;
            }

            foreach ($tables as $table) {

                // only updating home and siteurl options
                $update_query = "UPDATE {$table->table_name} SET option_value = REPLACE ( option_value, \"http://\", \"https://\" ) WHERE option_name = \"home\" OR option_name = \"siteurl\"";
                $result = $db->prepare($update_query);
                $result->execute();

                print "Updating: " . $table->table_name . PHP_EOL;
                print $result->rowCount() . " row(s) affected". PHP_EOL;

                // give it time to breathe
                sleep(1);
            }

            print "All done. Yay!";
            exit;

        } catch (PDOException $e) {
            print $e->getMessage();
        }
    } else {
        print "Error. Usage: php update_options_for_ssl.php databasename";
        exit;
    }
}
