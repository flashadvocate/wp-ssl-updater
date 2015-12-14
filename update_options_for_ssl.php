<?php

/**
 * WP SSL Updater
 *
 * Very primitive script to update wp_options tables for
 * WordPress multisite environments.
 * 
 * For reference, this script's results can also be achieved in PMA
 * via  few extra steps if one doesn't have access to CLI.
 * 
 * 1. Use query in search_global definition to find all
 * wp_options tables via information schema.
 * 
 * 2. Export resultset to CSV - Microsoft Excel
 * 
 * 3. Paste into Sublime Text and perform REGEXP search and replace (CTRL+H)
 * 
 * Search for: (.*);"(.*)"
 * Replace with: UPDATE $2.$1 SET option_value = REPLACE ( option_value, 'http://', 'https://' ) WHERE option_name = 'home' OR option_name = 'siteurl';
 * 
 * Use resulting query in PMA SQL query form
 */

require_once __DIR__ . '/credentials.php';

/**
 * We only want CLI interaction
 */
if (PHP_SAPI === 'cli') {

    /**
     * we need a proper database name
     */
    if (count($argv) > 1) {

        $dbname = trim($argv[1]);

        /**
         * alert the user that we are looking at all databases (looking at information schema
         * is non-specific, so we'll be safe in the fact that we are only looking
         * for wp_options tables)
         */
        print "Search all databases (including the one you provided) for wp_options tables? [y/n]: ";
        $search_global = trim(fgets(STDIN));

        /**
         * check with the user to verify that the database
         * is actually the one they want
         */
        print "The database name you provided was: {$dbname}" . PHP_EOL;
        print "Are you sure you wish to continue? [y/n]: ";
        $confirm = trim(fgets(STDIN));

        /**
         * did they actually agree or just randomly type something
         */
        if ($confirm !== 'y') {
            print "Exiting now";
            exit;
        }

        try {

            /**
             * Create database connection
             */
            $db = new PDO("mysql:host=". DB_HOST. ";dbname=". $dbname, DB_USER, DB_PASS);

            /**
             * Only looking for wp_options tables. Schemas are handy...
             */

            if ($search_global !== 'y') {
                $query = "SELECT `table_name`, `table_schema` FROM `information_schema`.`tables` WHERE `table_schema` = '{$dbname}' AND `table_type` = 'base table' AND `table_name` LIKE 'wp_%_options' OR `table_name` = 'wp_options'";
            } else {
                $query = "SELECT `table_name`, `table_schema` FROM `information_schema`.`tables` WHERE `table_type` = 'base table' AND `table_name` LIKE 'wp_%_options' OR `table_name` = 'wp_options'";
            }

            $result = $db->query($query);
            $result->setFetchMode(PDO::FETCH_OBJ);
            $tables = $result->fetchAll();

            /**
             * if we don't find any tables, stop here
             */
            if ($result->rowCount() < 1) {
                print "No wp_options tables were found. Exiting." . PHP_EOL;
                exit;
            }

            /**
             * keep track of what we're doing so we can roll it back
             */
            $db->beginTransaction();

            foreach ($tables as $table) {

                /**
                 * only updating home and siteurl options. We're also assuming
                 * that the resulting table_name is viable, but since we're
                 * pulling from information_schema, the information is fairly
                 * reliable anyway
                 */
                if ($search_global !== 'y') {
                    $update_query = "UPDATE {$dbname}.{$table->table_name} SET option_value = REPLACE ( option_value, 'http://', 'https://' ) WHERE option_name = 'home' OR option_name = 'siteurl'";
                } else {
                    $update_query = "UPDATE {$table->table_schema}.{$table->table_name} SET option_value = REPLACE ( option_value, 'http://', 'https://' ) WHERE option_name = 'home' OR option_name = 'siteurl'";
                }

                $result = $db->prepare($update_query);
                $result->execute();

                /**
                 * inform the user what tables are being affected
                 */
                print "Updating: " . $table->table_name . " in db " . $table->table_schema . PHP_EOL;
                print $result->rowCount() . " row(s) affected". PHP_EOL;

                /**
                 * give it time to breathe...
                 */
                sleep(1);
            }

            /**
             * commit the transaction
             */
            $db->commit();

            /**
             * our job is done here
             */
            print "All done. Exiting." . PHP_EOL;
            exit;
        } catch (PDOException $e) {

            /**
        	 * something bad happened
        	 */
            print $e->getMessage() . PHP_EOL;
            echo "An error occurred. No changes were made." . PHP_EOL;

            /**
             * if we made any changes, let's revert them
             */
            if (is_object($db) && $db->rowCount() > 0) {
                $db->rollBack();
            }
            exit;
        }
    } else {

        /**
    	 * the user didn't provide a parameter to use
    	 */
        print "Error. Usage: php update_options_for_ssl.php databasename";
        exit;
    }
}
