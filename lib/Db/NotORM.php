<?php


include_once dirname(__FILE__)."/Structure.php";
include_once dirname(__FILE__)."/Cache.php";
include_once dirname(__FILE__)."/Literal.php";
include_once dirname(__FILE__)."/Result.php";
include_once dirname(__FILE__)."/MultiResult.php";
include_once dirname(__FILE__)."/Row.php";

// friend visibility emulation
abstract class NotORM_Abstract
{
    protected $connection, $driver, $structure, $cache;
    protected $notORM, $table, $primary, $rows, $referenced = array();

    protected $debug = false;
    protected $freeze = false;
    protected $rowClass = 'NotORM_Row';

    protected function access($key, $delete = false)
    {
    }
}

/** Database representation
 * @property-write mixed $debug = false Enable debuging queries, true for fwrite(STDERR, $query), callback($query, $parameters) otherwise
 * @property-write bool $freeze = false Disable persistence
 * @property-write string $rowClass = 'NotORM_Row' Class used for created objects
 * @property-write string $transaction Assign 'BEGIN', 'COMMIT' or 'ROLLBACK' to start or stop transaction
 */
class NotORM extends NotORM_Abstract
{
    /** Create database representation
     * @param PDO
     * @param NotORM_Structure or null for new NotORM_Structure_Convention
     * @param NotORM_Cache or null for no cache
     */
    public function __construct(PDO $connection, NotORM_Structure $structure = null, NotORM_Cache $cache = null, $charset = true)
    {
        $this->connection = $connection;
        $this->driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!isset($structure)) {
            $structure = new NotORM_Structure_Convention();
        }
        $this->structure = $structure;
        $this->cache = $cache;

        if (defined('DB_CHARSET') && $charset == true) {
            $this->connection->prepare(sprintf('SET NAMES %s COLLATE %s', DB_CHARSET, DB_COLLATE))->execute();
        }
    }

    /** Get table data to use as $db->table[1]
     * @param string
     * @return NotORM_Result
     */
    public function __get($table)
    {
        return new NotORM_Result($this->structure->getReferencingTable($table, ''), $this, true);
    }

    /** Set write-only properties
     * @return null
     */
    public function __set($name, $value)
    {
        if ($name == "debug" || $name == "freeze" || $name == "rowClass") {
            $this->$name = $value;
        }
        if ($name == "transaction") {
            switch (strtoupper($value)) {
                case "BEGIN": return $this->connection->beginTransaction();
                case "COMMIT": return $this->connection->commit();
                case "ROLLBACK": return $this->connection->rollback();
            }
        }
    }

    /** Get table data
     * @param string
     * @param array (["condition"[, array("value")]]) passed to NotORM_Result::where()
     * @return NotORM_Result
     */
    public function __call($table, array $where)
    {
        if ($table == 'query') {
            $this->connection->prepare($where[0])->execute();
        } else {
            $return = new NotORM_Result($this->structure->getReferencingTable($table, ''), $this);
            if ($where) {
                call_user_func_array(array($return, 'where'), $where);
            }

            return $return;
        }
    }
}
