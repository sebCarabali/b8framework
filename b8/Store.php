<?php

namespace b8;
use b8\Exception\HttpException;
use b8\Database,
	b8\Model;

abstract class Store
{
	protected $_modelName   = null;
	protected $_tableName   = null;
	protected $_primaryKey  = null;

	/**
	 * @return \b8\Model
	 */
	abstract public function getByPrimaryKey($key, $useConnection = 'read');

	public function getWhere($where = array(), $limit = 25, $offset = 0, $joins = array(), $order = array(), $manualJoins = array(), $group = null, $manualWheres = array(), $whereType = 'AND')
	{
		$query      = 'SELECT ' . $this->_tableName . '.* FROM ' . $this->_tableName;
		$countQuery = 'SELECT COUNT(*) AS cnt FROM ' . $this->_tableName;

		$wheres = array();
		$params = array();
		foreach($where as $key => $value)
		{
			$key = $this->fieldCheck($key);

			if(!is_array($value))
			{
				$params[] = $value;
				$wheres[] = $key . ' = ?';
			}
			else
			{
				if(isset($value['operator']))
				{
					if(is_array($value['value']))
					{
						if($value['operator'] == 'between')
						{
							$params[] = $value['value'][0];
							$params[] = $value['value'][1];
							$wheres[] = $key . ' BETWEEN ? AND ?';
						}
						elseif($value['operator'] == 'IN')
						{
							$in = array();

							foreach($value['value'] as $item)
							{
								$params[] = $item;
								$in[]     = '?';
							}

							$wheres[] = $key . ' IN (' . implode(', ', $in) . ') ';
						}
						else
						{
							$ors = array();
							foreach($value['value'] as $item)
							{
								if($item == 'null')
								{
									switch($value['operator'])
									{
										case '!=':
											$ors[] = $key . ' IS NOT NULL';
											break;

										case '==':
										default:
											$ors[] = $key . ' IS NULL';
											break;
									}
								}
								else
								{
									$params[] = $item;
									$ors[]    = $this->fieldCheck($key) . ' ' . $value['operator'] . ' ?';
								}
							}
							$wheres[] = '(' . implode(' OR ', $ors) . ')';
						}
					}
					else
					{
						if($value['operator'] == 'like')
						{
							$params[] = '%' . $value['value'] . '%';
							$wheres[] = $key . ' ' . $value['operator'] . ' ?';
						}
						else
						{
							if($value['value'] === 'null')
							{
								switch($value['operator'])
								{
									case '!=':
										$wheres[] = $key . ' IS NOT NULL';
										break;

									case '==':
									default:
										$wheres[] = $key . ' IS NULL';
										break;
								}
							}
							else
							{
								$params[] = $value['value'];
								$wheres[] = $key . ' ' . $value['operator'] . ' ?';
							}
						}
					}
				}
				else
				{
					$wheres[] = $key . ' IN (\'' . implode('\', \'', array_map('mysql_real_escape_string', $value)) . '\')';
				}
			}
		}

		if(count($joins))
		{
			foreach($joins as $table => $join)
			{
				$query .= ' LEFT JOIN ' . $table . ' ' . $join['alias'] . ' ON ' . $join['on'] . ' ';
				$countQuery .= ' LEFT JOIN ' . $table . ' ' . $join['alias'] . ' ON ' . $join['on'] . ' ';
			}
		}

		if(count($manualJoins))
		{
			foreach($manualJoins as $join)
			{
				$query .= ' ' . $join . ' ';
				$countQuery .= ' ' . $join . ' ';
			}
		}

		$hasWhere = false;
		if(count($wheres))
		{
			$hasWhere = true;
			$query .= ' WHERE (' . implode(' ' . $whereType . ' ', $wheres) . ')';
			$countQuery .= ' WHERE (' . implode(' ' . $whereType . ' ', $wheres) . ')';
		}

		if(count($manualWheres))
		{
			foreach($manualWheres as $where)
			{
				if(!$hasWhere)
				{
					$hasWhere = true;
					$query .= ' WHERE ';
					$countQuery .= ' WHERE ';
				}
				else
				{
					$query .= ' ' . $where['type'] . ' ';
					$countQuery .= ' ' . $where['type'] . ' ';
				}

				$query .= ' ' . $where['query'];
				$countQuery .= ' ' . $where['query'];
				foreach($where['params'] as $param)
				{
					$params[] = $param;
				}
			}
		}

		if(!is_null($group))
		{
			$query .= ' GROUP BY ' . $group . ' ';
		}

		if(count($order))
		{
			$orders = array();
			if(is_string($order) && $order == 'rand')
			{
				$query .= ' ORDER BY RAND() ';
			}
			else
			{
				foreach($order as $key => $value)
				{
					$orders[] = $this->fieldCheck($key) . ' ' . $value;
				}

				$query .= ' ORDER BY ' . implode(', ', $orders);
			}
		}

		if($limit)
		{
			$query .= ' LIMIT ' . $limit;
		}

		if($offset)
		{
			$query .= ' OFFSET ' . $offset;
		}

		$stmt = Database::getConnection('read')->prepare($countQuery);
		if($stmt->execute($params))
		{
			$res   = $stmt->fetch(\PDO::FETCH_ASSOC);
			$count = (int)$res['cnt'];
		}
		else
		{
			$count = 0;
		}

		$stmt = Database::getConnection('read')->prepare($query);

		if($stmt->execute($params))
		{
			$res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			$rtn = array();

			foreach($res as $data)
			{
				$rtn[] = new $this->_modelName($data);
			}

			return array('items' => $rtn, 'count' => $count);
		}
		else
		{
			return array('items' => array(), 'count' => 0);
		}
	}

	public function save(Model $obj, $saveAllColumns = false)
	{
	    if(!isset($this->_primaryKey))
	    {
			throw new HttpException\BadRequestException('Save not implemented for this store.');
	    }

	    if(!($obj instanceof $this->_modelName))
	    {
			throw new HttpException\BadRequestException(get_class($obj) . ' is an invalid model type for this store.');
	    }

	    $data = $obj->getDataArray();
	    $modified = ($saveAllColumns) ? array_keys($data) : $obj->getModified();


	    if(isset($data[$this->_primaryKey]))
	    {
			$updates = array();
			$update_params = array();
			foreach($modified as $key)
			{
				$updates[]       = $key . ' = :' . $key;
				$update_params[] = array($key, $data[$key]);
			}

			if(count($updates))
			{
				$qs = 'UPDATE ' . $this->_tableName . '
											SET ' . implode(', ', $updates) . ' 
											WHERE ' . $this->_primaryKey . ' = :primaryKey';
				$q  = Database::getConnection('write')->prepare($qs);

				foreach($update_params as $update_param)
				{
					$q->bindValue(':' . $update_param[0], $update_param[1]);
				}

				$q->bindValue(':primaryKey', $data[$this->_primaryKey]);
				$q->execute();

				$rtn = $this->getByPrimaryKey($data[$this->_primaryKey], 'write');

				return $rtn;
			}
			else
			{
				return $obj;
			}
		}
		else
		{
			$cols    = array();
			$values  = array();
			$qParams = array();
			foreach($modified as $key)
			{
				$cols[]              = $key;
				$values[]            = ':' . $key;
				$qParams[':' . $key] = $data[$key];
			}

			if(count($cols))
			{
				$qs = 'INSERT INTO ' . $this->_tableName . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $values) . ')';
				$q  = Database::getConnection('write')->prepare($qs);

				if($q->execute($qParams))
				{
					return $this->getByPrimaryKey(Database::getConnection('write')->lastInsertId(), 'write');
				}
			}
		}

		throw new HttpException\ServerErrorException('Could not save.');
	}

	public function delete(Model $obj)
	{
	    if(!isset($this->_primaryKey))
	    {
			throw new HttpException\BadRequestException('Delete not implemented for this store.');
	    }

	    if(!($obj instanceof $this->_modelName))
	    {
			throw new HttpException\BadRequestException(get_class($obj) . ' is an invalid model type for this store.');
	    }

		$data = $obj->getDataArray();

		$q = Database::getConnection('write')->prepare('DELETE FROM ' . $this->_tableName . ' WHERE ' . $this->_primaryKey . ' = :primaryKey');
		$q->bindValue(':primaryKey', $data[$this->_primaryKey]);
		$q->execute();

		return true;
	}

	/**
	 *
	 */
	protected function fieldCheck($field)
	{
		if(is_null($field))
		{
			throw new HttpException('You cannot have null field');
		}

		if(strpos($field, '.') === false)
		{
			return $this->_tableName . '.' . $field;
		}

		return $field;
	}
}	