<?php

abstract class DbPatch_Command_Status_Testcase extends DbPatch_Command_Testcase
{
    /**
     * @var DbPatch_Command_Status
     */
    protected $object;

    /**
     * Set up the DbPatch_Command_Status object.
     */
    protected function setUp()
    {
        $this->object = new DbPatch_Command_Status();

        $this->object
            ->setConfig($this->config)
            ->setDb($this->db)
            ->setWriter($this->writer)
        ;
    }

    /**
     * Insert patch records in the database.
     */
    abstract protected function insertPatches(array $patches = array());

    /**
     * Fix the form of the inserted patches so that they're ready to be compared with
     * the output from the getAppliedPatches() method.
     */
    protected function preparePatchArrayForAssertion(array &$patches = array())
    {
        foreach ($patches as $i => $patch) {
            $patches[$i]['branch_order'] = (int)($patches[$i]['branch'] !== $this->object->getConfig()->default_branch);
            unset($patches[$i]['branch']);
        }
    }

    /**
     * Test the initialization method
     */
    protected function initTestHelper($delegateName, $adapterName)
    {
        $this->assertNull($this->object->getCommandDbDelegate());

        $this->assertInstanceOf('DbPatch_Command_Status', $this->object->init());

        $commandDbDelegate = $this->object->getCommandDbDelegate();

        $this->assertInstanceOf('DbPatch_Command_Status_DbDelegate_' . $delegateName, $commandDbDelegate);

        $this->assertInstanceOf('Zend_Db_Adapter_' . $adapterName, $commandDbDelegate->getAdapter());

        return $commandDbDelegate;
    }

    /**
     * Test the output of the execute method when there are no php patch files
     * in the patch_directory and no patches have been applied.
     */
    public function testExecuteWithNoPatchToApplyAndNoPatchApplied()
    {
        $this->config->patch_directory .= '/empty';

        $this->object->init();

        $this->object->execute();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "no patches found",
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "no patches found",
        );

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the output of the execute method when there are no php patch files
     * in the patch_directory and two patches have been applied:
     * it could happen if the patch files have been deleted.
     */
    public function testExecuteWithNoPatchToApplyAndTwoPatchesApplied()
    {
        $this->config->patch_directory .= '/empty';

        $this->object->init();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "no patches found",
            "",
            "patch-0002.php has been removed after it was applied on " . date('m-d-Y'),
            "patch-0001.php has been removed after it was applied on " . date('m-d-Y'),
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "0002 | " . date('m-d-Y') . " | patch-0002.php | bar",
            "0001 | " . date('m-d-Y') . " | patch-0001.php | foo",
        );

        $patches =
            array(
                array(
                    'patch_number' => 1,
                    'branch'       => 'default',
                    'completed'    => time(),
                    'filename'     => 'patch-0001.php',
                    'description'  => 'foo',
                    'hash'         => 'bar',
                ),
                array(
                    'patch_number' => 2,
                    'branch'       => 'test',
                    'completed'    => time() + 1,
                    'filename'     => 'patch-0002.php',
                    'description'  => 'bar',
                    'hash'         => 'baz',
                ),
            );

        $this->insertPatches($patches);

        $this->object->execute();

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the output of execute method when there are two php patch files
     * in the patch_directory and none of them have been applied.
     */
    public function testExecuteWithTwoPatchesToApplyAndNoPatchesApplied()
    {
        $this->config->patch_directory .= '/two_patches';

        $this->object->init();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "0001 | patch-0001.php | ",
            "0002 | patch-0002.php | ",
            "",
            "use 'dbpatch update' to apply the patches",
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "no patches found",
        );

        $this->object->execute();

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the output of execute method when there are two php patch files
     * in the patch_directory and both have been applied.
     */
    public function testExecuteWithTwoPatchesToApplyAndTwoPatchesApplied()
    {
        $this->config->patch_directory .= '/two_patches';

        $this->object->init();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "no patches found",
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "0002 | " . date('m-d-Y') . " | patch-0002.php | bar",
            "0001 | " . date('m-d-Y') . " | patch-0001.php | foo",
        );

        $patch1Hash = hash_file('crc32', $this->config->patch_directory . '/patch-0001.php');
        $patch2Hash = hash_file('crc32', $this->config->patch_directory . '/patch-0002.php');
        $patches = array(
            array(
                'patch_number' => 1,
                'branch'       => 'default',
                'completed'    => time(),
                'filename'     => 'patch-0001.php',
                'description'  => 'foo',
                'hash'         => $patch1Hash,
            ),
            array(
                'patch_number' => 2,
                'branch'       => 'default',
                'completed'    => time() + 1,
                'filename'     => 'patch-0002.php',
                'description'  => 'bar',
                'hash'         => $patch2Hash,
            ),
        );

        $this->insertPatches($patches);

        $this->object->execute();

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the output of execute method when there's one php patch files
     * in the patch_directory that has been modified after it was applied.
     */
    public function testExecuteWithOneModifiedPatchToApplyAndOnePatchApplied()
    {
        $this->config->patch_directory .= '/one_patch';

        $this->object->init();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "no patches found",
            "",
            "patch-0001.php has been changed since it's applied on " . date('m-d-Y'),
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "0001 | " . date('m-d-Y') . " | patch-0001.php | foo",
        );

        $patch = array(
            'patch_number' => 1,
            'branch'       => 'default',
            'completed'    => time(),
            'filename'     => 'patch-0001.php',
            'description'  => 'foo',
            'hash'         => 'wrongHash',
        );

        $this->insertPatches(array($patch));

        $this->object->execute();

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the output of execute method when there are two branches having
     * each one a php patch file in the patch_directory and none of them have been applied.
     */
    public function testExecuteWithTwoBranchesWithOnePatchToApplyEachAndNoPatchesApplied()
    {
        $this->config->patch_directory .= '/two_branches_with_one_patch_each';

        $this->object->init();

        $expectedMessages = array(
            "no changelog database found, try to create one",
            "changelog table 'db_changelog' created",
            "use 'dbpatch sync' to sync your patches",
            "",
            "patches to apply",
            "----------------------------------",
            "0001 | patch-0001.php | ",
            "",
            "use 'dbpatch update' to apply the patches",
            "",
            "patches to apply for branch 'test'",
            "----------------------------------",
            "0001 | patch-test-0001.php | ",
            "",
            "use 'dbpatch update --branch=test' to apply the patches",
            "",
            "applied patches (10 latest)",
            "----------------------------------",
            "no patches found",
        );

        $this->object->execute();

        $this->assertSame($expectedMessages, $this->writerOutput);
    }

    /**
     * Test the getAppliedPatches method when there are two php patch files
     * in the patch_directory and none of them have been applied.
     */
    public function testGetAppliedPatchesOnDefaultBranchWithNoAppliedPatches()
    {
        $this->config->patch_directory .= '/two_patches';

        $this->object->init();

        $this->assertSame(array(), $this->object->getAppliedPatches());
    }

    /**
     * Test the getAppliedPatches method when there are two php patch files
     * in the patch_directory and one of them have been applied.
     */
    public function testGetAppliedPatchesOnDefaultBranchWithOneAppliedPatch()
    {
        $this->config->patch_directory .= '/two_patches';

        $this->object->init();

        $patches = array(
            array(
                'patch_number' => 1,
                'branch'       => 'default',
                'completed'    => time(),
                'filename'     => 'patch-0001.php',
                'description'  => 'foo',
                'hash'         => 'bar',
            ),
        );

        $this->insertPatches($patches);

        $this->preparePatchArrayForAssertion($patches);

        $appliedPatches = $this->object->getAppliedPatches();

        $this->assertEquals($patches[0], array_pop($appliedPatches));
    }

    /**
     * Test the getAppliedPatches method when there are two php patch files
     * in the patch_directory and both of them have been applied, each one on its own branch.
     */
    public function testGetAppliedPatchesOnDifferentBranchesWithTwoAppliedPatches()
    {
        $this->config->patch_directory .= '/two_patches';

        $this->object->init();

        $patches =
            array(
                array(
                    'patch_number' => 1,
                    'branch'       => 'default',
                    'completed'    => time(),
                    'filename'     => 'patch-0001.php',
                    'description'  => 'foo',
                    'hash'         => 'bar',
                ),
                array(
                    'patch_number' => 2,
                    'branch'       => 'test',
                    'completed'    => time() + 1,
                    'filename'     => 'patch-0002.php',
                    'description'  => 'bar',
                    'hash'         => 'baz',
                ),
            );

        $this->insertPatches($patches);

        $this->preparePatchArrayForAssertion($patches);

        $appliedPatches = $this->object->getAppliedPatches();

        $this->assertEquals($patches[0], array_pop($appliedPatches));
        $this->assertEquals($patches[1], array_pop($appliedPatches));

        $appliedPatchesOnDefaultBranch = $this->object->getAppliedPatches('default');
        $this->assertEquals($patches[0], array_pop($appliedPatchesOnDefaultBranch));

        $appliedPatchesOnTestBranch    = $this->object->getAppliedPatches('test');
        $this->assertEquals($patches[1], array_pop($appliedPatchesOnTestBranch));
    }

    /**
     * Test the showHelp method
     */
    public function testShowHelp()
    {
        $this->object->showHelp();

        $expectedMessages = array(
            "usage: dbpatch status [<args>]",
            "",
            "The args are:",
            "  --config=<string>  Filename of the config file",
            "  --branch=<string>  Branch name",
            "  --color            Show colored output",
        );

        $this->assertSame($expectedMessages, $this->writerOutput);
    }
}
