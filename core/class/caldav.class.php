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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
//require_once dirname(__FILE__) . '/../../3rdparty/caldav-client.php';
if ( ! class_exists ("SimpleCalDAVClient") )
{
	require_once dirname(__FILE__) . '/../../3rdparty/simpleCalDAV/SimpleCalDAVClient.php';
}
//require_once dirname(__FILE__) . '/../../3rdparty/caldav-client-v2.php';


class caldav extends eqLogic {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

	public static function cron() {
		foreach (self::byType('caldav') as $eqLogic) {
			$eqLogic->pull();
		}
	}

	public function preUpdate()
	{
		if ( $this->getIsEnable() )
		{
			return $this->pull();
		}
	}

    public function pull() {
		if ( $this->getIsEnable() ) {
			try {
				$desc_event = array();
				$events = array();
				$time = mktime();
				$client = new SimpleCalDAVClient();
				$client->connect($this->getConfiguration('url'), $this->getConfiguration('username'), $this->getConfiguration('password'));
				log::add('caldav', 'info', 'Find calendar');
				$arrayOfCalendars = $client->findCalendars();
				log::add('caldav', 'info', 'Trouve '.print_r($arrayOfCalendars, true));
				log::add('caldav', 'info', 'Chose calendar');
				$client->setCalendar($arrayOfCalendars["thomas"]);
				log::add('caldav', 'info', 'Recupere les évenements entre '.date("Ymd\THi00\Z", $time).' et '.date("Ymd\THi59\Z", $time));
				$events = $client->getEvents(date("Ymd\THi00\Z", $time),date("Ymd\THi59\Z", $time));
				log::add('caldav', 'info', 'Trouve '.count($events).' events');
				foreach ( $events AS $event ) {
					$data = $event->getData();
					log::add('caldav', 'info', 'Event => '.print_r($data, true));
					foreach ( explode("\n", $data) AS $info) {
						log::add('caldav', 'info', 'info : '.$info);
						if ( preg_match("!^(.*):(.*)$!", $info, $regs) ) {
							if ( $regs[1] == "SUMMARY" ) {
								log::add('caldav', 'info', 'Trouve '.$regs[2]);
								array_push($desc_event, $regs[2]);
							}
						}
					}
					break;
				}
				log::add('caldav', 'info', 'Recherche correspondance cmd');
				foreach ($this->getCmd('info') as $cmd) {
					$value = $cmd->extract($desc_event);
					if ($value != $cmd->execCmd()) {
						$cmd->setCollectDate(date('Y-m-d H:i:s'));
						$cmd->event($value);
					}
				}
			} catch (Exception $e) {
				log::add('caldav', 'info', 'URL non valide ou accès internet invalide : ' .  $e->__toString());
#				throw $e;
			}
		}
    }

    /*     * *********************Methode d'instance************************* */
}

class caldavCmd extends cmd {
    public function preSave() {
        $this->setEventOnly(1);
    }

    public function extract($events = array()) {
		$result = array();
		if ( count($events) != 0 ) {
			foreach ( $events AS $event ) {
				if ( $this->getConfiguration('pattern') == '' ) {
					log::add('caldav', 'info', 'Correspond sans pattern');
					array_push($result, $event);
				} elseif ( preg_match($this->getConfiguration('pattern'), $event, $regs) ) {
					log::add('caldav', 'info', 'Correspond avec pattern et trouve : '.$regs["1"]);
					if ( !isset($regs["1"]) || $regs["1"] == '' ) {
						array_push($result, $event);
					} else {
						array_push($result, $regs["1"]);
					}
				}
			}
		}
		if (count($result) == 0) {
			log::add('caldav', 'info', 'Acune valeur trouve');
			if ($this->getConfiguration('defaultValue') == '') {
				return __('Aucun', __FILE__);
			} else {
				return $this->getConfiguration('defaultValue');
			}
		}
        return join(';', $result);
    }

    public function execute($_options = array()) {
		$EqLogic = $this->getEqLogic();
		$EqLogic->pull();
		return $this->execCmd();
	}
    /*     * **********************Getteur Setteur*************************** */
}
?>
