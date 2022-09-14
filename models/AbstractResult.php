<?php
namespace app\models;

abstract class AbstractResult
{
    /**
     * General Failure
     */
    const FAILURE                        =  0;

    /**
     * Failure due to item not being found.
     */
    const FAILURE_IDENTITY_NOT_FOUND     = -1;

    /**
     * Failure due to item being ambiguous.
     */
    const FAILURE_IDENTITY_AMBIGUOUS     = -2;

    /**
     * Failure due to invalid credential being supplied.
     */
    const FAILURE_CREDENTIAL_INVALID     = -3;

    /**
     * Failure due to uncategorized reasons.
     */
    const FAILURE_UNCATEGORIZED          = -4;

    /**
     * Action success.
     */
    const SUCCESS                        =  1;

    /**
     * Action result code
     *
     * @var int
     */
    protected $_code;


    /**
     * An array of string reasons why the Action attempt was unsuccessful
     *
     * If Action was successful, this should be an empty array.
     *
     * @var array
     */
    protected $_messages;

    /**
     * Result data
     *
     * If data is exists it is stored here.
     *
     * @var string
     */
    private $_data;    
    
    
    /**
     * Sets the result code, and failure messages
     *
     * @param  int     $code
     * @param  array   $messages
     * @return void
     */
    public function __construct($code, $messages, $data = null)
    {
        $code = (int) $code;

        if ($code < self::FAILURE_UNCATEGORIZED) {
            $code = self::FAILURE;
            $this->_data = null;            
        } elseif ($code >= self::SUCCESS ) {
            $code = 1;
            $this->_data = $data;
        }

        $this->_code     = $code;
        $this->_messages = $messages;        
    }

    /**
     * Returns whether the result represents a successful action
     *
     * @return boolean
     */
    public function success()
    {
        return ($this->_code > 0) ? true : false;
    }

    /**
     * getCode() - Get the result code for this Action attempt
     *
     * @return int
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Returns an array of string messages when result is unsuccessful
     *
     * If action was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->_messages;
    }
    
    /**
     * Returns string containing data
     *
     * If action was successful and data is returned this value will be non-null
     *
     * @return String
     */
    public function getData()
    {
    	if ($this->success())
    	{
        	return $this->_data;
    	} else {
    		return null;
    	}
    }    
    
    /**
     * Parse xml data and return array
     *
     * @return Array
     */    
	abstract public function to_a();
	
	public function getTitleCount()
	{
		return is_array($this->_data) ? count($this->_data) : 0;
	}

}
