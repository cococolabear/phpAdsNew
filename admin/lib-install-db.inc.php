<?php // $Revision: 1.1 $

/************************************************************************/
/* phpAdsNew 2                                                          */
/* ===========                                                          */
/*                                                                      */
/* Copyright (c) 2001 by the phpAdsNew developers                       */
/* http://sourceforge.net/projects/phpadsnew                            */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/



// Define
define ('phpAds_databaseUpgradeSupported', true);
define ('phpAds_databaseCreateSupported', true);
define ('phpAds_tableTypesSupported', true);




/*********************************************************/
/* Upgrade the database to the latest structure          */
/*********************************************************/

function phpAds_upgradeDatabase ($tabletype = '')
{
	// Get the database structure
	$dbstructure = phpAds_prepareDatabaseStructure();
	
	// Get table names
	$res = phpAds_dbQuery("SHOW TABLES");
	while ($row = phpAds_dbFetchRow($res))
		$availabletables[] = $row[0];
	
	for (reset($dbstructure);
		$key = key($dbstructure);
		next($dbstructure))
	{
		if (is_array($availabletables) && in_array ($key, $availabletables))
		{
			// Table exists, upgrade
			phpAds_upgradeTable ($key, $dbstructure[$key]);
		}
		else
		{
			// Table doesn't exists, create
			phpAds_createTable ($key, $dbstructure[$key], $tabletype);
		}
	}
	
	return true;
}



/*********************************************************/
/* Upgrade the database to the latest structure          */
/*********************************************************/

function phpAds_createDatabase ($tabletype = '')
{
	// Get the database structure
	$dbstructure = phpAds_prepareDatabaseStructure();
	
	// Get table names
	$res = phpAds_dbQuery("SHOW TABLES");
	while ($row = phpAds_dbFetchRow($res))
		$availabletables[] = $row[0];
	
	for (reset($dbstructure);
		$key = key($dbstructure);
		next($dbstructure))
	{
		if (is_array($availabletables) && in_array ($key, $availabletables))
		{
			// Table exists, drop it
			phpAds_dropTable ($key);
		}
		
		// Table doesn't exists, create
		phpAds_createTable ($key, $dbstructure[$key], $tabletype);
	}
	
	return true;
}




/*********************************************************/
/* Upgrade a table to the latest structure               */
/*********************************************************/

function phpAds_upgradeTable ($name, $structure)
{
	$columns = $structure['columns'];
	if (isset($structure['primary'])) $primary = $structure['primary'];
	if (isset($structure['index']))   $index   = $structure['index'];
	if (isset($structure['unique']))  $unique  = $structure['unique'];
	
	// Get existing columns
		$availablecolumns[$row['Field']] = $row;
	
	// Change case of all columns to lower
	$res = phpAds_dbQuery("DESCRIBE ".$name);
	while ($row = phpAds_dbFetchArray($res))
	{
		if ($row['Field'] != strtolower($row['Field']))
		{
			// Change case
			$check = $row['Type'];
			if ($row['Default'] != '') $check .= " DEFAULT '".$row['Default']."'";
			if ($row['Null'] != 'YES') $check .= " NOT NULL";
			if (ereg('auto_increment', $row['Extra'])) $check .= " AUTO_INCREMENT";
			
			$query = "ALTER TABLE ".$name." CHANGE COLUMN ".$row['Field']." ".strtolower($row['Field'])." ".$check;
			
			phpAds_dbQuery($query);
		}
	}
	
	
	// Get existing columns
	$res = phpAds_dbQuery("DESCRIBE ".$name);
	while ($row = phpAds_dbFetchArray($res))
		$availablecolumns[$row['Field']] = $row;
	
	
	// Get existing indexes
	$res = phpAds_dbQuery("SHOW INDEX FROM ".$name);
	while ($row = phpAds_dbFetchArray($res))
		if ($row['Key_name'] != 'PRIMARY')
		{
			if ($row['Non_unique'] == 0)
				$availableunique[$row['Key_name']][] = $row['Column_name'];
			else
				$availableindex[$row['Key_name']][] = $row['Column_name'];
		}
		else
			$availableprimary[] = $row['Column_name'];
	
	
	// Check columns
	for (reset($columns); $key = key($columns);	next($columns))
	{
		$createdefinition = $key." ".$columns[$key];
		
		if (isset($availablecolumns[$key]) && is_array($availablecolumns[$key]))
		{
			// Column exists, check if it need updating
			$check = $availablecolumns[$key]['Type'];
			if ($availablecolumns[$key]['Default'] != '') $check .= " DEFAULT '".$availablecolumns[$key]['Default']."'";
			if ($availablecolumns[$key]['Null'] != 'YES') $check .= " NOT NULL";
			if (ereg('auto_increment', $availablecolumns[$key]['Extra'])) $check .= " AUTO_INCREMENT";
			
			if ($check != $columns[$key])
			{
				// Check if the column is a boolean
				if (ereg("enum\('t','f'\)", $columns[$key]) && $availablecolumns[$key]['Type'] == "enum('true','false')")
				{
					// Boolean found
					
					// Change to intermediate type first
					$intermediate = "enum('true','false','t','f')";
					if ($availablecolumns[$key]['Default'] != '') $intermediate .= " DEFAULT '".$availablecolumns[$key]['Default']."'";
					if ($availablecolumns[$key]['Null'] != 'YES') $intermediate .= " NOT NULL";
					if (ereg('auto_increment', $availablecolumns[$key]['Extra'])) $intermediate .= " AUTO_INCREMENT";
					phpAds_dbQuery("ALTER TABLE ".$name." MODIFY COLUMN ".$key." ".$intermediate);
					
					// Change values
					phpAds_dbQuery("UPDATE ".$name." SET ".$key." = 't' WHERE ".$key." = 'true'");
					phpAds_dbQuery("UPDATE ".$name." SET ".$key." = 'f' WHERE ".$key." = 'false'");
					
					// Okay, now continue and change the type to the new boolean
				}
				
				phpAds_dbQuery("ALTER TABLE ".$name." MODIFY COLUMN ".$createdefinition);
			}
		}
		else
		{
			// Column doesn't exist, create it
			phpAds_dbQuery("ALTER TABLE ".$name." ADD COLUMN ".$createdefinition);
		}
	}
	
	
	// Check Primary
	if (is_array($primary) && sizeof($primary) > 0)
	{
		if (!isset($availableprimary) || !is_array($availableprimary))
		{
			// Index does not exist, so create it
			phpAds_dbQuery("ALTER TABLE ".$name." ADD PRIMARY KEY (".implode(",", $primary).")");
		}
	}
	
	
	// Check Indexes
	if (is_array($index) && sizeof($index) > 0)
	{
		for (reset($index); $key = key($index);	next($index))
		{
			if (!isset($availableindex[$key]) || !is_array($availableindex[$key]))
			{
				// Index does not exist, so create it
				phpAds_dbQuery("ALTER TABLE ".$name." ADD INDEX ".$key." (".implode(",", $index[$key]).")");
			}
		}
	}
	
	
	// Check Unique Indexes
	if (is_array($unique) && sizeof($unique) > 0)
	{
		for (reset($unique); $key = key($unique); next($unique))
		{
			if (!isset($availableunique[$key]) || !is_array($availableunique[$key]))
			{
				// Index does not exist, so create it
				phpAds_dbQuery("ALTER TABLE ".$name." ADD UNIQUE ".$key." (".implode(",", $unique[$key]).")");
			}
		}
	}
}



/*********************************************************/
/* Create a table                                        */
/*********************************************************/

function phpAds_createTable ($name, $structure, $tabletype = '')
{
	$columns = $structure['columns'];
	if (isset($structure['primary'])) $primary = $structure['primary'];
	if (isset($structure['index']))   $index   = $structure['index'];
	if (isset($structure['unique']))  $unique  = $structure['unique'];
	
	// Create empty array
	$createdefinitions = array();
	
	// Add columns
	for (reset($columns); $key = key($columns);	next($columns))
		$createdefinitions[] = $key." ".$columns[$key];
	
	if (isset($primary) && is_array($primary))
		$createdefinitions[] = "PRIMARY KEY (".implode(",", $primary).")";
	
	if (isset($index) && is_array($index))
	{
		for (reset($index);$key=key($index);next($index))
			$createdefinitions[] = "KEY $key (".implode(",", $index[$key]).")";
	}
	
	if (isset($unique) && is_array($unique))
	{
		for (reset($unique);$key=key($unique);next($unique))
			$createdefinitions[] = "UNIQUE $key (".implode(",", $unique[$key]).")";
	}
	
	if (is_array($createdefinitions) &&
		sizeof($createdefinitions) > 0)
	{
		$query  = "CREATE TABLE $name (";
		$query .= implode (", ", $createdefinitions);
		$query .= ")";
		
		// Tabletype
		if ($tabletype != '')
			$query .= " TYPE=".$tabletype;
		
		phpAds_dbQuery($query);
	}
}



/*********************************************************/
/* Drop an existing table                                */
/*********************************************************/

function phpAds_dropTable ($name)
{
	return phpAds_dbQuery("DROP TABLE ".$name);
}


/*********************************************************/
/* Get table types                                       */
/*********************************************************/

function phpAds_getTableTypes ()
{
	// Assume MySQL always supports MyISAM table types
	$types['MYISAM'] = 'MyISAM';
	
	$res = phpAds_dbQuery("SHOW VARIABLES");
	while ($row = phpAds_dbFetchRow($res))
	{
		if ($row[0] == 'have_bdb' && $row[1] == 'YES')
			$types['BDB'] = 'Berkeley DB';
		
		if ($row[0] == 'have_gemini' && $row[1] == 'YES')
			$types['GEMINI'] = 'NuSphere Gemini';
		
		if ($row[0] == 'have_innodb' && $row[1] == 'YES')
			$types['INNODB'] = 'InnoDB';
	}
	
	return $types;
}



/*********************************************************/
/* Get the default table type                            */
/*********************************************************/

function phpAds_getTableTypeDefault ()
{
	$res = phpAds_dbQuery("SHOW VARIABLES");
	while ($row = phpAds_dbFetchRow($res))
	{
		if ($row[0] == 'table_type')
			return $row[1];
	}
	
	return false;
}



/*********************************************************/
/* Read the database structure from a sql file           */
/*********************************************************/

function phpAds_readDatabaseStructure ()
{
	global $phpAds_config;
	
	$sql = join("", file(phpAds_path."/misc/all.sql"));
	
	// Stripping comments
	$sql = ereg_replace("$-- [^\n]*\n", "\n", $sql);
	$sql = ereg_replace("$#[^\n]*\n", "\n", $sql);
	
	// Stripping (CR)LFs
	//$sql = str_replace("\r?\n\r?", "", $sql);
	$sql = str_replace("\n", " ", $sql);
	$sql = str_replace("\r", " ", $sql);
	
	
	// Unifying duplicate blanks
	$sql = ereg_replace("[[:blank:]]+", " ", $sql);
	
	$sql = explode(";", $sql);
	
	// Replacing table names to match config.inc.php
	for ($i=0;$i<sizeof($sql);$i++)
	{
		if (ereg ("CREATE TABLE (phpads_[^\(]*) \(", $sql[$i], $regs))
		{
			$tablename = str_replace ("phpads_", "tbl_", $regs[1]);
			
			if (isset($phpAds_config[$tablename]))
				$sql[$i] = str_replace ($regs[1], $phpAds_config[$tablename], $sql[$i]);
		}
	}
	
	// Create an array with an element for each query
	return $sql;
}



/*********************************************************/
/* Parse the an sql file and return all queries          */
/*********************************************************/

function phpAds_prepareDatabaseStructure()
{
	$dbstructure = array();
	
	// Read the all.sql file
	$queries = phpAds_readDatabaseStructure ();
	
	
	for ($i=0;$i<sizeof($queries)-1;$i++)
	{
		if (ereg ("CREATE TABLE ([^\(]*) \((.*)\)", $queries[$i], $regs))
		{
			$tablename   = $regs[1];
			$definitions = $regs[2];
			
			$definitions = explode (", ", $definitions);
			
			for ($j=0;$j<sizeof($definitions);$j++)
			{
				$definition = trim($definitions[$j]);
				
				if (ereg("^PRIMARY KEY \((.*)\)$", $definition, $regs))
				{
					$items = explode(",", $regs[1]);
					for ($k=0;$k<sizeof($items);$k++)
						$dbstructure[$tablename]['primary'][] = $items[$k];
				}
				elseif (ereg("^(KEY|INDEX) ([^ ]*) \((.*)\)$", $definition, $regs))
				{
					$items = explode(",", $regs[3]);
					for ($k=0;$k<sizeof($items);$k++)
						$dbstructure[$tablename]['index'][$regs[2]][] = $items[$k];
				}
				elseif (ereg("^UNIQUE ([^ ]*) \((.*)\)$", $definition, $regs))
				{
					$items = explode(",", $regs[2]);
					for ($k=0;$k<sizeof($items);$k++)
						$dbstructure[$tablename]['unique'][$regs[1]][] = $items[$k];
				}
				elseif (ereg("^([^ ]*) (.*)$", $definition, $regs))
				{
					$dbstructure[$tablename]['columns'][$regs[1]] = $regs[2];
				}
			}
		}
	}
	
	return $dbstructure;
}


?>