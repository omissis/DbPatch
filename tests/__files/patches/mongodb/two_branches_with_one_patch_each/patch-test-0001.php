<?php

$this->writer->line('Start patch 2');

$collection = $this->db->getMongoDB()->selectCollection('patch_2');

$collection->insert(array('baz' => 'quux'));
