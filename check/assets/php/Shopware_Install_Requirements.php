<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License and of our
 * proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Components
 * @subpackage Check
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware Check System
 *
 * todo@all: Documentation
 * <code>
 * $list = new Shopware_Components_Check_System();
 * $data = $list->toArray();
 * </code>
 */
class Shopware_Install_Requirements implements IteratorAggregate, Countable
{
    protected $list;

    protected $fatalError;

    /**
     * Checks all requirements
     */
    protected function checkAll()
    {
        foreach ($this->list as $requirement) {
            $version = $this->check($requirement->name);
            $requirement->result = $this->compare(
                (string) $requirement->name,
                $version,
                (string)$requirement->required
            );
            $requirement->version = $version;
        }
    }


    /**
     * Returns the check list
     *
     * @return Iterator
     */
    public function getList()
    {
        if ($this->list === null) {
            $xml_object = simplexml_load_file(dirname(__FILE__) . '/System.xml');
            if (is_object($xml_object->requirements) == true) {
                $this->list = $xml_object->requirement;
            }
            $this->checkAll();
        }
        return $this->list;
    }

    /**
     * Checks a requirement
     *
     * @param string $name
     * @return bool|null
     */
    protected function check($name)
    {
        $m = 'check' . str_replace(' ', '', ucwords(str_replace(array('_', '.'), ' ', $name)));
        if (method_exists($this, $m)) {
            return $this->$m();
        } elseif (extension_loaded($name)) {
            return true;
        } elseif (function_exists($name)) {
            return true;
        } elseif (($value = ini_get($name)) !== null) {
            if (strtolower($value) == 'off' || $value == 0) {
                return false;
            } elseif (strtolower($value) == 'on' || $value == 1) {
                return true;
            } else {
                return $value;
            }
        } else {
            return null;
        }
    }

    /**
     * Compares the requirement with the version
     *
     * @param string $name
     * @param string $version
     * @param string $required
     * @return bool
     */
    protected function compare($name, $version, $required)
    {
        $m = 'compare' . str_replace(' ', '', ucwords(str_replace(array('_', '.'), ' ', $name)));
        if (method_exists($this, $m)) {
            return $this->$m($version, $required);
        } elseif (preg_match('#^[0-9]+[A-Z]$#', $required)) {
            return $this->decodePhpSize($required) <= $this->decodePhpSize($version);
        } elseif (preg_match('#^[0-9]+ [A-Z]+$#i', $required)) {
            return $this->decodeSize($required) <= $this->decodeSize($version);
        } elseif (preg_match('#^[0-9][0-9\.]+$#', $required)) {
            return version_compare($required, $version, '<=');
        } else {
            return $required == $version;
        }
    }

    /**
     * Returns the check list
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return $this->getList();
    }

    /**
     * Checks the ion cube loader
     *
     * @return bool|string
     */
    public function checkIonCubeLoader()
    {
        if (!extension_loaded('ionCube Loader')) {
            return false;
        }
        ob_start();
        phpinfo(1);
        $s = ob_get_contents();
        ob_end_clean();
        if (preg_match('/ionCube&nbsp;PHP&nbsp;Loader&nbsp;v([0-9.]+)/', $s, $match)) {
            return $match[1];
        }
        return false;
    }

    /**
     * Checks the php version
     *
     * @return bool|string
     */
    public function checkPhp()
    {
        if (strpos(phpversion(), '-')) {
            return substr(phpversion(), 0, strpos(phpversion(), '-'));
        } else {
            return phpversion();
        }
    }

    /**
     * Checks the curl version
     *
     * @return bool|string
     */
    public function checkCurl()
    {
        if (function_exists('curl_version')) {
            $curl = curl_version();
            return $curl['version'];
        } elseif (function_exists('curl_init')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the lib xml version
     *
     * @return bool|string
     */
    public function checkLibXml()
    {
        if (defined('LIBXML_DOTTED_VERSION')) {
            return LIBXML_DOTTED_VERSION;
        } else {
            return false;
        }
    }

    /**
     * Checks the gd version
     *
     * @return bool|string
     */
    public function checkGd()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();
            if (preg_match('#[0-9.]+#', $gd['GD Version'], $match)) {
                if (substr_count($match[0], '.') == 1) {
                    $match[0] .= '.0';
                }
                return $match[0];
            }
            return $gd['GD Version'];
        } else {
            return false;
        }
    }

    /**
     * Checks the gd jpg support
     *
     * @return bool|string
     */
    public function checkGdJpg()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();
            return !empty($gd['JPEG Support']) || !empty($gd['JPG Support']);
        } else {
            return false;
        }
    }

    /**
     * Checks the freetype support
     *
     * @return bool|string
     */
    public function checkFreetype()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();
            return !empty($gd['FreeType Support']);
        } else {
            return false;
        }
    }

    /**
     * Checks the session save path config
     *
     * @return bool|string
     */
    public function checkSessionSavePath()
    {
        if (function_exists('session_save_path')) {
            return (bool)session_save_path();
        } elseif (ini_get('session.save_path')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the magic quotes config
     *
     * @return bool|string
     */
    public function checkMagicQuotes()
    {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            return true;
        } elseif (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the disk free space
     *
     * @return bool|string
     */
    public function checkDiskFreeSpace()
    {
        if (function_exists('disk_free_space')) {
            return $this->encodeSize(disk_free_space(dirname(__FILE__)));
        } else {
            return false;
        }
    }

    /**
     * Checks the include path config
     *
     * @return unknown
     */
    public function checkIncludePath()
    {
        if (function_exists('set_include_path')) {
            $old = set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR);
            return $old && get_include_path() != $old;
        } else {
            return false;
        }
    }

    /**
     * Compare max execution time config
     *
     * @param string $version
     * @param string $required
     * @return bool
     */
    public function compareMaxExecutionTime($version, $required)
    {
        if (!$version) {
            return true;
        }
        return version_compare($required, $version, '<=');
    }

    /**
     * Decode php size format
     *
     * @param string $val
     * @return float
     */
    public static function decodePhpSize($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (float)$val;
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    /**
     * Decode byte size format
     *
     * @param string $val
     * @return float
     */
    public static function decodeSize($val)
    {
        $val = trim($val);
        list($val, $last) = explode(' ', $val);
        $val = (float)$val;
        switch (strtoupper($last)) {
            case 'TB':
                $val *= 1024;
            case 'GB':
                $val *= 1024;
            case 'MB':
                $val *= 1024;
            case 'KB':
                $val *= 1024;
            case 'B':
                $val = (float)$val;
        }
        return $val;
    }

    /**
     * Encode byte size format
     *
     * @param float $bytes
     * @return string
     */
    public static function encodeSize($bytes)
    {
        $types = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $bytes >= 1024 && $i < (count($types) - 1); $bytes /= 1024, $i++) ;
        return (round($bytes, 2) . ' ' . $types[$i]);
    }

    /**
     *  Returns the check list
     *
     * @return array
     */
    public function toArray()
    {
        $list = array();
        foreach ($this->getList() as $requirement) {
            $listResult = array();

            $listResult['name'] = (string)$requirement->name;
            $listResult['isHardlyRequired'] = $requirement->weakRequired ? false : true;
            $listResult['hasNotice'] = (string)$requirement->hasNotice;
            $listResult['required'] = (string)$requirement->required;
            $listResult['version'] = (string)$requirement->version;
            $listResult['result'] = (string)$requirement->result;
            if (empty($listResult['result']) && $listResult['isHardlyRequired'] == true) {
                $this->setFatalError(true);
            }
            $list[] = $listResult;
        }
        return $list;
    }

    /**
     * Counts the check list
     *
     * @return int
     */
    public function count()
    {
        return $this->getList()->count();
    }

    public function setFatalError($fatalError)
    {
        $this->fatalError = $fatalError;
    }

    public function getFatalError()
    {
        return $this->fatalError;
    }
}