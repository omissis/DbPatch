<?php

class DbPatch_Command_Status_SqlTest extends DbPatch_Command_Status_Testcase
{
    public function __construct()
    {
        parent::__construct('dbpatch_sql.ini');
    }

    public function __destruct()
    {
        $dbConfig = $this->getDbConfig();

        $filename = $this->filesDir . '/' . basename($dbConfig['dbname']);

        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $dbConfig = $this->getDbConfig();

        try {
            $this->db->getAdapter()->query("DROP TABLE db_changelog");
        } catch (Exception $e) {
            // Just in case the table doesn't exists
        };

        $this->object = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insertPatches(array $patches = array())
    {
        foreach ($patches as $patch) {
            $sql = sprintf(
                'INSERT INTO %s (patch_number, branch, completed, filename, description, hash) VALUES (%d, "%s", %d, "%s", "%s", "%s")',
                'db_changelog',
                $patch['patch_number'],
                $patch['branch'],
                $patch['completed'],
                $patch['filename'],
                $patch['description'],
                $patch['hash']
            );

            $this->db->getAdapter()->query($sql);
        }
    }

    public function testInit()
    {
        $this->initTestHelper('Sql', 'PDO_Sqlite');

        $dbConfig = $this->getDbConfig();

        $filename = $this->filesDir . '/' . basename($dbConfig['dbname']);

        $this->assertTrue(file_exists($filename));
    }
}