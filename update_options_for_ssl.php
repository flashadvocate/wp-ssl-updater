<?php

/**
 * WP SSL Updater
 *
 * Very primitive script to update wp_options tables for
 * WordPress multisite environments.
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

        /**
         * check with the user to verify that the database
         * is actually the one they want
         */
        print "The database name you provided was: {$argv[1]}" . PHP_EOL;
        print "Are you sure you wish to continue? [y/n]: ";
        $confirm = trim(fgets(STDIN));

        /**
         * did they actually agree or just randomly type something
         */
        if ($confirm !== 'y') {
            print "Exiting now";
            exit(0);
        }

        try {

            /**
             * Let's use PDO because it's the right thing to do
             */
            $db = new PDO("mysql:host=localhost;dbname=". DB_NAME, DB_USER, DB_PASS);

            /**
             * Only looking for wp_options tables. Schemas are handy...
             */
            $query = "SELECT `table_name` FROM `information_schema`.`tables` WHERE `table_type` = 'base table' AND `table_name` LIKE 'wp_%_options'";
            $result = $db->query($query);
            $result->setFetchMode(PDO::FETCH_OBJ);
            $tables = $result->fetchAll();

            /**
             * if we don't find any tables, there's no point in continuing
             */
            if ($result->rowCount() < 1) {
                print "No wp_options tables were found. Exiting.";
                exit;
            }

            /**
             * keep track of what we're doing so we can roll it back if
             * something terrible were to happen...
             */
            $db->beginTransaction();

            /**
             * we're assuming here, but if we got this far
             * then the table most likely exists
             */
            $update_query = "UPDATE `wp_options` SET `option_value` = REPLACE ( option_value, 'http://', 'https://' ) WHERE `option_name` = 'home' OR `option_name` = 'siteurl'";
            $result = $db->prepare($update_query);
            $result->execute();

            foreach ($tables as $table) {

                /**
                 * only updating home and siteurl options. We're also assuming
                 * that the resulting table_name is viable, but since we're
                 * pulling from information_schema, the information is fairly
                 * reliable anyway.
                 */
                $update_query = "UPDATE {$table->table_name} SET option_value = REPLACE ( option_value, \"http://\", \"https://\" ) WHERE option_name = \"home\" OR option_name = \"siteurl\"";
                $result = $db->prepare($update_query);
                $result->execute();

                /**
                 * some nice user feedback
                 */
                print "Updating: " . $table->table_name . PHP_EOL;
                print $result->rowCount() . " row(s) affected". PHP_EOL;

                /**
                 * give it time to breathe...
                 */
                sleep(1);
            }

            /**
             * our job is done here
             */
            print "All done. Yay!";
            exit;
        } catch (PDOException $e) {

            /**
        	 * something terrible happened, so any "s"'s we
        	 * added are now gone. Woe.
        	 */
            print $e->getMessage();
            $db->rollBack();
            echo "An error occurred. No changes were made.";
        }
    } else {

        /**
    	 * the user didn't provide a parameter to use
    	 */
        print "Error. Usage: php update_options_for_ssl.php databasename";
        exit;
    }
}
