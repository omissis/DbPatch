<?php

$this->writer->line('Start patch 1');
$this->writer->line('DB: ' . $this->db->getMongoDB());
$this->writer->line('Collection: ' . $this->db->getMongoDB()->selectCollection('patch_1')->getName());

$collection = $this->db->getMongoDB()->selectCollection('patch_1');

$collection->insert(array('foo' => 'bar'));