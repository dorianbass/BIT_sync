<?php include("BITsync_lib.php"); ?>

<html>
<head>
	<title>Testing BITsync PHP Class</title>
</head>

<body>

	<h1>Test Output for BITsync class</h1>
	
<?php
			
	// Create PDO database object to pass to BIT_sync constructor
	// (for production the attribute PD::ATTR_ERRMODE should be reset to PDO::ERRMODE_EXCEPTION)
	
	$username="";
	$password="";
	$server="";
	$database="";
	$application_id = "";
	
	$conn = new PDO( 'mysql:host=' . $server . ';dbname=' . $database, $username, $password );
	$conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
	
	// Create BIT_sync object and get all Bands In Town data
				
	$BIT_obj = new BIT_sync( $conn, $application_id );
	$BIT_obj->download_BIT_data();		
	$BIT_obj->update_local();
	
?>
	<pre>
<?php

	// Display all records downloaded from Bands In Town
	$tour_dates = $BIT_obj->get_BIT_data();

	echo "-----------------------------------------------------------------------------------------------------------\n\n";
	
	foreach( $tour_dates as $row ) {
		echo 'ID: ' . $row["id"] . "\n";
		echo 'Date: ' . $row["datetime"] . "\n";
		echo 'City: ' . $row["venue"]["city"] . "\n";
		echo 'Country: ' . $row["venue"]["country"] . "\n";
		echo 'Venue: ' . $row["venue"]["name"] . "\n";
		echo 'Tickets: ' . $row["ticket_url"] . "\n";
		echo 'Facebook RSVP: ' . $row["facebook_rsvp_url"] . "\n\n";
		echo "-----------------------------------------------------------------------------------------------------------\n\n";
	}

?>
	</pre>
</body>
</html>