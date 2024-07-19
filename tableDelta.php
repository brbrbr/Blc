<?php

namespace Brambring;

use Joomla\Database;

class TableDelta
{
    protected $db;
    protected $db_prefix;

    public function __construct($db_host, $db_user, $db_password, $db_database, $db_prefix)
    {
        $dbFactory = new Database\DatabaseFactory();
        $this->db  = $dbFactory->getDriver(
            'mysqli',
            [
                'host'     => $db_host,
                'user'     => $db_user,
                'password' => $db_password,
                'database' => $db_database,
                'prefix'   => $db_prefix,
                'sqlModes' => ['ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'],
                //'socket' => $this->get('database.socket'),
                //'database' => $this->get('database.name'),
            ]
        );
        $this->db_prefix = $db_prefix;
    }
    private function getCol($query)
    {
        return $this->db->setQuery($query)->loadColumn();
    }

    private function getResult($query)
    {
        return $this->db->setQuery($query)->loadObject();
    }


    private function query($query)
    {
        return $this->db->setQuery($query)->execute();
    }
    private function uniformLines($line)
    {
        $line = trim($line);
        $line = preg_replace('# *AUTO_INCREMENT=[0-9]+ *#', ' ', $line);
        $line = preg_replace('#ibfk_[0-9]+#', 'constraint', $line);
        return $line;
    }
    /*
     * The 'success' and 'error_message' keys will only be present if $execute was set to True.
     *
     * @param string $queries One or more CREATE TABLE queries separated by a semicolon.
     * @param bool $execute Whether to apply the schema changes. Defaults to true.
     * @param bool $drop_columns Whether to drop columns not present in the input. Defaults to true.
     * @param bool $drop_indexes Whether to drop indexes not present in the input. Defaults to true.
     * @return array
     */
    public function delta($queries, $execute = false, $drop_columns = true, $drop_indexes = true)
    {


        $queries = $this->db->replacePrefix($queries);
        // Separate individual queries into an array
        if (!\is_array($queries)) {
            $queries = explode(';', $queries);
            if ('' == $queries[\count($queries) - 1]) {
                array_pop($queries);
            }
        }

        $cqueries   = []; // Creation Queries


        // Create a tablename index for an array ($cqueries) of queries
        foreach ($queries as $qry) {
            if (preg_match('|CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s(]+)|i', $qry, $matches)) {
                $table                = trim($matches[1], '`');
                $cqueries[$table]     = $qry;
            }
        }



        $tables = $this->getCol('SHOW TABLES;');

        if ($tables) {
            // For every table in the database
            foreach ($tables as $table) {
                // If a table query exists for the database table...
                if (\array_key_exists($table, $cqueries)) {
                    $createStatement = $this->getResult("SHOW CREATE TABLE $table");
                    $dbTable         = $createStatement->{'Create Table'};

                    $dbLines =
                        array_map(
                            [$this, 'uniformLines'],
                            explode("\n", $dbTable)
                        );

                    $updateLines =
                        array_map(
                            [$this, 'uniformLines'],
                            explode("\n", $cqueries[$table])
                        );
                    print "-- $table --\n";
                    $res = array_filter(array_diff($dbLines, $updateLines));
                    if ($res) {
                        var_dump($res);
                    }

                    $res = array_filter(array_diff($updateLines, $dbLines));
                    if ($res) {
                        var_dump($res);
                    }
                }
            }
        }
    }
}
