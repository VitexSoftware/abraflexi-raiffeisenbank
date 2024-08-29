<?php

declare(strict_types=1);

/**
 * This file is part of the AbraFlexi-RaiffeisenBank package
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\RaiffeisenBank;

/**
 * Description of Event.
 *
 * @author vitex
 */
class Event extends \AbraFlexi\Udalost
{
    public function createEvent($engine): void
    {
        $eventData['typAkt'] = \AbraFlexi\Functions::code(\Ease\Functions::cfg('ABRAFLEXI_EVENT'));
        // $eventData['doklInt'] = TODO: Banka ?
        $statementFile = $engine->download(sys_get_temp_dir().'/');
        $eventData['predmet'] = basename($statementFile);
        $eventData['id'] = 'ext:'.$eventData['predmet'];
        $this->setData($eventData);

        if ($this->sync()) {
            $attachment = \AbraFlexi\Priloha::addAttachmentFromFile($this, $statementFile);
            unlink($statementFile);
        }
    }

    // put your code here
}
