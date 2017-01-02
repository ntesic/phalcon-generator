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
namespace ntesic\generator\forms\crud;

class Form extends \ntesic\boilerplate\Form\Form
{

    public function initialize()
    {
        parent::initialize();
        $modelClass = new \Phalcon\Forms\Element\Text("modelClass", ['value' => 'App\Models\\']);
        $modelClass->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Model is required']));
        $this->add($modelClass);

        $conterollerClass = new \Phalcon\Forms\Element\Text("controllerClass");
        $conterollerClass->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Controller is required']));
        $this->add($conterollerClass);

        $formClass = new \Phalcon\Forms\Element\Text("formClass");
        $formClass->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Form is required']));
        $this->add($formClass);

        $viewPath = new \Phalcon\Forms\Element\Text("viewPath");
        $viewPath->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The View Path is required']));
        $this->add($viewPath);

        $template = new \Phalcon\Forms\Element\Text("template", ['value' => realpath(__DIR__ . '/../../') . '/templates']);
        $template->addValidator(new \Phalcon\Validation\Validator\PresenceOf(['message' => 'The Template Path is required']));
        $this->add($template);

    }
}