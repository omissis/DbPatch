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
 * Remove Command SQL DbDelegate class
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
class DbPatch_Command_Remove_DbDelegate_Sql extends DbPatch_Command_Remove_DbDelegate_Abstract
{
    /**
     * {@inheritdoc}
     */
    public function removePatch($patchNumber, $branchName)
    {
        $branchSQL = "";

        if (!empty($branchName)) {
            $branchSQL = sprintf("AND branch = '%s'", $branchName);
        }

        $query = sprintf(
            "SELECT branch FROM %s WHERE patch_number = %d {$branchSQL}",
             $this->changelogContainerName,
             $patchNumber
        );

        $stmt = $this->adapter->query($query);
        $patchRecords = $stmt->fetchAll();

        if (count($patchRecords) == 0) {
            $branchMsg = (empty($branchName) ? "" : "for branch '$branchName' ");
            $this->writer->line("Patch $patchNumber not found {$branchMsg} in `" . $this->changelogContainerName . "` table");
        }
        else if (count($patchRecords) > 1) {
            // @todo this is not happening anymore ???????
            $branchArray = array();
            foreach ($patchRecords as $branch) {
                $branchArray[] = $branch['branch'];
            }

            $this->writer->line("There's a patch '$patchNumber' in multiple branches: '" . implode("', '", $branchArray) . "'");
            $this->writer->line("Specify the correct branch by adding: 'branch=" . implode("' or 'branch=", $branchArray) . "' to the command");
        }
        else {
            $branchMsg = (empty($branchName) ? "" : "from branch '$branchName' ");
            $query = sprintf(
                "DELETE FROM %s WHERE patch_number = %d {$branchSQL}",
                $this->changelogContainerName,
                $patchNumber
            );

            $this->adapter->query($query);
            $this->writer->line("Removed patch $patchNumber {$branchMsg}in the `" . $this->changelogContainerName . "` table");
        }
    }
}