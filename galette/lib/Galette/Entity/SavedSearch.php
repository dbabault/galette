<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Saved search
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.3dev - 2019-03-25
 */

namespace Galette\Entity;

use Galette\Core;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Repository\PaymentTypes;
use Analog\Analog;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\PredicateSet;

/**
 * Saved search
 *
 * @category  Entity
 * @name      SavedSearch
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.3dev - 2019-03-25
 */

class SavedSearch
{
    const TABLE = 'searches';
    const PK = 'search_id';

    private $zdb;
    private $id;
    private $name;
    private $private = true;
    private $parameters = [];
    private $author;
    private $creation_date;
    private $form;

    private $login;
    private $errors = [];

    /**
     * Main constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Login instance
     * @param mixed $args  Arguments
     */
    public function __construct(Db $zdb, Login $login, $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->creation_date = date('Y-m-d H:i:s');

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a saved search from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load($id)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where(self::PK . ' = ' . $id);

            $results = $this->zdb->execute($select);
            $res = $results->current();

            $this->loadFromRs($res);
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred loading saved search #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load a saved search from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs($rs)
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        $this->name = $rs->type_name;
        $this->private = $rs->private;
        $this->parameters = json_decode($rs->parameters);
        $this->author_id = $rs->id_adh;
        $this->creation_date = $rs->creation_date;
        $this->form = $rs->form;
    }

    /**
     * Check and set values
     *
     * @param array $values Values to set
     *
     * @return boolean
     */
    public function check($values)
    {
        $this->errors = [];
        $mandatory = [
            'name'  => _T('Name is mandatory!'),
            'form'  => _T('Form is mandatory!')
        ];

        foreach ($values as $key => $value) {
            if (empty($value) && isset($mandatory[$key])) {
                $this->errors = $mandatory[$key];
            }
            $this->$key = $value;
        }

        if ($this->id === null) {
            //set author for new searches
            $this->author_id = $this->login->id;
        }

        return (count($this->errors) === 0);
    }

    /**
     * Store saved search in database
     *
     * @return boolean|null
     */
    public function store()
    {
        $parameters = json_encode($this->parameters);
        $parameters_sum = sha1($parameters, true);
        $data = array(
            'name'              => $this->name,
            'private'           => $this->private,
            'parameters'        => $parameters,
            'parameters_sum'    => $parameters_sum,
            'id_adh'            => $this->author_id,
            'creation_date'     => ($this->creation_date !== null ? $this->creation_date : date('Y-m-d H:i:s')),
            'form'              => $this->form
        );

        try {
            $select = $this->zdb->select(self::TABLE);
            $select
                ->where([
                    'form'              => $this->form,
                    'parameters_sum'    => $parameters_sum,
                    'id_adh'            => $this->login->id,
                    'private'           => true
                ])
                ->where(['private' => false], PredicateSet::OP_OR)
                ->limit(1);

            $results = $this->zdb->execute($select);
            if ($results->count() !==  0) {
                $result = $results->current();
                //search already exists
                Analog::log(
                    str_replace('%name', $result->name, 'Already saved as "%name"!'),
                    Analog::INFO
                );
                return null;
            } else {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred storing saved search: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove current saved search
     *
     * @return boolean
     */
    public function remove()
    {
        $id = (int)$this->id;
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                self::PK . ' = ' . $id
            );
            $this->zdb->execute($delete);
            Analog::log(
                'Saved search #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to delete saved search ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $forbidden = [];
        $virtuals = [];
        if (in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            switch ($name) {
                default:
                    Analog::log(
                        sprintf('Unable to get %class property %property', self::class, $name),
                        Analog::WARNING
                    );
                    return $this->$name;
                    break;
            }
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'form':
                if (!in_array($value, $this->getKnownForms())) {
                    $this->errors[] = str_replace('%form', $value, _T("Unknown form %form!"));
                }
                $this->form = $value;
                break;
            case 'parameters':
                if (!is_array($value)) {
                    Analog::log(
                        'Search parameters must be an array!',
                        Analog::ERROR
                    );
                }
                $this->parameters = $value;
                break;
            case 'name':
                if (trim($value) === '') {
                    $this->errors[] = _T("Name cannot be empty!");
                }
                $this->name = $value;
                break;
            case 'author_id':
                $this->author_id = (int)$value;
                break;
            case 'parameters':
                $this->parameters = $value;
                break;
            default:
                Analog::log(
                    str_replace(
                        ['%class', '%property'],
                        [self::class, $name],
                        'Unable to set %class property %property'
                    ),
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Is search private?
     *
     * @return boolean
     */
    public function isPrivate()
    {
        return $this->private;
    }

    /**
     * Get known forms
     *
     * @return array
     */
    public function getKnownForms()
    {
        return [
            'Adherent'
        ];
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
