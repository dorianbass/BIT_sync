<?php
	Class BIT_sync {
	
		// ******************************************************************************************************
		// Variables
		// ******************************************************************************************************
		
		private $PDO_conn;
		private $BIT_appID;
		private $BIT_tour_dates;
		
		// ******************************************************************************************************
		// Public Functions
		// ******************************************************************************************************
		
		public function __construct( PDO $conn, $id ) {
			$this->PDO_conn = $conn;
			$this->BIT_appID = $id;
		}
		
		public function download_BIT_data() {
			// Pulls down data for all upcoming gigs for selected band from Bands In Town API into a global array
			
			$local_date = date( "l d F, H:i:s" );
			echo '<p>BIT_sync download started on ' . $local_date . '</p>';
			
			// Initialise curl object and set options to BandsInTown API, with no header, and return data in JSON format
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, "http://api.bandsintown.com/artists/Sheelanagig/events.json?api_version=2.0&app_id=" . $this->BIT_appID );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			
			// Run curl operation and close the object
			$result = curl_exec( $ch );
			curl_close( $ch );

			// If the curl operation returns FALSE, something is wrong so write error report and exit
			if ( !$result ) {
				echo "Error connecting to BandsInTown API - exiting";
				die;
			}
	
			// Decode the JSON data returned by BandsInTown into a multi dimensional array	
			$this->BIT_tour_dates = json_decode( $result, true );
			
			// Report number of records downloaded
			echo '<p>' . count( $this->BIT_tour_dates ) . ' records downloaded from Bands In Town.</p>';
			
			// Insert Bands In Town data into temporary table for easy reference and updates - exit if any problems
			$success = $this->create_temp_table();
			
			if ( !$success ) { echo '<p>Uh oh!  Problem creating temp table - exiting...</p>'; die; }
		}
	
		public function get_BIT_data() {
			return $this->BIT_tour_dates;
		}
	
		public function update_local() {
			// Create tour_dates table on local database if not found, then check 		
			
			if ( !$this->tour_dates_exists() ) {
				// Table tour_dates does not exist, so create it
				$success = $this->create_tour_dates();
				
				if ( !$success ) { 
					echo '<p>Oh no!  There was a problem trying to create the tour_dates table!  Exiting...</p>';
					die;
				}
				
				echo '<p>Created table tour_dates on local database</p>';
			} else {
				// Check if any gigs exist locally which were removed from Bands In Town
				$success = $this->check_deleted();
				
				if ( !$success ) {
					echo '<p>Oh no!  There was a problem trying to query the tour_dates table!  Exiting...</p>';
					die;
				}
				
				// Check if any new gigs have been created on Bands In Town
				$success = $this->check_added();

				if ( !$success ) {
					echo '<p>Oh no!  There was a problem trying to query the tour_dates table!  Exiting...</p>';
					die;
				}
				
				// Delete all future gigs from local database (prior to updating with data from Bands in Town)
				$success = $this->drop_local();
				
				if ( !$success ) {
					echo '<p>Oh no!  There was a problem trying to delete from the tour_dates table!  Exiting...</p>';
					die;
				}
				
			}
		
			// Insert all downloaded records from Bands In Town to local database
			$success = $this->insert_tour_dates();
				
			if ( !$success ) {
				echo '<p>Uh oh!  There was a problem trying to insert the new records into the tour_dates table!  Exiting...</p>';
				die;
			}
			
			// Get local time for end of report
			$local_time = date( "H:i:s" );
			echo '<p>BITsync operations completed successfully at ' . $local_time . '</p>';
		
			return TRUE;
		}
	
		// ******************************************************************************************************
		// Private functions
		// ******************************************************************************************************
		
		private function tour_dates_exists() {
			// Checks to see if the tour_dates table exists in the current database
		
			$query = 'SELECT * FROM tour_dates LIMIT 1';
		
			try {
				$result = $this->PDO_conn->query( $query );
			} catch ( Exception $e ) {
				return FALSE;
			}
				
			return $result !== FALSE;
		}
	
		private function create_tour_dates() {
			// Creates new tour_dates table in the local database
		
			$query = 'CREATE TABLE tour_dates (' .
					'id int(11) NOT NULL,' .
					'date datetime NOT NULL,' .
					'city varchar(50) NOT NULL,' .
					'country varchar(50) NOT NULL,' .
					'venue varchar(75) NOT NULL,' .
					'tickets varchar(100) DEFAULT NULL,' .
					'facebook varchar(100) DEFAULT NULL, ' .
					'PRIMARY KEY (id), ' .
					'KEY (date) ' .
					') ENGINE=MyISAM DEFAULT CHARSET=utf8';
		
			try {
				$result = $this->PDO_conn->query( $query );
			} catch ( Exception $e ) {
				return FALSE;
			}
		
			return TRUE;
		}
	
		private function create_temp_table() {
			// Creates a temporary table called temp_gigs and inserts all gig details dowloaded from Bands In Town into it
			// This table is used to test and update the main tour_dates table later on
			
			// Create temp table
			$query = 'CREATE TEMPORARY TABLE temp_gigs ( ' .
				'id int(11) NOT NULL,' .
				'date datetime NOT NULL,' .
				'city varchar(50) NOT NULL,' .
				'country varchar(50) NOT NULL,' .
				'venue varchar(75) NOT NULL,' .
				'tickets varchar(100) DEFAULT NULL,' .
				'facebook varchar(100) DEFAULT NULL, ' .
				'PRIMARY KEY (id), ' .
				'KEY (date) ' .
				')';
		
			try {
				$result = $this->PDO_conn->query( $query );
			} catch ( Exception $e ) {
				return FALSE;
			}
		
			// Populate temp table with Bands In Town data
			foreach( $this->BIT_tour_dates as $row ) {
			
				//Convert BIT datetime format (yyyy-mm-ddThh:mm:ss) to MySQL datetime formate (yyyy-mm-dd hh:mm:ss)
				$gig_date = str_replace( 'T', ' ', $row["datetime"] );
			
				$query = 'INSERT INTO temp_gigs (id, date, city, country, venue, tickets, facebook) VALUES (' .
					$row["id"] . ',"' . 
					$gig_date . '","' .
					$row["venue"]["city"] . '","' .
					$row["venue"]["country"] . '","' .
					$row["venue"]["name"] . '","' .
					$row["ticket_url"] . '","' .
					$row["facebook_rsvp_url"] . '")';
			
				try {
					$result = $this->PDO_conn->query( $query );
				} catch ( Exception $e ) {
					return FALSE;
				}
			}
			
			return TRUE;
		}
		
		private function check_deleted() {
			// Compares records downloaded from Bands In Town against records in local tour_dates table
			// Records which exist in tour_dates (that are in the future) that aren't in Bands in Town are reported
			// NOTE - this function is reporting purposes only, as these records will be deleted anyway before updating
			// the tour_dates table with all downloaded 
		
			$query = "SELECT * FROM tour_dates WHERE date >= NOW() AND id NOT IN ( SELECT id FROM temp_gigs )";
			
			try {
				$result = $this->PDO_conn->query( $query );
				$result->setFetchMode(PDO::FETCH_ASSOC);
			} catch ( Exception $e ) {
				return FALSE;
			}
		
			// Report details of any gigs deleted from Bands In Town
			$gigs_deleted = 0;
		
			while ( $row = $result->fetch() ) {	
				echo '<p>Record id: ' . $row["id"] . ' on ' . $row["date"] . ' at ' . $row["city"] . ' ' . $row["venue"] . ' deleted from Bands In Town</p>';
				$gigs_deleted++;
			}
		
			if ( !$gigs_deleted ) {
				echo '<p>No gigs deleted from Bands In Town since last update</p>';
			}
		
			return TRUE;
		}
		
		private function check_added() {
			// Compares records in local tour_dates table against records downloaded from Bands In Town
			// Records which exist in Bands In Town that aren't in the local database are reported
			// NOTE - as with check_deleted(), this function is for reporting purposes only
			
			$query = "SELECT * FROM temp_gigs WHERE id NOT IN ( SELECT id FROM tour_dates )";

			try {
				$result = $this->PDO_conn->query( $query );
				$result->setFetchMode(PDO::FETCH_ASSOC);
			} catch ( Exception $e ) {
				return FALSE;
			}	
		
			// Report details of any gigs added to Bands In Town
			$gigs_added = 0;
			
			while ( $row = $result->fetch() ) {
				echo '<p>Record id: ' . $row["id"] . ' on ' . $row["date"] . ' at ' . $row["city"] . ' ' . $row["venue"] . ' added to Bands In Town</p>';
				$gigs_added++;
			}
			
			if ( !$gigs_added ) {
				echo '<p>No gigs added to Bands In Town since last update</p>';
			}
			
			return TRUE;
		}
		
		private function drop_local() {
			// Deletes all future gig records from tour_dates table on local database, prior to inserting records from Bands In Town
			// (this is more efficient than checking every field of every local gig record against the Bands In Town version to find changes)
		
			$query = "DELETE FROM tour_dates WHERE date >= NOW()";

			try {
				$gigs_deleted = $this->PDO_conn->exec( $query );
			} catch ( Exception $e ) {
				return FALSE;
			}
		
			echo '<p>' . $gigs_deleted . ' records deleted from tour_dates</p>';
		
			return TRUE;
		}
		
		private function insert_tour_dates() {
			// Inserts all gig records downloaded from Bands In Town into tour_dates table
			// NOTE - tour_dates table must be empty, or have had future dates removed or there could be a DB error
			
			$query = 'INSERT INTO tour_dates SELECT * FROM temp_gigs';
		
			try {
				$gigs_added = $this->PDO_conn->exec( $query );
			} catch( Exception $e ) {
				return FALSE;
			}
			
			echo '<p>' . $gigs_added . ' records added to tour_dates</p>';
			
			return TRUE;
		}
	
	}
?>