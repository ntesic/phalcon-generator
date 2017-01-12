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

namespace ntesic\generator\generator;


use ntesic\generator\validators\Namespaces;
use Phalcon\Db;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Db\Column;
use Phalcon\Di;
use Phalcon\Loader;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\MetaData\Memory;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\User\Component;
use Phalcon\Mvc\View;
use Phalcon\Text;
use Phalcon\Validation;

abstract class Builder extends Component
{

    const VALIDATE_MODEL = 1;
    const VALIDATE_FORM = 2;

    const FILTER_EMAIL = 'email';
    const FILTER_STRING = 'string';
    const FILTER_INT = 'int';
    const FILTER_STRIPTAGS = 'striptags';
    const FILTER_TRIM = 'trim';
    const FILTER_LOWER = 'lower';
    const FILTER_UPPER = 'upper';

    /**
     * @var Memory
     */
    protected $metaData;
    /**
     * @var string
     */
    protected $table;
    /**
     * @var string
     */
    protected $schema;
    /**
     * @var Pdo
     */
    protected $db;
    /**
     * @var ModelInterface
     */
    protected $model;
    /**
     * @var array
     */
    protected $foreignKeys;
    /**
     * @var string
     */
    protected $templatePath;
    /**
     * @var string
     */
    protected $modelClass;
    /**
     * @var string
     */
    protected $template;
    /**
     * @var Column[]
     */
    protected $dbColumns;
    /**
     * @var array
     */
    protected $uniqueIndexes;
    /**
     * @var array
     */
    protected $enums = [];
    /**
     * @var array
     */
    public $errors = [];
    /**
     * @var array
     */
    public $files = [];

    public function __construct(array $options)
    {
        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->$option = $value;
            }
        }
        $this->db = $this->getDI()->getShared('db');
    }

    protected function init()
    {
        $this->template = rtrim($this->template, '/');
        $this->dbColumns = $this->db->describeColumns($this->getTable());
        $this->initUniqueIndexes();
        $this->initForeignKeys();
        $this->initEnums();
    }

    /**
     * Return table name of model
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Return schema (database name) of model
     * @return string
     */
    public function getSchema()
    {
        return $this->schema;
    }

    public function getDbColumns()
    {
        return $this->dbColumns;
    }

    public function getDataTypes()
    {
        return $this->db->describeColumns($this->getTable(), $this->getSchema());
    }

    abstract public function generate();

    /**
     * Return namespace of class
     * @param $class
     * @return string
     */
    public function getNamespace($class)
    {
        $namespace = explode('\\', $class);
        $last = count($namespace) - 1;
        unset($namespace[$last]);
        return implode('\\', $namespace);
    }

    /**
     * Return class name without namespace
     * @param $class
     * @return mixed
     */
    public function getClassName($class)
    {
        $className = explode('\\', $class);
        $lastPart = count($className) - 1;
        return $className[$lastPart];
    }

    public function getModelClass()
    {
        return $this->modelClass;
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

    /**
     * Return list of template files from template directory with append
     * @param string $append Which directory to append, crud or models
     * @return array
     */
    protected function getTemplateFiles($append)
    {
        /**
         * @var \DirectoryIterator $file
         * @var \DirectoryIterator $dir
         */
        $templates = $this->template . '/' . $append;
        $files = [];
        foreach (new \DirectoryIterator($templates) as $dir) {
            if ($dir->isDot()) {
                continue;
            }
            if ($dir->isDir()) {
                foreach (new \DirectoryIterator($dir->getPathname()) as $file) {
                    if ($file->isFile()) {
                        // Get file without extension
                        $files[$dir->getFilename()][] = pathinfo($file->getFilename())['filename'];
                    }
                }
            } else {
                $files[$dir->getFilename()] = pathinfo($dir->getFilename())['filename'];
            }
        }
        return $files;
    }

    /**
     * Generates code using the specified code template and parameters.
     * Note that the code template will be used as a PHP file.
     * @param string $template the code template file. This must be specified as a file path
     * relative to [[templatePath]].
     * @param array $params list of parameters to be passed to the template file.
     * @return string the generated code
     */
    public function render($template, $params = [])
    {
        $view = new View\Simple();
        $view->setViewsDir(rtrim($this->template, '/') . '/');
        $params['builder'] = $this;
        foreach ($params as $param => $value) {
            $view->setVar($param, $value);
        }
        $view->render($template);
        return $view->getContent();
    }

    /**
     * Returns the associated PHP type
     * @param Column $column
     * @return string
     */
    public function getPhpType(Column $column)
    {
        $type = $column->getType();
        switch ($type) {
            case Column::TYPE_INTEGER:
            case Column::TYPE_BIGINTEGER:
                return 'integer';
                break;
            case Column::TYPE_DECIMAL:
            case Column::TYPE_FLOAT:
                return 'double';
                break;
            case Column::TYPE_DATE:
            case Column::TYPE_VARCHAR:
            case Column::TYPE_DATETIME:
            case Column::TYPE_CHAR:
            case Column::TYPE_TEXT:
                return 'string';
                break;
            default:
                return 'string';
                break;
        }
    }

    /**
     * Initialize all ENUM type columns and store their values
     */
    public function initEnums()
    {
        $columns = $this->db->fetchAll($this->db->getDialect()->describeColumns($this->getTable()));
        foreach ($columns as $column) {
            if ((strpos($column['Type'], 'enum') !== false)) {
                if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column['Type'], $matches)) {
                    $values = explode(',', $matches[2]);
                    foreach ($values as $i => $value) {
                        $value = trim($value, "'");
                        $const_name = strtoupper($column['Field'] . '_' . $value);
                        $const_name = preg_replace('/\s+/', '_', $const_name);
                        $const_name = str_replace(['-', '_', ' '], '_', $const_name);
                        $const_name = preg_replace('/[^A-Z0-9_]/', '', $const_name);
                        $enumValues[$const_name] = $value;
                    }
                    $this->enums[$column['Field']] = $enumValues;
                }
            }
        }
    }

    /**
     * Check if column is ENUM type
     * @param Column $column
     * @return bool
     */
    public function isEnum(Column $column)
    {
        // If not MySql return false
        if ($this->db->getDialectType() !== 'mysql') {
            return false;
        }
        if (isset($this->enums[$column->getName()])) {
            return true;
        }
        return false;
    }

    /**
     * Add Validator to form or model
     * @param Column $column
     * @param string $type
     * @return string
     */
    public function addValidator(Column $column, $type = self::VALIDATE_MODEL)
    {
        $name = $column->getName();
        switch ($type) {
            case self::VALIDATE_FORM:
                $template = "\t\t\$$name" . "->addValidator(%s);\n";
                break;
            case self::VALIDATE_MODEL:
                $template = "\t\t\$validator->add('$name', %s);\n";
                break;
        }
        $validators = [];
        if ($column->isNotNull() && !$column->isAutoIncrement()) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\PresenceOf(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " is required'])");
        }
        if ((strpos($column->getName(), 'email') !== false)) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\Email(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " need to be email'])");
        }
        if ((strpos($column->getName(), 'url') !== false) || (strpos($column->getName(), 'link') !== false)) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\Url(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " need to be url format'])");
        }
        if ($column->getType() === Column::TYPE_VARCHAR && $column->getSize() > 0) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\StringLength(['min' => 0, 'max' => " . $column->getSize() ."])");
        }
        if ($column->getType() === Column::TYPE_INTEGER && !$column->isUnsigned()) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\Digit(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " need to be number only'])");
        }
        if ($column->getType() === Column::TYPE_INTEGER && $column->isUnsigned()) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\Numericality(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " need to be number only'])");
        }
        if ($this->isUnique($column)) {
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\Uniqueness(['message' => 'The " . \ntesic\boilerplate\Helpers\Text::camel2words($name) . " need to be unique'])");
        }
        if ($this->isEnum($column)) {
            $enum = '';
            foreach ($this->enums[$column->getName()] as $const => $value) {
                $enum .= "\n\t\t\t\t\\" . $this->getModelClass() ."::$const,";
            }
            $enum = rtrim($enum, ', ');
            $validators[] = sprintf($template, "new \\Phalcon\\Validation\\Validator\\InclusionIn([
            'domain' => [$enum
            ]
        ])");
        }
        return implode("", $validators);
    }

    protected function initUniqueIndexes()
    {
        /**
         * @var Db\Index[] $indexes
         */
        $indexes = $this->db->describeIndexes($this->getTable());
        foreach ($indexes as $index) {
            if ($index->getType() == 'UNIQUE') {
                foreach ($index->getColumns() as $column) {
                    $this->uniqueIndexes[$column] = true;
                }
            }
        }
    }

    /**
     * Return if column have unique index
     * @param Column $column
     * @return bool
     */
    protected function isUnique(Column $column)
    {
        return isset($this->uniqueIndexes[$column->getName()]);
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

    /**
     * Initialize foreign keys from whole table
     */
    protected function initForeignKeys()
    {
        /**
         * @var Db\Reference $reference
         */
        $foreignKeys = $this->db->describeReferences($this->getTable(), $this->getSchema());
        foreach ($foreignKeys as $foreignKey => $reference) {
            // Skip multi columns foreign keys
            if (count($reference->getColumns()) > 1 || count($reference->getReferencedColumns()) > 1) {
                continue;
            }
            $this->foreignKeys[$reference->getColumns()[0]] = $reference;
        }
    }

    /**
     * Check if column have reference
     * @param Column $column
     * @return bool
     */
    public function isForeignKey(Column $column)
    {
        return $this->foreignKeys !== null && array_key_exists($column->getName(), $this->foreignKeys);
    }

    /**
     * Translate namespace of class to directory, example 'App\Backend\Models\TestModel' => /srv/www/site/app/modules/Backend/Models if
     * Loader is registered as service in DI and namespace or class Module is registered
     * If Loader is not registered as service, try to translate namespace to directory by changing first part of namespace with APP_PATH (if defined),
     * otherwise return false
     * @param object||string $class
     * @return bool|string
     */
    public static function namespace2Dir($class, $manual = false)
    {
        /**
         * @var Loader $loader
         */
        $found = false;
        // If $class argument is type of object, get full name
        if (is_object($class)) {
            $reflection = new \ReflectionClass($class);
            $class = $reflection->getName();
        }
        $class = ltrim($class, '\\');
        if (Di::getDefault()->has('loader') && $manual == false) {
            // If Loader is registered only as service
            $loader = Di::getDefault()->get('loader');
            $dirs = [];
            foreach ($loader->getNamespaces() as $namespace => $dir) {
                $namespace = ltrim($namespace, '\\');
                $dir[0] = realpath($dir[0]);
                if ($dir[0]) {
                    $dir[0] .= '/';
                    $dirs[$namespace] = $dir[0];
                }
            }
            foreach ($loader->getClasses() as $namespace => $dir) {
                $namespace = ltrim($namespace, '\\');
                $dir = explode('/', $dir);
                $last = count($dir);
                if ((strpos($dir[$last - 1], '.') !== false)) {
                    // Remove file part (module)
                    unset($dir[$last - 1]);
                }
                $dir = implode('/', $dir);
                $dir = realpath($dir);
                if ($dir) {
                    $dir .= '/';
                    $dirs[$namespace] = $dir;
                }
            }

            $classParts = explode('\\', $class);
            $lastPart = count($classParts) - 1;
            // Remove model name from array
            unset($classParts[$lastPart]);
            $total = count($classParts);
            $removedParts = [];
            while ($total > 0) {
                if (isset($classParts[$total])) {
                    $removedParts[] = $classParts[$total];
                    unset ($classParts[$total]);
                }
                $currentNamespace = implode('\\', $classParts);
                if (array_key_exists($currentNamespace, $dirs)) {
                    $found = $dirs[$currentNamespace];
                    // We found it, break loop and go directly to return it
                    break;
                }
                $total--;
            }
            if ($found) {
                $removedParts = array_reverse($removedParts);
                $found .= implode('/', $removedParts);
            } else {
                // If not found try to translate manually
                return self::namespace2Dir($class, true);
            }
        } else {
            // Loader is not registered as service, try to translate manually by changing first part of namespace with APP_PATH
            if (defined('APP_PATH')) {
                $classParts = explode('\\', $class);
                $classParts[0] = ltrim(APP_PATH, '/');
                $total = count($classParts) - 1;
                unset($classParts[$total]);
                $dir = '/';
                $dir .= implode('/', $classParts);
                $found = $dir;
            }
        }
        return strtolower($found);
    }

    public function save(array $files, array $answers, &$results)
    {
        /**
         * @var CodeFile $file
         */
        foreach ($files as $file) {
            $relativePath = $file->getRelativePath();
            if (isset($answers[$file->id]) && $file->operation !== CodeFile::OP_SKIP) {
                $error = $file->save();
                if (is_string($error)) {
                    $hasError = true;
                    $lines[] = "generating $relativePath\n<span class=\"error\">$error</span>";
                } else {
                    $lines[] = $file->operation === CodeFile::OP_CREATE ? " generated $relativePath" : " overwrote $relativePath";
                }
            } else {
                $lines[] = "   skipped $relativePath";
            }
        }

        $lines[] = "done!\n";
        $results = implode("\n", $lines);

        return !$hasError;

    }

    /**
     * Returns the message to be displayed when the newly generated code is saved successfully.
     * Child classes may override this method to customize the message.
     * @return string the message to be displayed when the newly generated code is saved successfully.
     */
    public function successMessage()
    {
        return 'The code has been generated successfully.';
    }
}