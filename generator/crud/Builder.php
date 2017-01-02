<?php
/**
 * Copyright (c) 2016.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 12/27/2016
 * Time: 4:39 PM
 */

namespace ntesic\generator\generator\crud;

use ntesic\generator\generator\Builder as BaseBuilder;
use ntesic\generator\generator\CodeFile;
use ntesic\generator\validators\Namespaces;
use Phalcon\Db;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Db\Column;
use Phalcon\Di;
use Phalcon\Loader;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\MetaData\Memory;
use Phalcon\Mvc\View;
use Phalcon\Text;
use Phalcon\Validation;

class Builder extends BaseBuilder
{

    /**
     * @var string
     */
    protected $viewPath;
    /**
     * @var string
     */
    protected $controllerClass;
    /**
     * @var string
     */
    protected $formClass;

    protected function init()
    {
        parent::init();
        $this->metaData = new Memory($this->model);
        $this->model = new $this->modelClass;
        $this->table = $this->model->getSource();
        $this->schema = $this->model->getSchema();
        $this->viewPath = rtrim(str_replace('APP_PATH', APP_PATH, $this->viewPath) . '/');
    }


    /**
     * Validate generator for proper model, namespaces, template path, etc...
     * @return bool
     */
    public function validate()
    {
        $validator = new Validation();
        $validator->add('modelClass', new Namespaces());
        $validator->add('controllerClass', new Namespaces());
        $validator->add('formClass', new Namespaces());
        // Options to validate with validators
        $options = [
            'modelClass' => $this->modelClass,
            'controllerClass' => $this->controllerClass,
            'formClass' => $this->formClass,
        ];
        $messages = $validator->validate($options);

        // Check if template directory exist
        if (is_dir($this->template) === false) {
            $message = new Validation\Message('Template path do not exists');
            $validator->appendMessage($message);
        }
        if (class_exists($this->modelClass) === false) {
            $message = new Validation\Message('Model not exists');
            $validator->appendMessage($message);
        }

        if (count($messages)) {
            foreach ($messages as $message) {
                $this->errors[] = $message->getMessage();
            }
            return false;
        }
        // Validation passed, init class
        $this->init();
        return true;
    }

    public function generate()
    {
        if ($this->validate() === false) {
            return false;
        } else {
            $this->generateViews();
            $this->generateForm();
            $this->generateController();
        }
        return true;
    }

    public function getFormClass()
    {
        return $this->formClass;
    }

    public function getControllerClass()
    {
        return $this->controllerClass;
    }

    public function generateUrlParams()
    {
        /**
         * @var Memory $metaData
         */
        $metaData = $this->modelsMetadata;
        $pks = $metaData->getPrimaryKeyAttributes($this->model);
        $output = '[';
        foreach ($pks as $pk) {
            $output .= "'$pk' => \$model->$pk, ";
        }
        $output = rtrim($output, ', ');
        $output .= ']';
        return $output;
    }

    public function getDetailsViewAttributes()
    {
        /**
         * @var Column $column
         */
        $output = '';
        foreach ($this->getDataTypes() as $column) {
            $output .= $this->getDetailViewAttribute($column);
        }
        $output = rtrim($output, ",");
        return $output;
    }

    public function getGridViewAttributes()
    {
        /**
         * @var Column $column
         */
        $output = '';
        foreach ($this->getDataTypes() as $column) {
            $output .= $this->getGridViewAttribute($column);
        }
        return $output;
    }

    protected function getDetailViewAttribute(Column $column)
    {
        $isForeignKey = $this->isForeignKey($column);
        if ($isForeignKey) {
            /**
             * @var Db\Reference $reference
             */
            $reference = $this->foreignKeys[$column->getName()];
            $referencedModel = Text::camelize($reference->getReferencedTable(), '_');
            $referencedColumn = $reference->getReferencedColumns()[0];
            return "\t\t[
            'attribute' => '" . $column->getName() . "',
            'value' => \$model->getRelated('$referencedModel')?
                Tag::linkTo([
                    \$this->url->get('/" . Text::uncamelize($referencedModel, '_') . "/view',['$referencedColumn' => \$model->" . $column->getName() . "], null, \$this->router->getModuleName()),
                    'text' => \$model->getRelated('$referencedModel')->toString
                ])
            : '<span class=\"label label-warning\">?</span>'
        ],";
        } else {
            return "\t\t'" . $column->getName() . "',\n";
        }
    }

    protected function getGridViewAttribute(Column $column)
    {
        $isForeignKey = $this->isForeignKey($column);
        if ($isForeignKey) {
            /**
             * @var Db\Reference $reference
             */
            $reference = $this->foreignKeys[$column->getName()];
            $referencedModel = Text::camelize($reference->getReferencedTable(), '_');
            $referencedColumn = $reference->getReferencedColumns()[0];
            return "\t\t[
            'attribute' => '" . $column->getName() . "',
            'label' => '" . Text::humanize($column->getName()) . "',
            'content' => function(\$model, \$key, \$index){
                return Tag::linkTo([
                    \$this->url->get('/" . Text::uncamelize($referencedModel, '_') . "/view',['$referencedColumn' => \$model->" . $column->getName() . "], null, \$this->router->getModuleName()),
                    'text' => \$model->getRelated('$referencedModel')->toString
                ]);
            },
        ],";
        } else {
            return "\t\t'" . $column->getName() . "',\n";
        }
    }

    /**
     * Generate views from views template directory
     */
    protected function generateViews()
    {
        /**
         * @var \DirectoryIterator $file
         */
        foreach ($this->getTemplateFiles('crud')['views'] as $template) {
            $subDir = str_replace('Controller', '', $this->getClassName($this->getControllerClass()));
            $subDir = Text::uncamelize($subDir, '-');
            $outputFile = $this->viewPath . '/' . $subDir . '/' . $template . '.php';
            $codeFile = new CodeFile($outputFile, $this->render('crud/views/' . $template));
            $codeFile->save();
        }
    }

    protected function generateForm()
    {
        /**
         * @var \DirectoryIterator $file
         */
        foreach ($this->getTemplateFiles('crud')['forms'] as $template) {
            $outputFormFile = explode('\\', $this->formClass);
            $lastPart = count($outputFormFile) - 1;
            $outputFormFile = $outputFormFile[$lastPart];
            $outputFile = self::namespace2Dir($this->formClass) . '/' . $outputFormFile . '.php';
            $codeFile = new CodeFile($outputFile, $this->render('crud/forms/' . $template));
            $codeFile->save();
        }
    }

    protected function generateController()
    {
        /**
         * @var \DirectoryIterator $file
         */
        foreach ($this->getTemplateFiles('crud')['controllers'] as $template) {
            $outputControllerFile = $this->getClassName($this->getControllerClass());
            $baseAppend = '';
            if ($template == 'base') {
                $baseAppend = 'base/';
            }
            $outputFile = self::namespace2Dir($this->controllerClass) . '/' . $baseAppend . $outputControllerFile . '.php';
            $codeFile = new CodeFile($outputFile, $this->render('crud/controllers/' . $template));
            $codeFile->save();
        }
    }

    /**
     * Generate form element
     * @param Column $column
     * @return bool|string
     */
    public function generateFormElement(Column $column)
    {
        $type = $column->getType();
        $phpType = $this->getPhpType($column);
        $name = $column->getName();
        $ai = $column->isAutoIncrement();
        $required = $column->isNotNull();
        $pk = $column->isPrimary();
        $isForeignKeyColumn = $this->isForeignKey($column);
        $filters = [];
        if ($ai) {
            // Skip auto-incrament columns
            return false;
        }
        $element = "\t\t" . $this->getElementClass('text', $name);
        if ($isForeignKeyColumn) {
            $element = "\t\t" . $this->getSelectBox($column);
        } elseif ($type == Column::TYPE_DATETIME) {
            $element = "\t\t" . $this->getElementClass('date', $name);
        } elseif ((strpos($name, 'email') !== false)) {
            $element = "\t\t" . $this->getElementClass('email', $name);
        }
        // Add validators
        if ($required) {
            $element .= "\t\t" . $this->addValidator(self::VALIDATOR_REQUIRED, $name);
        }
        // Add filters
        if ((strpos($name, 'email') !== false)) {
            $element .= $this->addValidator(self::VALIDATOR_EMAIL, $name);
            $filters[] = self::FILTER_EMAIL;
        }
        $element .= "\t\t" . $this->addFilters($filters, $name);
        $element .= "\$this->add(\$$name);\n\n";
        return $element;
    }

    /**
     * Add validators to form element
     * @param $type
     * @param $name
     * @return string
     */
    protected function addValidator($type, $name)
    {
        switch ($type) {
            case self::VALIDATOR_REQUIRED:
                return "\$$name" . "->addValidator(new \\Phalcon\\Validation\\Validator\\PresenceOf(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " is required']));\n";
        }
    }

    /**
     * Generate form element
     * @param $type
     * @param $name
     * @param null $options
     * @return string
     */
    protected function getElementClass($type, $name, $options = null)
    {
        switch ($type) {
            case 'text':
                return "\$$name = new \\Phalcon\\Forms\\Element\\Text(\"$name\");\n";
            case 'date':
                return "\$$name = new \\Phalcon\\Forms\\Element\\Date(\"$name\");\n";
            case 'boolean':
                return "\$$name = new \\Phalcon\\Forms\\Element\\Check(\"$name\");\n";
            case 'email':
                return "\$$name = new \\Phalcon\\Forms\\Element\\Email(\"$name\");\n";
            case 'dropdown':
                return "\$$name = new \\Phalcon\\Forms\\Element\\Select(\"$name\");\n";
            default:
                return "\$$name = new \\Phalcon\\Forms\\Element\\Text(\"$name\");\n";
        }
    }

    /**
     * Generate select box for columns that have foreign keys
     * @param Column $column
     * @return string
     */
    protected function getSelectBox(Column $column)
    {
        /**
         * @var Db\Reference $reference
         */
        $name = $column->getName();
        $reflection = new \ReflectionClass($this->model);
        $name_space = $reflection->getNamespaceName();
        $reference = $this->foreignKeys[$column->getName()];
        $referencedTable = Text::camelize($reference->getReferencedTable(), '_');
        $referencedColumn = $reference->getReferencedColumns()[0];
        $referencedModel = '\\' . $name_space . '\\' . $referencedTable;

        return "\$$name = new \\Phalcon\\Forms\\Element\\Select(
            '$name',
            $referencedModel::find(),
            [
                'using' => [
                    '$referencedColumn',
                    'toString'
                ],
                'useEmpty' => true,
                'emptyText' => 'Please, choose one...',
                'emptyValue' => null,
            ]
        );\n";
    }

    /**
     * Add filters to form element
     * @param $filters
     * @param $name
     * @return bool|string
     */
    protected function addFilters($filters, $name)
    {
        if (!is_array($filters)) {
            $filters = (array)$filters;
        }
        if (empty($filters)) {
            return false;
        }
        $filters = '"' . implode('", "', $filters) . '"';
        return "\$$name" . "->setFilters([$filters]);\n";
    }

}