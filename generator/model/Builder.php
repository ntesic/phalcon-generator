<?php
/**
 * Copyright (c) 2016.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 12/30/2016
 * Time: 5:36 PM
 */
namespace ntesic\generator\generator\model;

use ntesic\boilerplate\Helpers\Text;
use ntesic\generator\generator\CodeFile;
use ntesic\generator\validators\Namespaces;
use Phalcon\Db\Column;
use Phalcon\Db\Reference;
use Phalcon\Validation;
use Phalcon\Validation\Message;

class Builder extends \ntesic\generator\generator\Builder
{

    /**
     * @var string
     */
    public $baseModel;
    /**
     * @var string
     */
    protected $namespace;

    public function generate()
    {
        if ($this->validate() === false) {
            return false;
        } else {
            $this->generateModel();
        }
        return true;
    }

    /**
     * Validate generator for proper model, namespaces, template path, etc...
     * @return bool
     */
    public function validate()
    {
        $validator = new Validation();
        $validator->add('namespace', new Namespaces());
        // Options to validate with validators
        $options = [
            'namespace' => $this->namespace,
        ];
        $messages = $validator->validate($options);

        // Check if template directory exist
        if (is_dir($this->template) === false) {
            $message = new Message('Template path do not exists');
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

    protected function init()
    {
        parent::init();
        $schema = $this->db->query('SELECT DATABASE()')->fetchArray();
        $this->schema = $schema['DATABASE()'];
        $this->modelClass = rtrim($this->namespace, '\\') . '\\' . ucfirst($this->modelClass);
    }

    protected function generateModel()
    {
        /**
         * @var \DirectoryIterator $file
         */
        foreach ($this->getTemplateFiles('model') as $template) {
            $outputModelFile = $this->getClassName($this->getModelClass());
            $baseAppend = '';
            if ($template == 'base') {
                $baseAppend = 'base/';
            }
            $outputFile = rtrim(self::namespace2Dir($this->modelClass), '/') . '/' . $baseAppend . $outputModelFile . '.php';
            $this->files[] = $codeFile = new CodeFile($outputFile, $this->render('model/' . $template));
        }
    }

    public function generateRelations()
    {
        /**
         * @var Reference $reference
         */
        $templateRelation = "        \$this->%s(%s, '%s', %s, %s);" . PHP_EOL;
        $initialize = [];
        $entityNamespace = $this->namespace . "\\";
        $aliases = [];
        foreach ($this->db->listTables() as $tableName) {
            foreach ($this->db->describeReferences($tableName, $this->schema) as $reference) {
                if ($reference->getReferencedTable() != $this->getTable()) {
                    continue;
                }

                $refColumns = $reference->getReferencedColumns();
                $columns = $reference->getColumns();
                $alias = Text::camelize($tableName);
                while (isset($aliases[$alias])) {
                    // If alias exist, increment by on until we find the one that is not yet used
                    $alias = Text::increment($alias);
                }
                $aliases[$alias] = $alias;
                $initialize[] = sprintf(
                    $templateRelation,
                    'hasMany',
                    (count($refColumns) == 1) ? '\'' . $refColumns[0] . '\'' : $this->compositeColumns($refColumns),
                    $entityNamespace . Text::camelize($tableName),
                    (count($columns) == 1) ? '\'' . $columns[0] . '\'' : $this->compositeColumns($columns),
                    "['alias' => '" . $alias . "']"
                );
            }
        }

        $aliases = [];
        foreach ($this->db->describeReferences($this->getTable(), $this->getSchema()) as $reference) {
            $refColumns = $reference->getReferencedColumns();
            $columns = $reference->getColumns();
            $alias = Text::camelize($reference->getReferencedTable());
            while (isset($aliases[$alias])) {
                // If alias exist, increment by on until we find the one that is not yet used
                $alias = Text::increment($alias);
            }
            $aliases[$alias] = $alias;
            $initialize[] = sprintf(
                $templateRelation,
                'belongsTo',
                (count($columns) == 1) ? '\'' . $columns[0] . '\'' : $this->compositeColumns($columns),
                $this->namespace . '\\' . Text::camelize($reference->getReferencedTable()),
                (count($refColumns) == 1) ? '\'' . $refColumns[0] . '\'' : $this->compositeColumns($refColumns),
                "['alias' => '" . $alias . "']"
            );
        }
        return $initialize;
    }

    protected function compositeColumns($columns)
    {
        $output = '[\'';
        $output .= implode("', '", $columns);
        $output .= '\']';
        return $output;
    }

    public function getToString()
    {
        /**
         * @var Column $column
         */
        $toString = null;
        foreach ($this->dbColumns as $column) {
            if (strtolower($column->getName()) == "name") {
                $toString = $column->getName();
                break;
            }
        }
        if ($toString === null) {
            foreach ($this->dbColumns as $column) {
                if (strstr(strtolower($column->getName()), "name") !== false) {
                    $toString = $column->getName();
                    break;
                }
            }
        }
        if ($toString === null) {
            foreach ($this->dbColumns as $column) {
                if (strstr(strtolower($column->getName()), "title") !== false) {
                    $toString = $column->getName();
                    break;
                }
            }
        }
        if ($toString === null) {
            foreach ($this->dbColumns as $column) {
                if ($column->isAutoIncrement()) {
                    $toString = $column->getName();
                    break;
                }
            }
        }
        if ($toString === null) {
            foreach ($this->dbColumns as $column) {
                $toString = $column->getName();
                break;
            }
        }
        return $toString;
    }

    public function generateEnumConstants()
    {
        if (empty($this->enums)) {
            return false;
        }
        $output = '';
        foreach ($this->enums as $column => $values) {
            foreach ($values as $const => $value) {
                $template = "\tconst %s = '%s';\n";
                $output .= sprintf($template, $const, $value);
            }
        }
        return $output;
    }

}