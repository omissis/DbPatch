<?php

abstract class DbPatch_Command_Testcase extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $filesDir;

    /**
     * @var string
     */
    protected $configFile;

    /**
     * @var null|\Zend_Config|\Zend_Config_Ini|\Zend_Config_Xml
     */
    protected $config;

    /**
     * @var null|DbPatch_Core_Db
     */
    protected $db;

    /**
     * @var DbPatch_Core_Writer
     */
    protected $writer;

    /**
     * @var array
     */
    protected $writerOutput = array();


    public function __construct($configFile = 'dbpatch.ini')
    {
        $this->filesDir = realpath(__DIR__ . '/../../../__files');

        $this->configFile = $this->filesDir . '/' . $configFile;

        $config = new DbPatch_Core_Config($this->configFile);

        $this->config = $config->getConfig();

        // Hijack the patch directory for testing purposes
        $this->config->patch_directory = $this->filesDir . '/' . $this->config->patch_directory;

        $this->db = new DbPatch_Core_Db($this->config);

        $this->writer = $this->createWriterMock();
    }

    protected function createWriterMock()
    {
        $writer = $this->getMock('DbPatch_Core_Writer');

        $writerOutput =& $this->writerOutput;

        $loggerCallback = function ($arg) use (&$writer, &$writerOutput) {
            $lastOutput       = array_pop($writerOutput);
            $lastOutputLength = strlen($lastOutput);
            $isIndentation    = $lastOutputLength > 0;

            for ($i = 0; $i < $lastOutputLength; ++$i) {
                if ($lastOutput[$i] !== ' ') {
                    $isIndentation = false;
                    break;
                }
            }

            if ($isIndentation) {
                if ($lastOutput . $arg !== null) {
                    array_push($writerOutput, $lastOutput . $arg);
                }
            } else {
                if ($lastOutput !== null) {
                    array_push($writerOutput, $lastOutput);
                }
                array_push($writerOutput, $arg);
            }

            return $writer;
        };

        $separateCallback = function () use ($loggerCallback) {
            return $loggerCallback('----------------------------------');
        };

        $indentCallback = function ($arg) use (&$writer, &$writerOutput) {
            $writerOutput[] = str_repeat(' ', $arg);
            return $writer;
        };

        $writer->expects($this->any())
            ->method('line')
            ->will($this->returnCallback($loggerCallback));

        $writer->expects($this->any())
            ->method('warning')
            ->will($this->returnCallback($loggerCallback));

        $writer->expects($this->any())
            ->method('indent')
            ->will($this->returnCallback($indentCallback));

        $writer->expects($this->any())
            ->method('separate')
            ->will($this->returnCallback($separateCallback));

        return $writer;
    }

    public function getDbConfig()
    {
        return $this->db->getAdapter()->getConfig();
    }
}