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
 * Update command
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
class DbPatch_Command_Update extends DbPatch_Command_DelegateAbstract implements DbPatch_Command_Update_DbDelegate_Interface
{
    /**
     * Initialize Command
     *
     * @return DbPatch_Command_Status
     */
    public function init()
    {
        parent::init();

        $commandDbDelegateClass = $this->getDbDelegateClass('DbPatch_Command_Update_DbDelegate_');

        $this->commandDbDelegate = new $commandDbDelegateClass();

        $this->commandDbDelegate->init($this->getDb()->getAdapter(), $this->getChangelogContainerName(), self::DEFAULT_BRANCH);

        return $this;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $branch = $this->getBranch();
        $force = ($this->console->issetOption('force')) ? true : false;
        $createDump = isset($this->config->dump_before_update) ? $this->config->dump_before_update : false;

        $latestPatchNumber = $this->getLastPatchNumber($branch);

        if ($branch != self::DEFAULT_BRANCH) {
            $this->writer->line('Branch: ' . $branch);
        }
        $this->writer->line('last patch number applied: ' . $latestPatchNumber);
        $patchFiles = $this->getPatches($branch);

        if (count($patchFiles) == 0) {
            $this->writer->success("no update needed " . ($branch != self::DEFAULT_BRANCH ? 'for branch ' . $branch : ''));
            return;
        }

        $this->writer->line(sprintf(
            'found %d patch %s',
            count($patchFiles),
            (count($patchFiles) == 1) ? 'file' : 'files'
        ));

        if ($createDump) {
            $config = $this->getDb()->getAdapter()->getConfig();
            $database = $config['dbname'];
            $filename = $this->getDumpFilename();

            $this->writer->line('Dumping database ' . $database . ' to file ' . $filename);
            $this->dumpDatabase($filename);
        }

        $patchNumbersToSkip = $this->getPatchNumbersToSkip($this->console->getOptions(), $patchFiles);

        if (count($patchNumbersToSkip)) {
            $this->writer->line('Skip patchnumbers: ' . implode(',', $patchNumbersToSkip));
        }

        foreach ($patchFiles as $patchNr => $patchFile) {
            if (($patchFile->patch_number <> $latestPatchNumber + 1) && !$force) {
                $this->writer->error(
                    sprintf('expected patch number %d instead of %d (%s). Use --force to override this check.',
                            $latestPatchNumber + 1,
                            $patchFile->patch_number,
                            $patchFile->basename
                    )
                );
                return;
            }

            if (in_array($patchNr, $patchNumbersToSkip)) {
                $this->writer->line('manually skipped patch ' . $patchFile->basename);
                $this->addToChangelog($patchFile, 'manually skipped');
                $latestPatchNumber = $patchFile->patch_number;
                continue;
            }

            $result = $patchFile->setDb($this->db)
                    ->setConfig($this->config)
                    ->setWriter($this->writer)
                    ->apply();

            if (!$result) {
                return;
            }

            $this->addToChangelog($patchFile);
            $latestPatchNumber = $patchFile->patch_number;

        }
    }

    /**
     * Returns the last applied patch number from the database
     *
     * @param  string $branch
     * @return int
     */
    protected function getLastPatchNumber($branch)
    {
        $patch = $this->getAppliedPatches($branch);

        if (empty($patch)) {
            return 0;
        }

        return $patch['patch_number'];
    }

    /**
     * Return the already applied patches from the changelog table
     *
     * @param string $branch
     *
     * @return array
     */
    public function getAppliedPatches($branch = '')
    {
        return $this->commandDbDelegate->getAppliedPatches($branch);
    }


    /**
     * Determine which patch numbers can be skipped
     * We may only skip numbers that are ready to apply
     *
     * These patches will not be executed and marked as skipped in the changelog
     *
     * @param array $params commandline params
     * @param array $patchFiles patches that are ready to apply
     * @return array $patchNumbers patchnumbers to skip
     */
    protected function getPatchNumbersToSkip($params, $patchFiles)
    {
        if (!isset($params['skip'])) {
            return array();
        }

        // requested numbers to skip
        $patchNumbers = explode(",", $params['skip']);

        // we may only skip numbers that are ready to apply
        $readyToApplyPatches = array_keys($patchFiles);

        // check which patchnumbers match
        $validPatchNumbers = array_intersect($patchNumbers, $readyToApplyPatches);

        return $validPatchNumbers;
    }

    /**
     * @param string $command Command name
     * @return void
     */
    public function showHelp($command = 'update')
    {
        parent::showHelp($command);
        $writer = $this->getWriter();
        $writer
                ->indent(2)->line('--skip=<int>       One or more patchnumbers seperated by a comma to skip')
                ->indent(2)->line('--force            Force the update, and ignore missing patches')
                ->line();
    }
}
