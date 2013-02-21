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
 * MongoDB DbDelegate class
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
class DbPatch_Command_Abstract_DbDelegate_Mongodb extends DbPatch_Command_Abstract_DbDelegate_Abstract
{
    public function selectCollection()
    {
        return $this->adapter->selectCollection($this->changelogContainerName);
    }

    /**
     *
     * {@inheritdoc}
     */
    public function isPatchApplied($patchNumber, $branch)
    {
        $cursor = $this->selectCollection()->find(array(
            'patch_number' => $patchNumber,
            'branch'       => $branch
        ));

        return $cursor->count() === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function updateColumnType()
    {
        // NOTE Does this method make sense for MongoDB?
    }

    /**
     * {@inheritdoc}
     */
    public function createChangelog()
    {
        if ($this->changelogExists()) {
            return true;
        }

        // Create Collection
        $collection = $this->adapter->getMongoDB()->createCollection($this->changelogContainerName);

        // Create a unique index to make sure that there are no duplicates
        // in the db that could break the patches flow due to bugs
        $collection->ensureIndex(array(
            'patch_number' => 1,
            'branch'       => 1,
        ), array(
            'unique'   => true,
            'dropDups' => true,
            'safe'     => true,
        ));

        if (!$this->changelogExists()) {
            return false;
        }

        $this->writer->line(sprintf("changelog table '%s' created", $this->changelogContainerName));
        $this->writer->line("use 'dbpatch sync' to sync your patches")->line();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addToChangelog($patchFile, $description = null)
    {
        if ($description == null) {
            $description = $patchFile->description;
        }

        if ($this->isPatchApplied($patchFile->patch_number, $patchFile->branch)) {
            $this->writer->warning(sprintf(
                'Skip %s, already exists in the changelog',
                $patchFile->basename
            ));
        } else {
            $this->selectCollection()->insert(array(
                'patch_number' => $patchFile->patch_number,
                'branch'       => $patchFile->branch,
                'completed'    => time(),
                'filename'     => $patchFile->basename,
                'description'  => $description,
                'hash'         => $patchFile->hash,
            ), array('safe' => true));

            $this->writer->line(sprintf(
                'added %s to the changelog ',
                $patchFile->basename
            ));
        }
    }

    /**
     * Checks if the changelog collection is present in the database
     *
     * @return boolean
     */
    public function changelogExists()
    {
        $collections = $this->adapter->listCollections();
        $changelogContainerName = $this->changelogContainerName;
        foreach ($collections as $collection) {
            if ($changelogContainerName === $collection->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getDumpFilename($filename = null)
    {
        return empty($filename) ? 'dump' : $filename;
    }
}
