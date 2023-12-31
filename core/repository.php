<?php
class Repository {
    var $error;
    private $db;
    private $table;
	protected $query_string;
	protected $query_order;
	protected $dbprefix;

    public function __construct($table, $db) {
        $this->dbprefix = DB_PREFIX;
        $this->table = $table;
        $this->db = $db;
    }

	function parse_table($table) {
		return $this->dbprefix.$table;
	}

	function find($where = array(), $single = false) {
		$data = array();

		if(!empty($where) && is_array($where)) {
			$temp = array();
			foreach ($where as $key => $value) {
				$temp[] = " {$key} = '{$value}' ";
			}
		}

		$where_condition = (is_array($where) ? (!empty($temp) ? "WHERE ".implode(' and ', $temp) : '') : (empty($where) ? '' : "WHERE ".$where));
		$this->query_string = "SELECT * FROM {$this->parse_table($this->table)} {$where_condition} {$this->query_order}";

        if ($single) {
            $this->query_string = $this->query_string . " LIMIT 1";
        }

		try {
	    	$data = $this->query($this->query_string);
	    }
	    catch (Exception $e) {
	    	$this->error = $e->getMessage();
				throw new \Exception($e->getMessage(), 1);
		}
	    catch (PDOException $e) {
	    	$this->error = $e->getMessage();
				throw new \Exception($e->getMessage(), 1);
	    }

	    return $data;
	}

	function findOne($where = array()) {
        if (is_numeric($where)) {
            $where = array('id' => $where);
        }

		$result = $this->find($where, true);
		if(!empty($result)) return $result[0];

		return array();
	}

	function query($query = '', $exclude = false) {
		$data = array();
		$this->query_string = $query;
		try {
			if (!$exclude) {
				$extract = $this->extractTable($this->query_string);
				$sql = $extract['sql'];
			} else {
				$sql = $this->query_string;
			}
			$query = $this->db->query($sql);
	    	$result = $query->fetchAll(PDO::FETCH_ASSOC);
	    	$data = $result;
	    }
	    catch (Exception $e) {
	    	$this->error = $e->getMessage();
			throw new Exception($e->getMessage());
		}
		catch (PDOException $e) {
			$this->error = $e->getMessage();
			throw new PDOException($e->getMessage());
		}

	  return array_map(function($value){
            return (object) $value;
      }, $data);
	}
	
	private function extractTable($sql) {
		$modifiedTableName = '';
		$modifiedSql = '';
		// Regular expressions to match different types of statements
		$selectPattern = "/\bFROM\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\b/i";
		$updatePattern = "/\bUPDATE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\b/i";
		$deletePattern = "/\bDELETE\s+FROM\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\b/i";
	
		if (preg_match($selectPattern, $sql, $matches)) {
			$originalTableName = $matches[1];
			$modifiedTableName = $this->parse_table($originalTableName);
			$modifiedSql = preg_replace($selectPattern, "FROM $modifiedTableName", $sql);
			$tableName = $modifiedTableName;
		} elseif (preg_match($updatePattern, $sql, $matches)) {
			$originalTableName = $matches[1];
			$modifiedTableName = $this->parse_table($originalTableName);
			$modifiedSql = preg_replace($updatePattern, "UPDATE $modifiedTableName", $sql);
			$tableName = $modifiedTableName;
		} elseif (preg_match($deletePattern, $sql, $matches)) {
			$originalTableName = $matches[1];
			$modifiedTableName = $this->parse_table($originalTableName);
			$modifiedSql = preg_replace($deletePattern, "DELETE FROM $modifiedTableName", $sql);
			$tableName = $modifiedTableName;
		}

		return array('table' => $modifiedTableName, 'sql' => $modifiedSql);
	}

	function insert($insert_values = array()) {
		try {
			$columns = array();
			$values = array();
			$dummy = array();


			if(!empty($insert_values)) {
				foreach($insert_values as $key => $value) {
					$columns[] = "`{$key}`";
					$dummy[] = '?';
					$values[] = $value;
				}
			}

			$this->query_string = "INSERT INTO {$this->parse_table($this->table)} (".implode(',', $columns).") VALUES (".implode(',', $dummy).")";
			$sql = $this->db->prepare($this->query_string);
			$response = $sql->execute($values);
			
            $insert_values->id = $this->lastInsertedId();
            return $insert_values;
		}
		catch (Exception $e) {
			$this->error = $e->getMessage();
		}
		catch (PDOException $e) {
			$this->error = $e->getMessage();
	    }
	}

	function delete($where = array()) {
		try {
			$columns = array();
			$values = array();

			if(!empty($where)) {
				foreach($where as $key => $value) {
					$columns[] = "`{$key}`".' = ? ';
					$values[] = $value;
				}
			}

			$where_condition = implode(' and ', $columns);
			$this->query_string = "DELETE from {$this->parse_table($this->table)} WHERE {$where_condition}";
			if (empty($where_condition)) $this->query_string = str_replace('WHERE ', '', $this->query_string);
			$sql = $this->db->prepare($this->query_string);
			$response = $sql->execute($values);
			return $response;
		}
		catch (Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
		catch (PDOException $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}
	function update($update_values = array(), $where = array()) {
		try {
			$columns = array();
			$wcolumns = array();
			$values = array();
            $id = '';
            
            if (!empty($update_values->id)) {
                $id = $update_values->id;
                unset($update_values->id);
            }

            if (!empty($id) && is_numeric($where)) {
                $where = array('id' => $id);
            }

			if(!empty($update_values)) {
				foreach($update_values as $key => $value) {
					$columns[] = "`{$key}`".' = ? ';
					$values[] = $value;
				}
			}


			if(!empty($where)) {
				foreach($where as $key => $value) {
					$wcolumns[] = $key.' = ? ';
					$values[] = $value;
				}
			}
			$where_condition = implode(' and ', $wcolumns);

			$this->query_string = "UPDATE {$this->parse_table($this->table)}
									SET ".implode(',', $columns)."
									WHERE {$where_condition} ";

			$sql = $this->db->prepare($this->query_string);
			$response = $sql->execute($values);

            $update_values->id = $id;
			return $update_values;
		}
		catch (Exception $e) {
			$this->error = $e->getMessage();
		}
		catch (PDOException $e) {
			$this->error = $e->getMessage();
	    }
	}

    function save($payload) {
        if (!empty($payload->id)) {
            $id = $payload->id;
            unset($payload->id);
            $response = $this->update($payload, array('id' => $id));
        } else {
            $response = $this->insert($payload);
        }

        return $response;
    }

	function count($where = array()) {
		$data = array();

		if(!empty($where) && is_array($where)) {
			$temp = array();
			foreach ($where as $key => $value) {
				$temp[] = " {$key} = '{$value}' ";
			}
		}

		$where_condition = (is_array($where) ? (!empty($temp) ? "WHERE ".implode(' and ', $temp) : '') : (empty($where) ? '' : "WHERE ".$where));
		$this->query_string = "SELECT count(*) as count FROM {$this->parse_table($this->table)} {$where_condition}";

		try {
	    	$query = $this->db->query($this->query_string, true);
			return $query->fetch(); 
	    }
	    catch (Exception $e) {
	    	$this->error = $e->getMessage();
				throw new \Exception($e->getMessage(), 1);
		}
	    catch (PDOException $e) {
	    	$this->error = $e->getMessage();
				throw new \Exception($e->getMessage(), 1);
	    }

	    return 0;
	}

	function order_by($order_by = '') {
		if(!empty($order_by)) {
			$this->query_order = 'ORDER BY '.$order_by;
		}
	}

	function error() {
			return $this->error;
	}

	function last_query() {
		return $this->query_string;
	}

	function lastInsertedId() {
		return $this->db->lastInsertId();
	}
}