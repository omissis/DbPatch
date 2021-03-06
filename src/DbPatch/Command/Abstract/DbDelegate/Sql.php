<?php
/**
 * DbPatch
 *
 * Copyright (c) 2011, Sandy Pleyte.
 * Copyright (c) 2010-2011, Martijn De Letter.
 * Copyright (c) 2012, Claudio Beatrice.
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  * Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 *  * Neither the name of the authors nor the names of his
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package DbPatch
 * @subpackage Command
 * @author Sandy Pleyte
 * @author Martijn De Letter
 * @author Claudio Beatrice
 * @copyright 2011 Sandy Pleyte
 * @copyright 2010-2011 Martijn De Letter
 * @copyright 2012 Claudio Beatrice
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.github.com/dbpatch/DbPatch
 * @since File available since Release 1.0.0
 */

/**
 * SQL DbDelegate class
 *
 * @package DbPatch
 * @subpackage Command
 * @author Sandy Pleyte
 * @author Martijn De Letter
 * @author Claudio Beatrice
 * @copyright 2011 Sandy Pleyte
 * @copyright 2010-2011 Martijn De Letter
 * @copyright 2012 Claudio Beatrice
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.github.com/dbpatch/DbPatch
 * @since File available since Release 1.0.0
 */
class DbPatch_Command_Abstract_DbDelegate_Sql extends DbPatch_Command_Abstract_DbDelegate_Abstract
{
    /**
     *
     * {@inheritdoc}
     */
    public function isPatchApplied($patchNumber, $branch)
    {
        $query = sprintf("SELECT COUNT(patch_number) as applied
                          FROM %s
                          WHERE patch_number = %d
                          AND branch = %s",
                         $this->adapter->quoteIdentifier($this->getChangelogContainerName()),
                         $patchNumber,
                         $this->adapter->quote($branch));

        $patchRecords = $this->adapter->fetchAll($query);

        if ((int)$patchRecords[0]['applied'] == 0) {
            return false;
        }

        return true;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function updateColumnType()
    {
        if (strstr(strtolower(get_class($this->adapter)), 'mysql')) {
            $columns = $this->adapter->describeTable($this->getChangelogContainerName());
            foreach($columns as $columnName => $meta) {
                if ($columnName == 'completed' && strtolower($meta['DATA_TYPE']) == 'timestamp') {
                    $this->adapter->query(sprintf("ALTER TABLE %s ADD completed2 int(11) NOT NULL DEFAULT 0 AFTER completed", $this->adapter->quoteIdentifier($this->getChangelogContainerName())));
                    $this->adapter->query(sprintf("UPDATE %s SET completed2 = UNIX_TIMESTAMP(completed)", $this->adapter->quoteIdentifier($this->getChangelogContainerName())));
                    $this->adapter->query(sprintf("ALTER TABLE %s DROP COLUMN completed, CHANGE completed2 completed INT(11) NOT NULL", $this->adapter->quoteIdentifier($this->getChangelogContainerName())));
                    $this->writer->line('Updated column type');
                }
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     */
    public function createChangelog()
    {
        if ($this->changelogExists()) {
            return true;
        }

        $this->adapter->query(
            sprintf("
             CREATE TABLE %s (
             patch_number int NOT NULL,
             branch varchar(50) NOT NULL,
             completed int,
             filename varchar(100) NOT NULL,
             hash varchar(32) NOT NULL,
             description varchar(200) default NULL,
             PRIMARY KEY  (patch_number, branch)
        )", $this->adapter->quoteIdentifier($this->getChangelogContainerName())
            ));


        if (!$this->changelogExists()) {
            return false;
        }

        $this->writer->line(sprintf("changelog table '%s' created", $this->getChangelogContainerName()));
        $this->writer->line("use 'dbpatch sync' to sync your patches")->line();

        return true;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function addToChangelog($patchFile, $description = null)
    {
        if ($description == null) {
            $description = $patchFile->description;
        }

        if ($this->isPatchApplied($patchFile->patch_number, $patchFile->branch)) {
             $this->writer->warning(
                 sprintf(
                     'Skip %s, already exists in the changelog',
                     $patchFile->basename
                 )
             );
         } else {
            $sql = sprintf("
                INSERT INTO %s (patch_number, branch, completed, filename, description, hash)
                VALUES(%d, %s, %d, %s, %s, %s)",
                           $this->adapter->quoteIdentifier($this->getChangelogContainerName()),
                           $patchFile->patch_number,
                           $this->adapter->quote($patchFile->branch),
                           time(),
                           $this->adapter->quote($patchFile->basename),
                           $this->adapter->quote($description),
                           $this->adapter->quote($patchFile->hash)
            );

            $this->adapter->query($sql);
            $this->writer->line(
                sprintf(
                    'added %s to the changelog ',
                    $patchFile->basename
                )
            );
        }
    }

    /**
     * Checks if the changelog table is present in the database
     * @return bool
     */
    public function changelogExists()
    {
        try {
            return in_array(
                $this->getChangelogContainerName(), $this->adapter->listTables()
            );
        } catch (Zend_Db_Adapter_Exception $e) {
            throw new DbPatch_Exception('Database error: ' . $e->getMessage());
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getDumpFilename($filename = null)
    {
        $config   = $this->adapter->getConfig();
        $database = $config['dbname'];

        if (is_null($filename)) {
            // split by slash, database name can be a path (in case of SQLite)
            $parts    = explode(DIRECTORY_SEPARATOR, $database);
            $filename = array_pop($parts) . '_' . date('Ymd_His') . '.sql';
        }

        if (isset($this->config->dump_directory)) {
            return $this->trimTrailingSlashes($this->config->dump_directory) . '/' . $filename;
        }

        return './' . $filename;
    }
}