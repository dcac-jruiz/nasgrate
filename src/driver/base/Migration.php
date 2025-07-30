<?php

namespace Driver\Base;

use Process\Migration as MigrationConsole;

class Migration
{
    protected
        $_date = null,
        $_name = null,
        $_migrationId = null,
        $_isSkip = false,
        $_isExecuted = false,
        $_description = '',
        $_upSql = array(),
        $_downSql = array();

    /**
     * Migration constructor.
     */
    public function __construct($migrationId)
    {
        $this->_migrationId = $migrationId;
        preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $migrationId, $parts);
        $this->setDate($parts[3] . '.' . $parts[2] . '.' . $parts[1] . ' ' . $parts[4] . ':' . $parts[5] . ':' . $parts[6]);
        $this->_createFromFile();
    }

    private function _createFromFile()
    {
        $currentSection = null;
        $path = DIR_MIGRATION . '/' . $this->_migrationId . '.' . FILE_EXTENSION;
        if (!file_exists($path)) throw new Exception('File ' . $path . ' not found');
        
        $fileContent = file_get_contents($path);
        $lines = explode("\n", $fileContent);
        
        $subQuery = array();
        
        foreach ($lines as $str) {
            // Procesar headers del archivo
            if (preg_match('/ *-- *Skip *:/', $str)) {
                list($param, $value) = explode(':', $str, 2);
                $this->setIsSkip(trim($value) == 'yes');
                $currentSection = null;
                continue;
            }
            
            foreach (array('Name', 'Description', 'Date') as $field) {
                if (preg_match('/ *-- *' . $field . ' *:/', $str)) {
                    list($param, $value) = explode(':', $str, 2);
                    call_user_func_array(array($this, 'set' . trim($field)), array(trim($value)));
                    $currentSection = null;
                    continue 2;
                }
            }

            // Detectar secciones UP/DOWN
            if (preg_match('/ *-- *(UP|DOWN) *--/', $str, $s)) {
                // Guardar query anterior si existe
                if ($currentSection && $subQuery) {
                    $this->_processSubQuery($subQuery, $currentSection);
                }
                $currentSection = $s[1];
                $subQuery = array();
                continue;
            }

            // Acumular contenido de la sección actual
            if ($currentSection) {
                $subQuery[] = $str;
            }
        }
        
        // Procesar último subQuery
        if ($currentSection && $subQuery) {
            $this->_processSubQuery($subQuery, $currentSection);
        }
        
        return $this;
    }
    
    private function _processSubQuery($subQuery, $section)
    {
        // Eliminar líneas vacías al inicio y final
        while (count($subQuery) > 0 && trim($subQuery[0]) === '') {
            array_shift($subQuery);
        }
        while (count($subQuery) > 0 && trim($subQuery[count($subQuery)-1]) === '') {
            array_pop($subQuery);
        }
        
        if (empty($subQuery)) return;
        
        $content = implode("\n", $subQuery);
        
        // Detectar stored procedures y tratarlos como un solo bloque
        if (preg_match('/CREATE\s+(PROCEDURE|FUNCTION)/i', $content)) {
            // Todo el contenido como un solo statement
            call_user_func_array(array($this, 'add' . ucfirst(strtolower($section)) . 'Sql'), array($content));
        } else {
            // Para queries normales, dividir por líneas vacías dobles
            $statements = preg_split('/\n\s*\n/', $content);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement) {
                    call_user_func_array(array($this, 'add' . ucfirst(strtolower($section)) . 'Sql'), array($statement));
                }
            }
        }
    }

    public function addUpSql($sql)
    {
        if ($sql) $this->_upSql[] = $sql;
        return $this;
    }

    public function addDownSql($sql)
    {
        if ($sql) $this->_downSql[] = $sql;
        return $this;
    }

    public function getUpSql()
    {
        return $this->_upSql;
    }

    public function getDownSql()
    {
        return $this->_downSql;
    }

    public function getDownSqlItem($id)
    {
        if (isset($this->_downSql[$id])) return $this->_downSql[$id];
        return '';
    }

    /**
     * @return null|number
     */
    public function getMigrationId()
    {
        return $this->_migrationId;
    }

    /**
     * @param null $migrationId
     *
     * @return Migration
     */
    public function setMigrationId($migrationId)
    {
        $this->_migrationId = $migrationId;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isSkip()
    {
        return $this->_isSkip;
    }

    /**
     * @param boolean $isSkip
     *
     * @return Migration
     */
    public function setIsSkip($isSkip)
    {
        $this->_isSkip = $isSkip;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @param string $description
     *
     * @return string
     */
    public function setDescription($description)
    {
        $this->_description = $description;
        return $this;
    }

    /**
     * @return null
     */
    public function getDate()
    {
        return $this->_date;
    }

    /**
     * @param null $date
     */
    public function setDate($date)
    {
        $this->_date = $date;
        return $this;
    }

    /**
     * @return null
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param null $name
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isExecuted()
    {
        if (null === $this->_isExecuted) {
            $this->_isExecuted = in_array($this->getMigrationId(), MigrationConsole::getInstance()->getMigrationIdFromBase());
        }
        return $this->_isExecuted;
    }

    /**
     * @param boolean $isExecuted
     */
    public function setIsExecuted($isExecuted)
    {
        $this->_isExecuted = $isExecuted;
        return $this;
    }

    public function getClearName()
    {
        $name = explode("_", $this->_migrationId);
        array_shift($name);
        return implode(" ", $name);
    }

}