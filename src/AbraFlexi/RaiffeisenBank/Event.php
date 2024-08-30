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
    public function createEvent(Statementor $engine): void
    {
        $eventData['typAkt'] = \AbraFlexi\Functions::code(\Ease\Functions::cfg('ABRAFLEXI_EVENT'));
        // $eventData['doklInt'] = TODO: Banka ?
        $statementFiles = $engine->download(sys_get_temp_dir().'/');

        foreach ($statementFiles as $statementId => $statementFile) {
            [$month, $year, $accountNumner, $accountId, $currency, $firstDay] = explode('_', $statementId);
            $eventData['predmet'] = basename($statementFile);
            $eventData['popis'] = sprintf(_('Raiffeisen Bank Account %s Statement (%s)'), $accountNumner, $currency);
            $eventData['id'] = 'ext:'.$statementId;
            $eventData['termin'] = (new \AbraFlexi\Date($firstDay))->modify('+1 month');
            if ($this->recordExists(['id' => 'ext:'.$statementId])) {
                $this->addStatusMessage(sprintf(_('Record %s already exists'), $statementId));
            } else {
                $this->dataReset();
                $this->setData($eventData);
                if ($this->sync()) {
                    $attachment = \AbraFlexi\Priloha::addAttachmentFromFile($this, $statementFile);
                    $this->addStatusMessage(sprintf(_('The Statement was Attached to %s'), $this->getRecordIdent()), 'success');
                }
            }

            unlink($statementFile);
        }
    }
}
