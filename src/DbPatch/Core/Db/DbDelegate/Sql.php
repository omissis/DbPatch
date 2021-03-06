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
 * @subpackage Core
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
 * SQL DbDelegate class for Core Db Class
 *
 * @package DbPatch
 * @subpackage Core
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
class DbPatch_Core_Db_DbDelegate_Sql extends DbPatch_Core_Db_DbDelegate_Abstract
{
    /**
     * This method provides backward compatibility for
     * the 'bin_dir' configuration option. it could be
     * removed in future versions. using bin_dir only
     * is not sufficient because it limits the user to
     * mysql/mysqldump. it's also not possible to use
     * bin_dir and pass a Zend_Db_Adapter instance as
     * configuration value.
     *
     * @return DbPatch_Core_Db
     */
    public function enableOldConfigCompatibility()
    {
        $options = '-h{host} {%port%}-P{port} {%port%}-u{username} {%password%}-p{password} {%password%}--default-character-set={charset} {dbname}';

        if (!isset($this->config->dump_command)) {
            $dir = '';

            if (isset($this->config->db->bin_dir)) {
                $dir = $this->config->db->bin_dir . DIRECTORY_SEPARATOR;
            }

            $this->config->dump_command = "{$dir}mysqldump {$options} > {filename} 2>&1";
        }

        if (!isset($this->config->import_command)) {
            $dir = '';

            if (isset($this->config->db->bin_dir)) {
                $dir = $this->config->db->bin_dir . DIRECTORY_SEPARATOR;
            }

            $this->config->import_command = "{$dir}mysql {$options} < {filename} 2>&1";
        }

        return $this;
    }
}