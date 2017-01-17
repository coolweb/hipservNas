<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../php/hipservNas.inc.php';

class AuthLoginResponse
{
    public $returnCode = '';
    public $authCode = '';
    public $nasUrl = '';
}

/*
 * Plugin for hipservNas.
 */
class hipservNas extends eqLogic
{
    private static $_eqLogics = null;

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
     */
    public static function cron()
    {
    }

    public function preInsert()
    {
    }

    public function postInsert()
    {
    }

    public function preSave()
    {
    }

    /**
     * Function called by jeedom after the save of a device.
     */
    public function postSave()
    {
        $logger = log::getLogger('hipservNas');
        log::add('hipservNas', 'debug', "Ajout d'un équipement");

        log::add('hipservNas', 'debug', "postSave: check logon to server");

        if($this->getConfiguration('nasName') != null)
        {
            $hipservServiceObj = new hipservNasService();
            $manufacturerCode = $this->getConfiguration('manufacturer');
            if($manufacturerCode !== null)
            {
                $hipservServiceObj->setDeviceManufacturer($manufacturerCode);
            }

            $errorMessage = $this->loginToHipservServer($hipservServiceObj);

            if ($errorMessage != '') {
                log::add('hipservNas', 'error', 'Unable to logon: ' . $errorMessage);
                throw new Exception($errorMessage);
            }
        }

        $cmd = $this->getCmd(null, 'shutdown');
        if (!is_object($cmd)) {
            $cmd = new hipservNasCmd();
            $cmd->setLogicalId('shutdown');
            $cmd->setName(__('Arrêter', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        $cmd = $this->getCmd(null, 'restart');
        if (!is_object($cmd)) {
            $cmd = new hipservNasCmd();
            $cmd->setLogicalId('restart');
            $cmd->setName(__('Redémarrer', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        $cmd = $this->getCmd(null, 'copyBackup');
        if (!is_object($cmd)) {
            $cmd = new hipservNasCmd();
            $cmd->setLogicalId('copyBackup');
            $cmd->setName(__('Copier le backup jeedom', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }
    }

    public function preUpdate()
    {
    }

    public function postUpdate()
    {
    }

    public function preRemove()
    {
    }

    public function postRemove()
    {
    }

    public function loginToHipservServer($hipservServiceObj)
    {
        $nasName = $this->getConfiguration('nasName');
        $user = $this->getConfiguration('user');
        $password = $this->getConfiguration('password');

        $errorMessage = $hipservServiceObj->getAuthentificationToken($nasName, $user, $password);

        if($errorMessage != '')
        {
            return $errorMessage;
        }

        $hipservServiceObj->logon();
    }

    public function shutdown($hipservServiceObj)
    {
        $hipservServiceObj->shutdown();
    }

    public function restart($hipservServiceObj)
    {
        $hipservServiceObj->restart();
    }

    public function copyBackup($hipservServiceObj, $nasPath)
    {        
        if($nasPath !== null && $nasPath !== '')
        {
            $hipservServiceObj->getUserInformation();
            $mediaSourceFolder = $hipservServiceObj->getDirectoryOfPath($nasPath);

            if($mediaSourceFolder === null)
            {
                log::add('hipservNas', 'error', 'Folder for backup not found: ' . $nasPath);
                return;
            }

            $backup_dir = calculPath(config::byKey('backup::path'));
            // get files
            $files = array_filter(scandir($backup_dir, 1), function($item) {
                 return !is_dir($backup_dir . $item);
            });

            if(count($files) === 0)
            {
                log::add('hipservNas', 'debug', 'No files in backup folder');
            } else 
            {
                log::add('hipservNas', 'debug', 'Backup file to copy: ' . $files[0]);
                $backupFile = $backup_dir . '/' . $files[0];
                $uploadResult = $hipservServiceObj->uploadFile($backupFile, $mediaSourceFolder);

                if($uploadResult !== '')
                {
                    log::add('hipservNas', 'error', 'Error uploading file for backup: ' . $uploadResult);
                }
            }
        } else {
            log::add('hipservNas', 'debug', 'Backup folder cmd configuration not set');
        }
    }
}

/**
 * Command class for hipservNas plugin.
 */
class hipservNasCmd extends cmd
{

    /**
     * Method called by jeedom when a command is executed on a device.
     */
    public function execute($_options = array())
    {
        if ($this->getType() == 'info') {
            return;
        }

        $logger = log::getLogger('hipservNas');
        log::add('hipservNas', 'debug', "command received: " . $this->getLogicalId());

        $hipservServiceObj = new hipservNasService();
        
        $manufacturerCode = $this->getEqLogic()->getConfiguration('manufacturer');
        if($manufacturerCode !== null)
        {
            $hipservServiceObj->setDeviceManufacturer($manufacturerCode);
        }

        $hipservNasDevice = $this->getEqLogic();
        $hipservNasDevice->loginToHipservServer($hipservServiceObj);

        if ($this->getLogicalId() == 'shutdown') 
        {
            log::add('hipservNas', 'info', "Extinction du disque dur");
            $hipservNasDevice->shutdown($hipservServiceObj);
        }

        if ($this->getLogicalId() == 'restart') 
        {
            log::add('hipservNas', 'info', "Redémarrage du disque dur");
            $hipservNasDevice->restart($hipservServiceObj);
        }

        if ($this->getLogicalId() == 'copyBackup') 
        {
            log::add('hipservNas', 'info', "Copie du backup");
            $nasPath = $this->getConfiguration('nasPath');
            $hipservNasDevice->copyBackup($hipservServiceObj, $nasPath);
        }
    }
}
