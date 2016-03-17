<?php

/**
 * Return a properly formatted message for slack
 *
 * @param $message
 * @param $response_type
 * @return string
 */
function slack_response($message, $response_type = "ephemeral") {
    echo json_encode(array(
        "response_type" => $response_type,
        "text" => $message
    ));
}