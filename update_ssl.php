<?php

header('Content-Type: application/json');

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
require_once __DIR__ . '/functions.php';

if (!(isset($_POST['token']) && $_POST['token'] === SLACK_TOKEN)) {
    slack_response('Token failure! Either this request didn\'t come from Slack, or the token provided to the script doesn\'t match the one provided by Slack');
    exit();
}

try {


    /**
     * Create database connection
     */
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=wordpress", DB_USER, DB_PASS);

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
        slack_response('No wp_options tables were found. Something is very wrong! :(');
        exit();
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
    slack_response($counter . ' rows updated on web00wpb.unity.ncsu.edu! :D', 'in_channel');
    exit();

} catch (PDOException $e) {

    /**
     * if something bad happens, rollback
     */

    if (is_object($db) && $db->rowCount() > 0) {
        $db->rollBack();
    }

    slack_response($e->getMessage());
    slack_response('An error occurred. No changes were made.');
    exit;
}
