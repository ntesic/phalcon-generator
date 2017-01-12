<?php
/**
 * Copyright (c) 2016.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 12/27/2016
 * Time: 12:39 PM
 */

namespace ntesic\generator\controllers;

use ntesic\boilerplate\controllers\BaseController;
use ntesic\generator\forms\crud\Form as CrudForm;
use ntesic\generator\forms\model\Form as ModelForm;
use ntesic\generator\generator\crud\Builder as CrudBuilder;
use ntesic\generator\generator\model\Builder as ModelBuilder;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Exception;

class IndexController extends BaseController
{

    public function IndexAction()
    {
        $form = new CrudForm();
        $this->jquery->blur('#modelClass', $this->updateFields());
        $this->jquery->compile($this->view);
        $this->view->setVar('form', $form);
        if ($this->request->isPost()) {
            if (!$form->isValid($this->request->getPost())) {
                // If the form failed validation, add the errors to the flash error message.
                foreach ($form->getMessages() as $message) {
                    $this->flashSession->error($message->getMessage());
                }
            } else {
                $generator = new CrudBuilder($this->request->getPost());
                if ($generator->generate() === false) {
                    foreach ($generator->errors as $error) {
                        $this->flashSession->error($error);
                    }
                }
            }
        }
    }

    public function ModelAction()
    {
        $form = new ModelForm();
        $this->jquery->compile($this->view);

        $this->view->setVars([
            'form' => $form,
        ]);
        if ($this->request->isPost()) {
            if (!$form->isValid($this->request->getPost())) {
                // If the form failed validation, add the errors to the flash error message.
                foreach ($form->getMessages() as $message) {
                    $this->flashSession->error($message->getMessage());
                }
            } else {
                $generator = new ModelBuilder($this->request->getPost());
                if ($generator->generate() === false) {
                    foreach ($generator->errors as $error) {
                        $this->flashSession->error($error);
                    }
                }
            }
        }
    }

    protected function updateFields()
    {
        $js = <<<JS
var modelClass = $('#modelClass').val();
            $('#searchModelClass').val(modelClass + 'Search');
            $('#controllerClass').val(modelClass.replace(/models/gi,'Backend\\\\Controllers') + 'Controller');
            $('#viewPath').val(modelClass.replace(/models?/gi,'modules/backend/views').replace(/\\\[^\\\]*$/,'').replace(/\\\/gi,'/').replace(/app/gi,'APP_PATH') );
            $('#formClass').val(modelClass.replace(/models/gi,'Backend\\\\Forms') + 'Form');
JS;
        return $js;
    }
}