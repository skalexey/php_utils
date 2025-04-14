<?php

// define("PRINT_QUERIES", 1);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ALL);

function to_string($value): string {
	if (is_object($value)) {
		return 'Object of class ' . get_class($value);
	}
	if (is_array($value)) {
		return 'Array: ' . print_r($value, true); // or json_encode($value)
	}
	if (is_bool($value)) {
		return $value ? 'true' : 'false';
	}
	if (is_null($value)) {
		return 'null';
	}
	return (string)$value;
}

class Db {
  var $no_error = 0;
  var $connection;
  var $query_id = 0;
  var $query_count = 0;
  var $query_time = 0;
  var $query_array = array();
  var $table_fields = array();

  function __construct($db_name, $db_host = NULL, $db_user = NULL, $db_password = NULL, $db_pconnect = 0) {
    // $connect_handle = ($db_pconnect) ? "mysql_pconnect" : "mysql_connect";
    // if (!$this->connection = $connect_handle($db_host, $db_user, $db_password)) {
    try {
      if (!$this->connection = mysqli_connect($db_host, $db_user, $db_password))
        $this->error("Could not connect to the database server ($db_host, $db_user).", 1);
      if ($db_name != "") {
        if (!mysqli_select_db($this->connection, $db_name)) {
          mysqli_close($this->connection);
          $this->error("Could not select database ($db_name).", 1);
        }
      }
    } catch (mysqli_sql_exception $e) {
      die("DB connection failed: " . $e->getMessage());
    }
    // mysqli_query($this->connection, "SET NAMES cp1251");
    return $this->connection;
  }

  function close() {
    if ($this->connection) {
      if ($this->query_id) {
        mysqli_free_result($this->query_id);
      }
      return mysqli_close($this->connection);
    }
    else {
      return false;
    }
  }

  function table_exists($table) {
    if ($this->query("SHOW TABLES LIKE '$table';")) {
        $num_rows = $this->get_numrows();
        return $num_rows > 0;
    }
    return false;
  }

  function query($query = "", $print_result = NULL) {
    unset($this->query_id);
    // echo "query: $query<br>";
    if ($query != "") {
      if ((defined("PRINT_QUERIES") && PRINT_QUERIES == 1) || (defined("PRINT_STATS") && PRINT_STATS == 1)) {
        $startsqltime = explode(" ", microtime());
      }
      if (!$this->query_id = mysqli_query($this->connection, $query)) {
        $this->error("<b>Bad SQL Query</b>: ".htmlentities($query)."<br /><b>".mysqli_error($this->connection)."</b>");
      }
      // echo "this->query_id: " . to_string($this->query_id) . "<br>";
      if ((defined("PRINT_QUERIES") && PRINT_QUERIES == 1) || (defined("PRINT_STATS") && PRINT_STATS == 1)) {
        $endsqltime = explode(" ", microtime());
        $totalsqltime = round($endsqltime[0]-$startsqltime[0]+$endsqltime[1]-$startsqltime[1],3);
        $this->query_time += $totalsqltime;
        $this->query_count++;
      }
      if (defined("PRINT_QUERIES") && PRINT_QUERIES == 1) {
        $query_stats = htmlentities($query);
        $query_stats2 = " <b>Querytime:</b> ".$totalsqltime." ";
        echo $query_stats2.$query_stats."<br>";
        $this->query_array[] = $query_stats;
      }
      if ($print_result)
        $this->print_result();
      return $this->query_id;
    }
  }

  function fetch_array($query_id = -1) {
    if ($query_id != -1)
        $this->query_id = $query_id;
    if ($this->query_id)
        return mysqli_fetch_array($this->query_id);
    return NULL;
  }
  
  function fetch_assoc($query_id = -1) {
    // echo "query_id: $query_id<br>";
    if ($query_id != -1)
        $this->query_id = $query_id;
    # if quiery
    if ($this->query_id) {
      if ($this->query_id instanceof mysqli_result) {
        // echo "fetch_assoc: ";
        // print_r($this->query_id);
        $r = mysqli_fetch_assoc($this->query_id);
        return $r;
      }
      else {
        return $this->query_id;
      }
    }
    return NULL;
  }

  function free_result($query_id = -1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    return mysqli_free_result($this->query_id);
  }

  function print_result($ar = NULL) {
    if ($this->query_id)
    {
      $result = $ar ? $ar : $this->fetch_assoc();
      echo "<b>Query result</b>:<br>";
      print_r($result);
      echo "<br>";
      echo "<b>End of Query result</b><br>";
    }
    else
    {
      echo "<b>Query result</b>: no valid result<br>";
    }
  }
  
  function query_firstrow($query = "", $print_result = false) {
    if ($query != "") {
      $this->query($query, $print_result);
    }
    if (!$this->query_id)
      return NULL;
    $result = $this->fetch_assoc();
    $this->free_result();
    return $result;
  }

  function get_numrows($query_id = -1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    return mysqli_num_rows($this->query_id);
  }

  function get_insert_id() {
    return ($this->connection) ? mysqli_insert_id($this->connection) : 0;
  }

  function get_next_id($column = "", $table = "") {
    if (!empty($column) && !empty($table)) {
      $sql = "SELECT MAX($column) AS max_id
              FROM $table";
      $row = $this->query_firstrow($sql);
      return (($row['max_id'] + 1) > 0) ? $row['max_id'] + 1 : 1;
    }
    else {
      return NULL;
    }
  }

  function get_numfields($query_id = -1) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    return mysqli_num_fields($this->query_id);
  }

  function get_fieldinfo($query_id = -1, $offset = null) {
    if ($query_id != -1) {
      $this->query_id = $query_id;
    }
    return mysqli_fetch_field_direct($this->query_id, $offset);
  }
  
  function get_fieldname($query_id = -1, $offset = null) {
    $finfo = get_fieldinfo($query_id, $offset);
    return $info->name;
  }

  function get_fieldtype($query_id = -1, $offset = null) {
    $finfo = get_fieldinfo($query_id, $offset);
    return $info->type;
  }

  function affected_rows() {
    return ($this->connection) ? mysqli_affected_rows($this->connection) : 0;
  }

  function is_empty($query = "") {
    if ($query != "") {
      $this->query($query);
    }
    return (!mysqli_num_rows($this->query_id)) ? 1 : 0;
  }

  function not_empty($query = "") {
    if ($query != "") {
      $this->query($query);
    }
    return (!mysqli_num_rows($this->query_id)) ? 0 : 1;
  }

  function get_table_fields($table) {
    if (!empty($this->table_fields[$table])) {
      return $this->table_fields[$table];
    }
    $this->table_fields[$table] = array();
    $result = $this->query("SHOW FIELDS FROM $table");
    while ($row = $this->fetch_assoc()) {
      $this->table_fields[$table][$row['Field']] = $row['Type'];
    }
    return $this->table_fields[$table];
  }

  function error($errmsg, $halt = 0) {
    if (!$this->no_error) {
    // go404();
     global $user_info;
     //if (isset($user_info['user_level']) && $user_info['user_level'] == ADMIN){
      echo "<br /><font color='#FF0000'><b>DB Error</b></font>: ".$errmsg."<br />";
      //} else {
        //echo "<br /><font color='#FF0000'><b>An unexpected error occured. Please try again later.</b></font><br />";
      //}
      if ($halt) {
        exit;
      }
    }
  }
} // end of class
?>