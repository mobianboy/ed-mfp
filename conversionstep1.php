<?php

// This is disabled by SDK on 09-27-2013 after successful conversion
exit;

$db = new PDO("mysql:dbname=alphawebdb;host=alpha-db.eardish.net", "alphawebdb", "GLLTtpr5dbKLL");

echo "Begining database prep...";

$creations['mfp_audio'] = $db->query("CREATE TABLE `mfp_audio_back` LIKE `mfp_audio`");
echo ".";
$creations['mfp_audio_formats'] = $db->query("CREATE TABLE `mfp_audio_formats_back` LIKE `mfp_audio_formats`");
echo ".";
$creations['mfp_images'] = $db->query("CREATE TABLE `mfp_images_back` LIKE `mfp_images`");
echo ".";
$creations['mfp_image_formats'] = $db->query("CREATE TABLE `mfp_image_formats_back` LIKE `mfp_image_formats`");
echo ".".PHP_EOL;

$errors1 = false;
foreach ($creations as $key => $val) {
    if (!$val) {
        echo "Error creating '".$key."_back'".PHP_EOL;
        $errors1 = true;
    }
}

echo PHP_EOL.PHP_EOL;

if ($errors1) {
    echo "Conversion failed in Step 1. Please investigate. Killing script.".PHP_EOL;
    exit;
}

echo "Begining data copy...";

$duplications['mfp_audio'] = $db->query("INSERT INTO `mfp_audio_back` SELECT * FROM `mfp_audio`");
echo ".";
$duplications['mfp_audio_formats'] = $db->query("INSERT INTO `mfp_audio_formats_back` SELECT * FROM `mfp_audio_formats`");
echo ".";
$duplications['mfp_images'] = $db->query("INSERT INTO `mfp_images_back` SELECT * FROM `mfp_images`");
echo ".";
$duplications['mfp_image_formats'] = $db->query("INSERT INTO `mfp_image_formats_back` SELECT * FROM `mfp_image_formats`");
echo ".".PHP_EOL;

$errors2 = false;
foreach ($duplications as $key => $val) {
    if (!$val) {
        echo "Error copying '".$key."'".PHP_EOL;
        $errors2 = true;
    }
}

echo PHP_EOL.PHP_EOL;

if ($errors2) {
    echo "Conversion failed in Step 1. Please investigate. Killing script.".PHP_EOL;
    exit;
}

echo "Dropping old tables...";

$drops['mfp_audio'] = $db->query("DROP TABLE `mfp_audio`");
echo ".";
$drops['mfp_audio_formats'] = $db->query("DROP TABLE `mfp_audio_formats`");
echo ".";
$drops['mfp_images'] = $db->query("DROP TABLE `mfp_images`");
echo ".";
$drops['mfp_image_formats'] = $db->query("DROP TABLE `mfp_image_formats`");
echo ".".PHP_EOL;

$errors3 = false;
foreach ($duplications as $key => $val) {
    if (!$val) {
        echo "Error dropping '".$key."'".PHP_EOL;
        $errors3 = true;
    }
}

echo PHP_EOL.PHP_EOL;

if ($errors3) {
    echo "Conversion failed in Step 1. Please investigate. Killing script.".PHP_EOL;
    exit;
}
