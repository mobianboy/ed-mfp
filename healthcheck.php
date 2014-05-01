<html>
<head>
<title>alpha-web db health check</title>
</head>
<body>

<?php
$dbhost = 'alpha-db.eardish.net:3306';
$dbuser = 'alphawebdb';
$dbpass = 'GLLTtpr5dbKLL';
$conn = mysql_connect($dbhost, $dbuser, $dbpass);
if(! $conn)
{
//die('Could not connect: ' . mysql_error());
die('FAIL');
}
echo 'OK';
mysql_close($conn);
?>

</body>
</html>

