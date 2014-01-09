<?php
namespace Sirius\Validation;

use Sirius\Validation\Utils;
use Sirius\Validation\Helper;

class Validator implements ValidatorInterface
{

    const RULE_REQUIRED = 'required';

    const RULE_REQUIRED_WITH = 'requiredwith';

    const RULE_REQUIRED_WITHOUT = 'requiredwithout';

    const RULE_REQUIRED_WHEN = 'requiredwhen';
    
    // string rules
    const RULE_ALPHA = 'alpha';

    const RULE_ALPHANUMERIC = 'alphanumeric';

    const RULE_ALPHANUMHYPHEN = 'alphanumhyphen';

    const RULE_LENGTH = 'length';

    const RULE_MAX_LENGTH = 'maxlength';

    const RULE_MIN_LENGTH = 'minlength';

    const RULE_FULLNAME = 'fullname';
    
    // array rules
    const RULE_ARRAY_LENGTH = 'arraylength';

    const RULE_ARRAY_MIN_LENGTH = 'arrayminlength';

    const RULE_ARRAY_MAX_LENGTH = 'arraymaxlength';

    const RULE_IN_LIST = 'inlist';

    const RULE_NOT_IN_LIST = 'notinlist';
    
    // date rules
    const RULE_DATE = 'date';

    const RULE_DATETIME = 'datetime';

    const RULE_TIME = 'time';
    
    // number rules
    const RULE_BETWEEN = 'between';

    const RULE_GREATER_THAN = 'greaterthan';

    const RULE_LESS_THAN = 'lessthan';

    const RULE_NUMBER = 'number';

    const RULE_INTEGER = 'integer';
    // regular expression rules
    const RULE_REGEX = 'regex';

    const RULE_NOT_REGEX = 'notregex';
    // other rules
    const RULE_EMAIL = 'email';

    const RULE_EMAIL_DOMAIN = 'emaildomain';

    const RULE_URL = 'url';

    const RULE_WEBSITE = 'website';

    const RULE_IP = 'ipaddress';

    const RULE_MATCH = 'match';

    const RULE_EQUAL = 'equal';

    const RULE_CALLBACK = 'callback';

    /**
     * This is set after the validation is performed so, in case data
     * does not change, the validation is not executed again
     *
     * @var boolean
     */
    protected $wasValidated = false;

    /**
     * The validation rules
     *
     * @var array
     */
    protected $rules = array();

    /**
     * The error messages generated after validation or set manually
     *
     * @var array
     */
    protected $messages = array();

    /**
     * The prototype that will be used to generate the error message
     *
     * @var \Sirius\Validation\ErrorMessage
     */
    protected $errorMessagePrototype;

    /**
     * The object that will contain the data
     *
     * @var \Sirius\Validation\DataWrapper\WrapperInterface
     */
    protected $dataWrapper;

    /**
     *
     * @var \Sirius\Validation\ValidatorFactory
     */
    protected $validatorFactory;

    function __construct($rules = null, ValidatorFactory $validatorFactory = null)
    {
        if ($validatorFactory) {
            $this->validatorFactory = $validatorFactory;
        }
        if (is_array($rules) or $rules instanceof Traversable) {
            foreach ($rules as $rule) {
                call_user_func_array(array(
                    $this,
                    'add'
                ), $rule);
            }
        }
    }

    /**
     * Retrieve the validator factory
     *
     * @return \Sirius\Validation\ValidatorFactory
     */
    function getValidatorFactory()
    {
        if (! $this->validatorFactory) {
            $this->validatorFactory = new ValidatorFactory();
        }
        return $this->validatorFactory;
    }

    /**
     * Sets the error message prototype that will be used when returning the error message
     * when validation fails.
     * This option can be used when you need translation
     *
     * @param \Sirius\Validation\ErrorMessage $errorMessagePrototype            
     * @throws \InvalidArgumentException
     * @return \Sirius\Validation\Validator\AbstractValidator
     */
    function setErrorMessagePrototype(\Sirius\Validation\ErrorMessage $errorMessagePrototype)
    {
        $this->errorMessagePrototype = $errorMessagePrototype;
        return $this;
    }

    /**
     * Retrieve the error message prototype
     *
     * @return \Sirius\Validation\ErrorMessage
     */
    function getErroMessagePrototype()
    {
        if (! $this->errorMessagePrototype) {
            $this->errorMessagePrototype = new ErrorMessage();
        }
        return $this->errorMessagePrototype;
    }

    /**
     * Add 1 or more validation rules to a selector
     *
     * @example // add multiple rules at once
     *          $validator->add(array(
     *          'field_a' => 'required',
     *          'field_b' => array('required', array('email', null, '{label} must be an email', 'Field B')),
     *          ));
     *          // add multiple rules using arrays
     *          $validator->add('field', array('required', 'email'));
     *          // add multiple rules using a string
     *          $validator->add('field', 'required | email');
     *          // add validator with options
     *          $validator->add('field', 'minlength', array('min' => 2), '{label} should have at least {min} characters', 'Field');
     *          // add validator with string and parameters as JSON string
     *          $validator->add('field', 'minlength({"min": 2})({label} should have at least {min} characters)(Field)');
     *          // add validator with string and parameters as query string
     *          $validator->add('field', 'minlength(min=2)({label} should have at least {min} characters)(Field)');
     * @param string $selector            
     * @param string|callback $name            
     * @param string|array $options            
     * @param string $messageTemplate            
     * @param string $label            
     * @return \Sirius\Validation\Validator
     */
    function add($selector, $name = null, $options = null, $messageTemplate = null, $label = null)
    {
        // the $selector is an associative array with $selector => $rules
        if (func_num_args() == 1) {
            if (! is_array($selector)) {
                throw new \InvalidArgumentException('If $selector is the only argument it must be an array');
            }
            
            foreach ($selector as $valueSelector => $rules) {
                // multiple rules were passed for the same $valueSelector
                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        // the rule is an array, this means it contains $name, $options, $messageTemplate, $label
                        if (is_array($rule)) {
                            array_unshift($rule, $valueSelector);
                            call_user_func_array(array(
                                $this,
                                'add'
                            ), $rule);
                            // the rule is only the name of the validator
                        } else {
                            $this->add($valueSelector, $rule);
                        }
                    }
                    // a single rule was passed for the $valueSelector
                } else {
                    $this->add($valueSelector, $rules);
                }
            }
            return $this;
        }
        if (is_array($name) && ! is_callable($name)) {
            foreach ($name as $singleRule) {
                // make sure the rule is an array (the parameters of subsequent calls);
                $singleRule = is_array($singleRule) ? $singleRule : array(
                    $singleRule
                );
                array_unshift($singleRule, $selector);
                call_user_func_array(array(
                    $this,
                    'add'
                ), $singleRule);
            }
            return $this;
        }
        if (is_string($name)) {
            // rule was supplied like 'required' or 'required | email'
            if (strpos($name, ' | ') !== false) {
                return $this->add($selector, explode(' | ', $name));
            }
            // rule was supplied like this 'length(2,10)(error message template)(label)'
            if (strpos($name, '(') !== false) {
                list ($name, $options, $messageTemplate, $label) = $this->parseRule($name);
            }
        }
        $validator = $this->getValidatorFactory()->createValidator($name, $options, $messageTemplate, $label);
        $validator->setErrorMessagePrototype($this->getErroMessagePrototype());
        
        if (! array_key_exists($selector, $this->rules)) {
            $this->rules[$selector] = array();
        }
        if (! $this->hasValidator($selector, $validator)) {
            // required validators should come first
            if ($validator instanceof Validator\Required) {
                array_unshift($this->rules[$selector], $validator);
            } else {
                $this->rules[$selector][] = $validator;
            }
        }
        return $this;
    }

    /**
     * Remove validation rule
     *
     * @param string $selector
     *            data selector
     * @param mixed $name
     *            rule name or true if all rules should be deleted for that selector
     * @param mixed $options
     *            rule options, necessary for rules that depend on params for their ID
     * @return self
     */
    function remove($selector, $name = true, $options = null)
    {
        if (! array_key_exists($selector, $this->rules)) {
            return $this;
        }
        if ($name === true) {
            unset($this->rules[$selector]);
        } else {
            $validator = $this->getValidatorFactory()->createValidator($name, $options);
            $validator->setErrorMessagePrototype($this->getErroMessagePrototype());
            foreach ($this->rules[$selector] as $k => $v) {
                if ($v->getUniqueId() == $validator->getUniqueId()) {
                    unset($this->rules[$selector][$k]);
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * Verify if a specific selector has a validator associated with it
     *
     * @param string $selector            
     * @param \Sirius\Validation\Validator\AbstractValidator $validator            
     * @return boolean
     */
    function hasValidator($selector, \Sirius\Validation\Validator\AbstractValidator $validator)
    {
        if (! array_key_exists($selector, $this->rules) || ! $this->rules[$selector]) {
            return false;
        }
        foreach ($this->rules[$selector] as $k => $v) {
            if ($v->getUniqueId() == $validator->getUniqueId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converts a rule that was supplied as string into a set of options that define the rule
     *
     * @example 'minLength({"min":2})({label} must have at least {min} characters)(Street)'
     *         
     *          will be converted into
     *         
     *          array(
     *          'minLength', // validator name
     *          array('min' => 2'), // validator options
     *          '{label} must have at least {min} characters',
     *          'Street' // label
     *          )
     * @param string $ruleAsString            
     * @return array
     */
    protected function parseRule($ruleAsString)
    {
        $ruleAsString = trim($ruleAsString);
        $name = '';
        $options = array();
        $messageTemplate = null;
        $label = null;
        
        $name = substr($ruleAsString, 0, strpos($ruleAsString, '('));
        $ruleAsString = substr($ruleAsString, strpos($ruleAsString, '('));
        $matches = array();
        preg_match_all('/\(([^\)]*)\)/', $ruleAsString, $matches);
        
        if (isset($matches[1])) {
            if (isset($matches[1][0]) && $matches[1][0]) {
                $options = $matches[1][0];
            }
            if (isset($matches[1][1]) && $matches[1][1]) {
                $messageTemplate = $matches[1][1];
            }
            if (isset($matches[1][2]) && $matches[1][2]) {
                $label = $matches[1][2];
            }
        }
        
        return array(
            $name,
            $options,
            $messageTemplate,
            $label
        );
    }

    /**
     * The data wrapper will be used to wrap around the data passed to the validator
     * This way you can validate anything, not just arrays (which is the default)
     *
     * @return \Sirius\Validation\DataWrapper\WrapperInterface
     */
    function getDataWrapper()
    {
        if (! $this->dataWrapper) {
            $this->dataWrapper = new DataWrapper\ArrayWrapper();
        }
        return $this->dataWrapper;
    }

    /**
     *
     * @param Sirius\Validation\DataWrapper\WrapperInterface $wrapper            
     * @return \Sirius\Validation\Validator
     */
    function setDataWrapper(Sirius\Validation\DataWrapper\WrapperInterface $wrapper)
    {
        $this->dataWrapper = $wrapper;
        return $this;
    }

    function setData($data)
    {
        $this->getDataWrapper()->setData($data);
        $this->wasValidated = false;
        // reset messages
        $this->messages = array();
        return $this;
    }

    /**
     * Performs the validation
     *
     * @param mixed $data
     *            array to be validated
     * @return boolean
     */
    function validate($data = null)
    {
        if ($data !== null) {
            $this->setData($data);
        }
        // data was already validated, return the results immediately
        if ($this->wasValidated === true) {
            return $this->wasValidated and count($this->messages) === 0;
        }
        foreach ($this->rules as $selector => $selectorRules) {
            foreach ($this->getDataWrapper()->getItemsBySelector($selector) as $item => $value) {
                $this->validateSingle($item, $value, $selectorRules);
            }
        }
        $this->wasValidated = true;
        return $this->wasValidated and count($this->messages) === 0;
    }

    protected function validateSingle($item, $value, $rules)
    {
        $isRequired = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Validator\Required) {
                $isRequired = true;
                break;
            }
        }
        foreach ($rules as $ruleId => $rule) {
            if (! $this->valueSatisfiesRule($value, $item, $rule)) {
                $this->addMessage($item, $rule->getMessage());
            }
            // if field is required and we have an error,
            // do not continue with the rest of rules
            if ($isRequired && array_key_exists($item, $this->messages) && count($this->messages[$item])) {
                break;
            }
        }
    }

    protected function valueSatisfiesRule($value, $item, $rule)
    {
        $rule->setContext($this->getDataWrapper());
        return $rule->validate($value, $item);
    }

    /**
     * Adds a messages to the list of error messages
     *
     * @param string $item
     *            data identifier (eg: 'email', 'addresses[0][state]')
     * @param string $message            
     * @return self
     */
    function addMessage($item, $message = null)
    {
        if (! $message) {
            return;
        }
        if (! array_key_exists($item, $this->messages)) {
            $this->messages[$item] = array();
        }
        $this->messages[$item][] = $message;
        
        return $this;
    }

    /**
     * Clears the messages of an item
     *
     * @param string $item            
     * @return self
     */
    function clearMessages($item = null)
    {
        if (is_string($item)) {
            if (array_key_exists($item, $this->messages)) {
                unset($this->messages[$item]);
            }
        } elseif ($item === null) {
            $this->messages = array();
        }
        return $this;
    }

    /**
     * Returns all validation messages
     *
     * @param string $item
     *            key of the messages array (eg: 'password', 'addresses[0][line_1]')
     * @return array
     */
    function getMessages($item = null)
    {
        if (is_string($item)) {
            return array_key_exists($item, $this->messages) ? $this->messages[$item] : array();
        }
        return $this->messages;
    }

    function getRules()
    {
        return $this->rules;
    }
}
