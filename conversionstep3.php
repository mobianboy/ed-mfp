<?php

// This is disabled by SDK on 09-27-2013 after successful conversion
exit;

$db = new PDO("mysql:dbname=alphawebdb;host=alpha-db.eardish.net", "alphawebdb", "GLLTtpr5dbKLL");


echo "Repopulating image & audio tables...";

$repop['mfp_audio'] = $db->query("INSERT `mfp_audio` SELECT * FROM `mfp_audio_back`");
echo ".";
$repop['mfp_images'] = $db->query("INSERT `mfp_images` SELECT * FROM `mfp_images_back`");
echo ".".PHP_EOL;

$errors1 = false;
foreach ($repop as $key => $val) {
    if (!$val) {
        echo "Error repopulating '".$key."'".PHP_EOL;
        $errors1 = true;
    }
}

echo PHP_EOL.PHP_EOL;

if ($errors1) {
    echo "Conversion failed in Step 3. Please investigate. Killing script.".PHP_EOL;
    exit;
}

echo "Filling formats tables...";

$fill['mfp_audio_formats'] = $db->query("INSERT `mfp_audio_formats` (`audio_id`, `format`, `s3_url`, `s3_key`, `date_created`, `date_modified`) SELECT (`audio_id`, `format`, `s3_url`, `s3_key`, `date_created`, `date_modified`) FROM `mfp_audio_formats_back`");
echo ".";
$fill['mfp_image_formats'] = $db->query("INSERT `mfp_image_formats` (`image_id`, `format`, `s3_url`, `s3_key`, `date_created`, `date_modified`) SELECT (`image_id`, `format`, `s3_url`, `s3_key`, `date_created`, `date_modified`) FROM `mfp_image_formats_back`");
echo ".".PHP_EOL;

$errors2 = false;
foreach ($repop as $key => $val) {
    if (!$val) {
        echo "Error filling '".$key."'".PHP_EOL;
        $errors2 = true;
    }
}

echo PHP_EOL.PHP_EOL;

if ($errors2) {
    echo "Conversion failed in Step 3. Please investigate. Killing script.".PHP_EOL;
} else {
    echo "Conversion successful. You rock.".PHP_EOL;
}
