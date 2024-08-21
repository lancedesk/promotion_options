<?php
class Locations
{
    private $db;
    /**
    * Constructor to establish the database connection.
    */
    public function __construct()
    {
        $this->connect_to_database();		add_action('wp_ajax_load_states', [$this, 'load_states']);
        add_action('wp_ajax_nopriv_load_states', [$this, 'load_states']);
        add_action('wp_ajax_load_cities', [$this, 'load_cities']);
        add_action('wp_ajax_nopriv_load_cities', [$this, 'load_cities']);				/* Hook into the 'wp' action to run when a post is loaded */		/* add_action('admin_init', [$this, 'insert_locations_on_post_load']); */
    }
    /**
    * Connect to the SQLite database.
    */
    private function connect_to_database()
    {
        $database_path = $_SERVER['DOCUMENT_ROOT'] . '/locations/locations.sqlite3';
        try
        {
            $this->db = new PDO('sqlite:' . $database_path);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e)
        {
            die('Connection failed: ' . $e->getMessage());
        }
    }
    /**
    * Load states based on the selected country ID.
    */
    public function load_states()	{		$country_id = isset($_POST['country_id']) ? intval($_POST['country_id']) : 0;		if ($country_id) {			$query = "SELECT id, name FROM states WHERE country_id = :country_id";			$states = $this->query($query, ['country_id' => $country_id]);						if ($states) {				wp_send_json_success($states);			} else {				wp_send_json_error(['message' => __('No states found.', 'DIRECTORYPRESS')]);			}		} else {			wp_send_json_error(['message' => __('Invalid country ID.', 'DIRECTORYPRESS')]);		}	}		/**	 * Load states based on the provided country ID.	 * @param int $country_id The ID of the country for which states are to be loaded.	 * @return array The states corresponding to the given country ID.	*/	public function get_states_by_country_id($country_id)	{		/* Ensure the country ID is an integer */		$country_id = intval($country_id);		if ($country_id > 0) {			/* Prepare the query to fetch states */			$query = "SELECT id, name FROM states WHERE country_id = :country_id";						/* Execute the query and get the states */			$states = $this->query($query, ['country_id' => $country_id]);			if ($states) {				return array('success' => true, 'data' => $states);			} else {				return array('success' => false, 'message' => __('No states found.', 'DIRECTORYPRESS'));			}		} else {			return array('success' => false, 'message' => __('Invalid country ID.', 'DIRECTORYPRESS'));		}	}		/**	 * Get cities by state ID.	 * @param int $state_id The ID of the state.	 * @return array An array with success status and data or an error message.	*/	public function get_cities_by_state_id($state_id)	{		/* Ensure the state ID is an integer */		$state_id = intval($state_id);		if ($state_id > 0) {			/* Prepare the query to fetch cities based on the state ID */			$query = "SELECT id, city FROM zipcodes WHERE state_id = :state_id";						/* Execute the query and fetch cities */			$cities = $this->query($query, ['state_id' => $state_id]);			if ($cities) {				/* Format city names: Capitalize properly */				foreach ($cities as &$city) {					$city_normalized = strtolower($city['city']); /* Convert to lowercase */					$city['city'] = ucwords($city_normalized); /* Capitalize properly */				}				return array('success' => true, 'data' => $cities);			} else {				return array('success' => false, 'message' => __('No cities found.', 'DIRECTORYPRESS'));			}		} else {			return array('success' => false, 'message' => __('Invalid state ID.', 'DIRECTORYPRESS'));		}	}
    /**
     * Load cities based on the selected state ID.
    */		public function load_cities()	{		$state_id = isset($_POST['state_id']) ? intval($_POST['state_id']) : 0;		if ($state_id) {			/* Select the city and use the id as the identifier */			$query = "SELECT id, city FROM zipcodes WHERE state_id = :state_id";			$cities = $this->query($query, ['state_id' => $state_id]);			if ($cities) {				/* Format city names */				foreach ($cities as &$city) {					$city_normalized = strtolower($city['city']); /* Convert to lowercase */					$city['city'] = ucwords($city_normalized); /* Capitalize properly */				}				wp_send_json_success($cities);			} else {				wp_send_json_error(['message' => __('No cities found.', 'DIRECTORYPRESS')]);			}		} else {			wp_send_json_error(['message' => __('Invalid state ID.', 'DIRECTORYPRESS')]);		}	}
    /**
     * Execute a query on the SQLite database.
     * @param string $query The SQL query to execute.
     * @param array $params Optional parameters for prepared statements.
     * @return array|bool The query result or false on failure.
    */
    private function query($query, $params = [])
    {
        try
        {
            $stmt = $this->db->prepare($query);
            if ($stmt->execute($params))
            {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        catch (PDOException $e)
        {
            echo 'Query failed: ' . $e->getMessage();
            return false;
        }
    }
    /**
     * Close the database connection.
    */
    public function close_connection()
    {
        $this->db = null;
    }
	/**	 * Retrieve all countries from the countries table, sorted alphabetically by name.	 * @return array|bool An array of countries sorted by name, or false on failure.	*/	public function get_all_countries()	{		$query = "SELECT id, name FROM countries ORDER BY name ASC";		return $this->query($query);	}
    /**
     * Retrieve the names of all tables in the SQLite database.
     * @return array|bool An array of table names or false on failure.
    */
    public function get_all_tables()
    {
        $query = 'SELECT name FROM sqlite_master WHERE type="table"';
        return $this->query($query);
    }
    /**
     * Retrieve columns and their data types for a specific table.
     * @param string $table_name The name of the table to query.
     * @return array|bool An array of column information or false on failure.
    */
    public function get_table_columns($table_name)
    {
        $query = 'PRAGMA table_info(' . $this->db->quote($table_name) . ')';
        return $this->query($query);
    }
    /**
     * Show the structure of the SQLite database, including tables and columns.
     * @return void Outputs the database structure.
    */
    public function show_database_structure()
    {
        /* Get all tables */
        $tables = $this->get_all_tables();
        if ($tables)
        {
            echo '<h2>Database Structure</h2>';
            foreach ($tables as $table)
            {
                echo '<h3>Table: ' . htmlspecialchars($table['name']) . '</h3>';
                /* Get columns for each table */
                $columns = $this->get_table_columns($table['name']);
                if ($columns)
                {
                    echo '<table border="1"><tr><th>Column Name</th><th>Data Type</th></tr>';
                    foreach ($columns as $column)
                    {
                        echo '<tr><td>' . htmlspecialchars($column['name']) . '</td><td>' . htmlspecialchars($column['type']) . '</td></tr>';
                    }
                    echo '</table>';
                }
                else
                {
                    echo 'No columns found or query failed.<br />';
                }
            }
        }
        else
        {
            echo 'No tables found or query failed.';
        }
    }	public function insert_locations_on_post_load()	{		static $has_run = false;		if ($has_run) {			return;		}		$has_run = true;		/* Define batch size and delay */		$batch_size = 100;		$delay_seconds = 2; /* Delay between batches in seconds */		/* Initialize counters for logging */		$total_countries = 0;		$total_states = 0;		$total_cities = 0;		$total_zip_codes = 0;		/* Get all countries from the SQLite database */		$countries = $this->get_all_countries();		if (!$countries) {			return; /* No countries to insert */		}		foreach ($countries as $country) {			$country_name = ucwords(strtolower($country['name']));			/* Check if the country already exists */			$country_term = term_exists($country_name, 'directorypress-location');			if (!$country_term) {				/* Insert the country if it doesn't exist */				$country_term = wp_insert_term($country_name, 'directorypress-location');				if (is_wp_error($country_term)) {					continue; /* Skip this country if there's an error */				}			}			/* Get the term ID of the country */			$country_term_id = is_array($country_term) ? $country_term['term_id'] : $country_term;			$total_countries++;			/* Get all states for this country */			$states = $this->query("SELECT id, name FROM states WHERE country_id = :country_id", ['country_id' => $country['id']]);			if (!$states) {				continue; /* Skip if no states for this country */			}			$state_batch = [];			foreach ($states as $state) {				$state_name = ucwords(strtolower($state['name']));				$state_batch[] = $state;				/* Process in batches */				if (count($state_batch) >= $batch_size) {					$this->process_state_batch($state_batch, $country_term_id, $total_cities, $total_zip_codes);					$state_batch = []; /* Reset batch */					sleep($delay_seconds); /* Delay between batches */				}			}			/* Process remaining states */			if (!empty($state_batch)) {				$this->process_state_batch($state_batch, $country_term_id, $total_cities, $total_zip_codes);			}		}		/* Log statistics */		$this->log_statistics($total_countries, $total_states, $total_cities, $total_zip_codes);	}	private function process_state_batch($state_batch, $country_term_id, &$total_cities, &$total_zip_codes)	{		foreach ($state_batch as $state) {			/* Get all cities (distinct) for this state from the zipcodes table */			$cities = $this->query("SELECT DISTINCT city, code FROM zipcodes WHERE state_id = :state_id", ['state_id' => $state['id']]);			if (!$cities) {				continue; /* Skip if no cities for this state */			}			foreach ($cities as $city) {				$city_normalized = strtolower($city['city']);				$city_formatted = ucwords($city_normalized); /* Proper formatting for city */				/* Check if the city already exists */				$city_term = term_exists($city_formatted, 'directorypress-location');				if (!$city_term) {					/* Insert the city as a child term under the country */					$city_term = wp_insert_term(						$city_formatted,						'directorypress-location',						['parent' => $country_term_id]					);					if (!is_wp_error($city_term)) {						$city_term_id = is_array($city_term) ? $city_term['term_id'] : $city_term;						$total_cities++;						/* Insert the zip code into the wpax_city_zip_codes table */						$this->insert_zip_code($city_term_id, $city['code']);						$total_zip_codes++;					}				}			}		}	}	private function log_statistics($total_countries, $total_states, $total_cities, $total_zip_codes)	{		$log_file = WP_CONTENT_DIR . '/locations_insertion_stats.txt';		$log_message = sprintf(			"Insertion Completed:\nCountries: %d\nStates: %d\nCities: %d\nZip Codes: %d\n",			$total_countries,			$total_states,			$total_cities,			$total_zip_codes		);		file_put_contents($log_file, $log_message);		echo $log_message; /* Optional: Display message on completion */	}	/**	 * Insert a zip code for the given city ID into the wpax_city_zip_codes table.	 * @param int $city_id The term ID of the city.	 * @param string $zip_code The zip code associated with the city.	 */	private function insert_zip_code($city_id, $zip_code)	{		global $wpdb;		$wpdb->insert(			'wpax_city_zip_codes',			array(				'city_id' => $city_id,				'zip_code' => $zip_code			),			array(				'%d',				'%s'			)		);	}	    /**     * Retrieve location details by listing ID.     * @param int $listing_id The ID of the listing (post_id).     * @return array|false Array with country, state, city, postal code, or false if not found.    */    public function get_location_by_listing_id($listing_id)    {        global $wpdb;        /* Table name encapsulated inside the function */        $table_name = $wpdb->prefix . 'directorypress_locations_relation';        /* Ensure the listing ID is a valid integer */        $listing_id = (int) $listing_id;        /* Prepare the SQL query to fetch location data */        $query = $wpdb->prepare(            "SELECT country, state, city, zip_or_postal_index              FROM $table_name              WHERE post_id = %d LIMIT 1",            $listing_id        );        /* Execute the query and retrieve the results */        $location = $wpdb->get_row($query, ARRAY_A);        /* Return the location details or false if no results found */        if ($location)        {            return $location;        }        else        {            return false;        }    }
}
?>