<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace AbraFlexi\RaiffeisenBank;

/**
 * Description of Event
 *
 * @author vitex
 */
class Event extends \AbraFlexi\Udalost {

    public function createEvent($engine) {
        $eventData['typAkt'] = \AbraFlexi\Functions::code(\Ease\Functions::cfg('ABRAFLEXI_EVENT'));
        //$eventData['doklInt'] = TODO: Banka ?
        $statementFile = $engine->download(sys_get_temp_dir() . '/');
        $eventData['predmet'] = basename($statementFile);
        $eventData['id'] = 'ext:' . $eventData['predmet'];
        $this->setData($eventData);
        if ($this->sync()) {
            $attachment = \AbraFlexi\Priloha::addAttachmentFromFile($this, $statementFile);
            unlink($statementFile);
        }
    }

    //put your code here
}
