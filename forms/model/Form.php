<?php
/**
 * Copyright (c) 2016.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 12/28/2016
 * Time: 2:58 PM
 */
namespace ntesic\generator\forms\model;

use Phalcon\Forms\Element\Select;

class Form extends \ntesic\boilerplate\Form\Form
{

    public function initialize()
    {
        parent::initialize();
        $tables = $this->db->listTables();
        foreach ($tables as $table) {
            $tablesForList[$table] = $table;
        }
        $tableName = new Select('table', $tablesForList, [
            'useEmpty' => true,
            'emptyText' => 'Please, choose table...',
            'emptyValue' => null,
        ]);
        $tableName->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Table name is required']));
        $this->add($tableName);

        $modelClass = new \Phalcon\Forms\Element\Text("modelClass");
        $modelClass->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Model name is required']));
        $this->add($modelClass);

        $nameSpace = new \Phalcon\Forms\Element\Text("namespace", ['value' => 'App\Models']);
        $nameSpace->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Namespace is required']));
        $this->add($nameSpace);

        $template = new \Phalcon\Forms\Element\Text("template", ['value' => realpath(__DIR__ . '/../../') . '/templates']);
        $template->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Template Path is required']));
        $this->add($template);

    }
}