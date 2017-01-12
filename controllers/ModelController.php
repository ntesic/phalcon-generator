<?php
/**
 * Copyright (c) 2017.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 1/5/2017
 * Time: 2:23 PM
 */

namespace ntesic\generator\controllers;

use ntesic\boilerplate\controllers\BaseController;
use ntesic\generator\forms\model\Form as ModelForm;
use ntesic\generator\generator\model\Builder as ModelBuilder;
use ntesic\Helpers\Tag;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Mvc\View;

class ModelController extends BaseController
{
    public function IndexAction()
    {
        $form = new ModelForm();
        $this->jquery->compile($this->view);
        $form->buttons = [
            Tag::tagHtml('button', ['type' => 'submit', 'class' => 'btn btn-primary', 'name' => 'preview']) . 'Preview' . Tag::tagHtmlClose('button') . PHP_EOL,
        ];


        if ($this->request->isPost() && ($this->request->hasPost('generate') || $this->request->hasPost('preview'))) {
            if (!$form->isValid($this->request->getPost())) {
                // If the form failed validation, add the errors to the flash error message.
                foreach ($form->getMessages() as $message) {
                    $this->flashSession->error($message->getMessage());
                }
            } else {
                $form->buttons[] = Tag::tagHtml('button', ['type' => 'submit', 'class' => 'btn btn-success', 'name' => 'generate']) . 'Generate' . Tag::tagHtmlClose('button') . PHP_EOL;
                $generator = new ModelBuilder($this->request->getPost());
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
            'form' => $form,
        ]);

        $this->assets->collection('footer')
            ->addJs(VENDOR_PATH . '/ntesic/phalcon-generator/assets/js/generator.js', true);
        $this->assets->collection('main_css')
            ->addCss(VENDOR_PATH . '/ntesic/phalcon-generator/assets/css/generator.css', true);
    }

    public function DiffAction()
    {
        $file = $this->request->get('file');
        $generator = new ModelBuilder($this->request->getPost());
        $generator->generate();
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        foreach ($generator->files as $f) {
            if ($f->id === $file) {
                $this->view->setVar('diff', $f->diff());
                return $this->view->partial('diff');
            }
        }
    }
}