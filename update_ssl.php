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

if (!(isset($_POST['token']) && $_POST['token'] === SLACK_TOKEN)) {
    exit('Token failure.');
}

/**
 * Verify that a parameter was provided
 */

if (!isset($_POST['text'])) {
    exit('A database name must be provided. Ex. /update-blogs-ssl wordpress');
}

try {

    require_once __DIR__ . '/credentials.php';

    /**
     * Create database connection
     */
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . $dbname, DB_USER, DB_PASS);

    /**
     * Only looking for wp_options tables. Schemas are handy...
     */
    $query = "SELECT `table_name`, `table_schema` FROM `information_schema`.`tables` WHERE `table_type` = 'base table' AND `table_name` LIKE 'wp_%_options' OR `table_name` = 'wp_options'";

    $result = $db->query($query);
    $result->setFetchMode(PDO::FETCH_OBJ);
    $tables = $result->fetchAll();

    /**
     * if we don't find any tables, stop here
     */
    if ($result->rowCount() < 1) {
        exit('No wp_options tables were found. Something is very wrong!');
    }

    /**
     * keep track of what we're doing so we can roll it back
     */
    $db->beginTransaction();

    $counter = 0;

    foreach ($tables as $table) {

        /**
         * only updating home and siteurl options. We're also assuming
         * that the resulting table_name is viable, but since we're
         * pulling from information_schema, the information is fairly
         * reliable anyway
         */

        $update_query = "UPDATE {$table->table_schema}.{$table->table_name} SET option_value = REPLACE ( option_value, 'http://', 'https://' ) WHERE option_name = 'home' OR option_name = 'siteurl'";

        $result = $db->prepare($update_query);
        $result->execute();

        if ($result->rowCount()) {
            $counter++;
        }

    }

    /**
     * commit the transaction
     */
    $db->commit();

    /**
     * our job is done here
     */
    exit($counter . 'rows updated!');

} catch (PDOException $e) {

    /**
     * if something bad happens, rollback
     */

    if (is_object($db) && $db->rowCount() > 0) {
        $db->rollBack();
    }

    echo $e->getMessage() . PHP_EOL;
    echo "An error occurred. No changes were made." . PHP_EOL;

    exit;
}
