<?php
/**
 * DbPatch
 *
 * Copyright (c) 2011, Sandy Pleyte.
 * Copyright (c) 2010-2011, Martijn De Letter.
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
 * @copyright 2011 Sandy Pleyte
 * @copyright 2010-2011 Martijn De Letter
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.github.com/dbpatch/DbPatch
 * @since File available since Release 1.0.0
 */

/**
 * Remove patch from the changelog command
 *
 * @package DbPatch
 * @subpackage Command
 * @author Sandy Pleyte
 * @author Martijn De Letter
 * @copyright 2011 Sandy Pleyte
 * @copyright 2010-2011 Martijn De Letter
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.github.com/dbpatch/DbPatch
 * @since File available since Release 1.0.0
 */
class DbPatch_Command_Remove extends DbPatch_Command_Abstract implements DbPatch_Command_Remove_DbDelegate_Interface
{
    /**
     * Initialize Command
     *
     * @return DbPatch_Command_Status
     */
    public function init()
    {
        parent::init();

        $commandDbDelegateClass = 'DbPatch_Command_Remove_DbDelegate_' . ucfirst(strtolower($this->config->db->adapter));

        $this->commandDbDelegate = new $commandDbDelegateClass();

        $this->commandDbDelegate->init($this->getDb()->getAdapter(), $this->getChangelogContainerName(), $this->getWriter());

        return $this;
    }

    /**
     * @return void
     */
    public function execute()
    {
        if ($this->console->issetOption('patch')) {
            $patchNumbers = explode(",", $this->console->getOptionValue('patch', null));
            $filteredPatchNumbers = array_filter($patchNumbers, 'is_numeric');

            if (empty($filteredPatchNumbers)) {
                $this->writer->error('no patch defined or patch isn\'t numeric');
                return;
            }

            $branch = $this->getBranch();
            foreach ($filteredPatchNumbers as $patchNumber) {
                $this->removePatch($patchNumber, $branch);
            }
            return;
        }
        $this->writer->error('No patch defined or patch isn\'t numeric');
        return;
    }

    /**
     * Remove patch from the changelog table
     *
     * @param int $patchNumber
     * @param string $branchName
     * @return void
     */
    public function removePatch($patchNumber, $branchName)
    {
        return $this->commandDbDelegate->removePatch($patchNumber, $branchName);
    }

    /**
     * @return void
     */
    public function showHelp($command = 'remove')
    {
        parent::showHelp($command);

        $writer = $this->getWriter();
        $writer->indent(2)->line('--patch=<int>      One or more patchnumbers seperated by a comma to remove')
                ->line();
    }
}
