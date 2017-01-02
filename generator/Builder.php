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

    const VALIDATOR_REQUIRED = 1;
    const VALIDATOR_EMAIL = 2;
    const VALIDATOR_DATE = 3;
    const VALIDATOR_UNIQUE = 4;
    const VALIDATOR_URL = 5;
    const VALIDATOR_STRING_LENGTH = 6;

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
     * @var array
     */
    protected $dbColumns;
    /**
     * @var array
     */
    public $errors = [];

    protected $validators = [
        self::VALIDATOR_REQUIRED => '\Phalcon\Validation\Validator\PresenceOf',
    ];

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
        $this->initForeignKeys();
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

}