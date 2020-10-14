<?php
$filename = "/app/plex-data/data.js";

if (file_exists($filename)) {
    echo json_encode(array(
        'filedate' => filemtime($filename)
    )); 
}
?>
