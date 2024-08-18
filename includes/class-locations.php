<?php

class Locations
{
    private $db;

    /**
     * Constructor to establish the database connection.
    */

    public function __construct()
    {
        $this->connect_to_database();
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
     * Method to execute a query on the SQLite database.
     * 
     * @param string $query The SQL query to execute.
     * @param array $params Optional parameters for prepared statements.
     * @return array|bool The query result or false on failure.
    */

    public function query($query, $params = [])
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
     * Method to close the database connection.
     */
    public function close_connection()
    {
        $this->db = null;
    }
}

?>