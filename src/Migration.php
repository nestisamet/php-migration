<?php

namespace YD;

use YD\Exception\MigrationException;
use PDO;

/**
 * Class Migration
 *
 * @category YD
 * @package  YD
 * @author   Samet TEMIZER <nestisamet@gmail.com>
 * @license  MIT License
 * @link     https://stemizer.com
 */
class Migration
{

    protected $dbh;
    protected $scriptsPath;

    /**
     * Migration constructor.
     *
     * @param PDO $pdoObj      pdo-instance
     * @param $scriptsPath sql-files
     */
    public function __construct(PDO $pdoObj, $scriptsPath)
    {
        $this->scriptsPath = $scriptsPath;
        $this->dbh = $pdoObj;
        $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->_validateArguments();
        $this->_requirements();
    }

    /**
     * Desc
     *
     * @throws MigrationException
     * @return null
     */
    private function _validateArguments()
    {
        if (!$this->dbh instanceof PDO) {
            throw new MigrationException(
                [
                'error' => 'argument-I not a pdo instance',
                ]
            );
        }
        if (!is_dir($this->scriptsPath)) {
            throw new MigrationException(
                [
                'error' => 'argument-II is not a directory',
                ]
            );
        }
    }

    /**
     * Execute migration scripts
     *
     * @return null
     */
    public function run()
    {
        $last = $this->dbh->query("select `i` from `_migration` order by `i` desc")
            ->fetch();
        $migration_last = ($last) ? $last['i'] : -1;
        $migrations_path = $this->scriptsPath;
        $migrations = [];
        foreach (scandir($migrations_path) as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }
            $migrations[] = $file;
        }
        if (!($sizeof=sizeof($migrations)) || $migration_last >= $sizeof-1) {
            exit;
        }
        $i = $migration_last;
        do {
            $i++;
            $script = $migrations[$i];
            // shell_exec("php $migrations_path/$script");
            $this->dbh->beginTransaction();
            try {
                if (!($statement = file_get_contents("$migrations_path/$script"))) {
                    continue;
                }
                if ($this->dbh->query($statement) === false) {
                    throw new MigrationException(
                        [
                        'script' => $script,
                        'error' => $this->dbh->errorInfo()
                        ]
                    );
                }
                $this->dbh->exec(
                    "
                    insert into `_migration`(`i`, `migration`)
                    values ($i, '{$script}')
                "
                );
                $this->dbh->commit();
            }
            catch (MigrationException $e) {
                $this->dbh->rollBack();
                // todo: log migrationExc
                // print_r($e->getMsg());
            }
            catch (PDOException $e) {
                $this->dbh->rollBack();
                // todo: log pdoExc
            }
            catch (Exception $e) {
                $this->dbh->rollBack();
                // todo: log unexpected
            }
        } while ($i < $sizeof-1);
    }

    /**
     * Structural requirements
     * 
     * @return null
     */
    private function _requirements()
    {
        $sth = $this->dbh->query('SHOW TABLES LIKE "_migration"');
        if (!$sth->rowCount()) {
            $this->dbh->query(
                '
                CREATE TABLE `_migration` (
                    `i` SMALLINT NOT NULL,
                    `migration` VARCHAR(100) NOT NULL
                );
            '
            );
        }
    }
}