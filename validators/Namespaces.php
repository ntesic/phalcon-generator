<?php

/**
 * Copyright (c) 2016.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

namespace ntesic\generator\validators;

use Phalcon\Validation;
use Phalcon\Validation\Validator;
use Phalcon\Validation\Message;
use Phalcon\Validation\ValidatorInterface;

/**
 * Phalcon\Validation\Validator\Namespaces
 *
 * Check for namespace
 *
 *<code>
 *use Phalcon\Validation\Validator\Namespaces as NSValidator;
 *
 *$validation->add('namespace', new Namespaces(array(
 *    'allowEmpty' => true,
 *    'message' => ':field must be a valid namespace'
 *)));
 *</code>
 *
 * @package Phalcon\Validation\Validator
 */
class Namespaces extends Validator implements ValidatorInterface
{
    /**
     * Executes the namespaces validation
     *
     * @param Validation $validation
     * @param string     $field
     *
     * @return bool
     */
    public function validate(Validation $validation, $field)
    {
        $value = $validation->getValue($field);

        if ($this->hasOption('allowEmpty') && empty($value)) {
            return true;
        }

        $re = '#^(?:(?:\\\)?[a-z](?:[a-z0-9_]+)?)+(?:\\\\(?:[a-z](?:[a-z0-9_]+)?)+)*$#i';

        if (false === (bool) preg_match($re, $value)) {
            $label = $this->getOption('label') ?: $validation->getLabel($field);
            $message = $this->getOption('message') ?: 'Invalid namespace syntax for field ' . $label;
            $replacePairs = array(':field' => $label);

            $validation->appendMessage(new Message(strtr($message, $replacePairs), $field, 'Namespaces'));
            return false;
        }

        return true;
    }
}
