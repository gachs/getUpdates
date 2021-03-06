<?php

/*
          Copyright (C) 2018 Enea Dolcini
          This file is part of getUpdates.
          getUpdates is free software: you can redistribute it and/or modify
          it under the terms of the GNU Affero General Public License as published by
          the Free Software Foundation, either version 3 of the License, or
          (at your option) any later version.
          getUpdates is distributed in the hope that it will be useful,
          but WITHOUT ANY WARRANTY; without even the implied warranty of
          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
          GNU Affero General Public License for more details.
          You should have received a copy of the GNU  Affero General Public License
          along with getUpdates.  If not, see http://www.gnu.org/licenses.
*/

namespace neneone\getUpdates;

class getUpdates
{
    use \neneone\getUpdates\Wrappers\serializedDatabase;

    public function __construct($settings)
    {
        $this->settingsScheme = [
      'token'  => true,
      'logger' => [
        'default' => true,
      ],
    ];
        $this->buildSettings($settings);
        $this->botAPI = new botAPI($this->settings['token']);
        $this->API = new API($this->botAPI->token);
        if (isset($this->settings['db']['unserialize_db_on_startup']) === false) {
            $this->settings['db']['unserialize_db_on_startup'] = true;
            \neneone\getUpdates\Logger::log('Non hai fornito l\'impostazione db.unserialize_db_on_startup che ha assunto il valore di true', \neneone\getUpdates\Logger::IMPORTANCE_MEDIUM);
        }
        if ($this->settings['db']['unserialize_db_on_startup'] == true) {
            $this->getDatabase();
        }
        if (isset($this->settings['plugins']) && is_array($this->settings['plugins'])) {
            foreach ($this->settings['plugins'] as $Plugin) {
                if (class_exists($Plugin)) {
                    $this->plugins[$Plugin] = new $Plugin($this->settings);
                }
            }
        }
        \neneone\getUpdates\Logger::log('getUpdatesBot inizializzato correttamente.', \neneone\getUpdates\Logger::IMPORTANCE_LOW, $this->settings);
    }

    private function buildSettings($settings, $settingsScheme = 0)
    {
        if ($settingsScheme == 0) {
            $settingsScheme = $this->settingsScheme;
        }
        foreach ($settingsScheme as $setting => $required) {
            if ($required === true && isset($settings[$setting]) == false) {
                throw new \neneone\getUpdates\Exception('Devi fornire l\'impostazione '.$setting.'!');
            } elseif (isset($settings[$setting]) == false && isset($settingsScheme[$setting]['default'])) {
                $settings[$setting] = $settingsScheme[$setting]['default'];
                \neneone\getUpdates\Logger::log('Non hai fornito l\'impostazione '.$setting.' che ha assunto il valore di '.$settingsScheme[$setting]['default'], \neneone\getUpdates\Logger::IMPORTANCE_MEDIUM);
            }
        }
        $this->settings = $settings;
    }

    public function setEventHandler($function)
    {
        if (is_callable($function)) {
            $this->settings['updates']['event_handler'] = $function;
        } else {
            throw new \neneone\getUpdates\Exception('L\'EventHandler deve essere una funzione valida!');
        }
    }

    public function loopUpdates($fork = false)
    {
        $offset = 0;
        $time = time();
        \neneone\getUpdates\Logger::log('Starting updates loop...');
        if ($fork == true) {
            while (true) {
                $updates = $this->botAPI->getUpdates(['offset' => $offset]);
                if (isset($this->settings['db']['serialization_interval'])) {
                    if ($time + $this->settings['db']['serialization_interval'] <= time()) {
                        $time = time();
                        $this->serializeDatabase();
                    }
                }
                foreach ($updates['result'] as $key => $value) {
                    $update = $updates['result'][$key];
                    $offset = $update['update_id'] + 1;
                    if (!pcntl_fork()) {
                        $this->settings['updates']['event_handler']($update);
                        exit;
                    }
                }
                $offset = (count($updates['result']) - 1) > 0 ? $updates['result'][count($updates['result']) - 1]['update_id'] + 1 : $offset;
            }
        } else {
            while (true) {
                $updates = $this->botAPI->getUpdates(['offset' => $offset]);
                if (isset($this->settings['db']['serialization_interval'])) {
                    if ($time + $this->settings['db']['serialization_interval'] <= time()) {
                        $time = time();
                        $this->serializeDatabase();
                    }
                }
                foreach ($updates['result'] as $key => $value) {
                    $update = $updates['result'][$key];
                    $offset = $update['update_id'] + 1;
                    $this->settings['updates']['event_handler']($update);
                }
                $offset = (count($updates['result']) - 1) > 0 ? $updates['result'][count($updates['result']) - 1]['update_id'] + 1 : $offset;
            }
        }
    }
}
