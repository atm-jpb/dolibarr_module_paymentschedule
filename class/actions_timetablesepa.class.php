<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_timetablesepa.class.php
 * \ingroup timetablesepa
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionstimetableSEPA
 */
class ActionstimetableSEPA
{
    /**
     * @var DoliDb		Database handler (result of a new DoliDB)
     */
    public $db;

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
     * @param DoliDB    $db    Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		$TContext = explode(':',$parameters['context']);

		if (in_array('invoicecard', $TContext))
		{
			if ($action == 'confirm_createtimetablesepa')
			{
                if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
				dol_include_once('timetablesepa/class/timetablesepa.class.php');

				$date_start = dol_mktime(12, 0, 0, GETPOST('date_startmonth'), GETPOST('date_startday'), GETPOST('date_startyear'));
				$periodicity_unit = GETPOST('periodicity_unit');
				$periodicity_value = GETPOST('periodicity_value', 'int');
				$nb_term = GETPOST('nb_term', 'int');

				$echeancier = new TimetableSEPA($this->db);
				$ret = $echeancier->createFromFacture($object, $date_start, $periodicity_unit, $periodicity_value, $nb_term);
				if ($ret < 0)
				{
					setEventMessage($echeancier->errors, "errors");
				}
				else
                {
                    header('Location: '.dol_buildpath('/timetablesepa/card.php?id='.$echeancier->id, 1));
                    exit;
                }
			}
		}
		elseif (in_array('levycard', $TContext))
        {
            if ($action === 'delete')
            {
                $did = GETPOST('did', 'int');
                if ($did > 0)
                {
                    $sql = 'SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element 
                        WHERE fk_source = '.$did.' AND sourcetype = \'prelevement_facture_demande\'
                        AND targettype = \'timetablesepadet\'';

                    $resql = $this->db->query($sql);
                    if ($resql)
                    {
                        $obj = $this->db->fetch_object($resql);
                        if ($obj)
                        {
                            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
                            dol_include_once('timetablesepa/class/timetablesepa.class.php');
                            $det = new TimetableSEPADet($this->db);
                            $det->fetch($obj->fk_target);

                            $det->setWaiting($user);
                            $det->deleteObjectLinked($did, 'prelevement_facture_demande');
                        }
                    }
                    else
                    {
                        setEventMessage($this->db->lasterror(), 'errors');
                    }

                }
            }
//            var_dump($action);exit;
        }

		return 0;
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$TContext = explode(':',$parameters['context']);

		if (in_array('invoicecard', $TContext))
		{
			if (empty($object->array_options))
			{
				$object->fetch_optionals();
			}

			// vérifier qu'on a bien l'extrafield isecheancier à true
			if (!empty($object->array_options['options_isecheancier']) && !empty($user->rights->timetablesepa->write))
			{
                if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
				dol_include_once('/timetablesepa/class/timetablesepa.class.php');

				$TRestrictMessage = TimetableSEPA::checkFactureCondition($object);
				if (empty($TRestrictMessage))
				{
					print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?facid='.$object->id.'&action=createtimetablesepa">'.$langs->trans('timetableSEPACreate').'</a></div>';
				}
				else
				{
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.implode('<br />', $TRestrictMessage).'">'.$langs->trans('timetableSEPACreate').'</a></div>';
				}
			}
		}

		return 0;
	}

	public function formConfirm($parameters, &$object, &$action, $hookmanager)
    {
        $TContext = explode(':',$parameters['context']);

        if (in_array('invoicecard', $TContext))
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('timetablesepa/lib/timetablesepa.lib.php');

            $form = new Form($this->db);
            $this->resprints = getFormConfirmtimetableSEPA($form, null, $object, $action);
        }

        return 0;
    }

    /**
     * Permet de retirer l'onglet "Echéancier" sur la fiche d'une facture s'il n'en a pas de créé pour éviter à l'utilisateur de cliquer dessus
     * @param $parameters
     * @param $object
     * @param $action
     * @param $hookmanager
     * @return int
     */
	public function completeTabsHead($parameters, &$object, &$action, $hookmanager)
    {
        $TContext = explode(':',$parameters['context']);

        if (in_array('invoicecard', $TContext) && $parameters['mode'] == 'remove')
        {
            $head = $parameters['head'];
            if (!empty($head))
            {
                foreach ($head as $k => $info)
                {
                    if ($info[2] === 'timetablesepacard')
                    {
                        if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
                        dol_include_once('/timetablesepa/class/timetablesepa.class.php');
                        $TimetableSEPA = new TimetableSEPA($this->db);
                        $TimetableSEPA->fetchBy($parameters['object']->id, 'fk_facture');
                        if (empty($TimetableSEPA->id))
                        {
                            unset($head[$k]);
                            $this->results = $head;
                            return 1;
                        }

                        break;
                    }
                }
            }
        }

        return 0;
    }

    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        $TContext = explode(':',$parameters['context']);
        if (in_array('levycreatecard', $TContext))
        {
            if (GETPOST('action') === 'create')
            {
                $sql = 'SELECT MAX(rowid) as last_id FROM '.MAIN_DB_PREFIX.'prelevement_bons';
                $resql = $this->db->query($sql);
                if ($resql)
                {
                    $obj = $this->db->fetch_object($resql);
                    $fk_prelevement_bons = $obj->last_id;
                    //var_dump($fk_prelevement_bons);
                    $sql = 'SELECT pfd.rowid, ee.fk_target
                            FROM '.MAIN_DB_PREFIX.'prelevement_facture_demande pfd
                            INNER JOIN '.MAIN_DB_PREFIX.'element_element ee ON (ee.fk_source = pfd.rowid AND ee.sourcetype = \'prelevement_facture_demande\')
                            WHERE pfd.fk_prelevement_bons = '.$fk_prelevement_bons.'
                            AND ee.targettype = \'timetablesepadet\'';

                    $resql = $this->db->query($sql);
                    if ($resql)
                    {
                        if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
                        dol_include_once('/timetablesepa/class/timetablesepa.class.php');
                        while ($obj = $this->db->fetch_object($resql))
                        {
                            $det = new TimetableSEPADet($this->db);
                            $det->fetch($obj->fk_target);

                            $det->setAccepted($user, $fk_prelevement_bons);
                        }
                    }
                    else
                    {
                        setEventMessage($this->db->lasterror(), 'errors');
                    }
                }
                else
                {
                    setEventMessage($this->db->lasterror(), 'errors');
                }
            }
        }

        return 0;
    }
}
