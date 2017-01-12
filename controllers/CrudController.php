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
use ntesic\generator\generator\crud\Builder as CrudBuilder;
use ntesic\generator\generator\crud\Builder;
use ntesic\Helpers\Tag;
use Phalcon\Assets\Filters\Cssmin;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Exception;
use Phalcon\Mvc\View;

class CrudController extends BaseController
{

    protected $files;

    public function IndexAction()
    {
        $form = new CrudForm();
        $form->buttons = [
            Tag::tagHtml('button', ['type' => 'submit', 'class' => 'btn btn-primary', 'name' => 'preview']) . 'Preview' . Tag::tagHtmlClose('button') . PHP_EOL,
        ];
        $this->jquery->blur('#modelClass', $this->updateFields());
        $this->jquery->compile($this->view);
        if ($this->request->isPost() && ($this->request->hasPost('generate') || $this->request->hasPost('preview'))) {
            if (!$form->isValid($this->request->getPost())) {
                // If the form failed validation, add the errors to the flash error message.
                foreach ($form->getMessages() as $message) {
                    $this->flashSession->error($message->getMessage());
                }
            } else {
                $form->buttons[] = Tag::tagHtml('button', ['type' => 'submit', 'class' => 'btn btn-success', 'name' => 'generate']) . 'Generate' . Tag::tagHtmlClose('button') . PHP_EOL;
                $generator = new CrudBuilder($this->request->getPost());
                $answers = $this->request->getPost('answers');
                $generator->generate();
                if ($this->request->hasPost('generate') && !empty($answers)) {
                    $params['hasError'] = !$generator->save($generator->files, (array)$answers, $results);
                    $params['results'] = $results;
                } else {
                    $params['files'] = $generator->files;
                    $this->files = $generator->files;
                    $params['answers'] = $answers;
                }
                $params['builder'] = $generator;
                $this->view->setVars($params);
            }
        }
        $this->view->setVars([
            'form' => $form
        ]);
        $this->assets->collection('footer')
            ->addJs(VENDOR_PATH . '/ntesic/phalcon-generator/assets/js/generator.js', true);
        $this->assets->collection('main_css')
            ->addCss(VENDOR_PATH . '/ntesic/phalcon-generator/assets/css/generator.css', true);

    }

    public function DiffAction()
    {
        $file = $this->request->get('file');
        $generator = new CrudBuilder($this->request->getPost());
        $generator->generate();
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        foreach ($generator->files as $f) {
            if ($f->id === $file) {
                $this->view->setVar('diff', $f->diff());
                return $this->view->partial('diff');
            }
        }
    }

    public function PreviewAction()
    {
        $file = $this->request->get('file');
        $generator = new CrudBuilder($this->request->getPost());
        $generator->generate();
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        foreach ($generator->files as $f) {
            if ($f->id === $file) {
                $content = $f->preview();
                if ($content !== false) {
                    return  '<div class="content">' . $content . '</content>';
                } else {
                    return '<div class="error">Preview is not available for this file type.</div>';
                }
            }
        }
    }

    protected function updateFields()
    {
        $js = <<<JS
var modelClass = $('#modelClass').val();
            $('#searchModelClass').val(modelClass + 'Search');
            $('#controllerClass').val(modelClass.replace(/models/gi,'Controllers') + 'Controller');
            $('#viewPath').val(modelClass.replace(/models?/gi,'modules/views').replace(/\\\[^\\\]*$/,'').replace(/\\\/gi,'/').replace(/app/gi,'APP_PATH') );
            $('#formClass').val(modelClass.replace(/models/gi,'Forms') + 'Form');
JS;
        return $js;
    }
}