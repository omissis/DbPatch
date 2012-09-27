<?php

class DbPatch_Command_Status_MongoDBTest extends DbPatch_Command_Status_Testcase
{
    public function __construct()
    {
        parent::__construct('dbpatch_mongodb.ini');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->db->getAdapter()->getMongoDB()->drop();

        $this->object = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insertPatches(array $patches = array())
    {
        $collection = $this->db->getAdapter()->getMongoDB()->selectCollection($this->object->getCommandDbDelegate()->getChangelogContainerName());

        foreach ($patches as $patch) {
            $collection->insert($patch, array('safe' => true));
        }
    }

    public function testInit()
    {
        $commandDbDelegate = $this->initTestHelper('MongoDB', 'MongoDB');

        $dbConfig = $this->getDbConfig();

        $this->assertSame($dbConfig['dbname'], (string)$commandDbDelegate->getAdapter()->getMongoDB());
    }
}
