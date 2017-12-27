<?php

/**
 * Copyright (c) 2013 Will Hattingh (https://github.com/Nitecon
 *
 * For the full copyright and license information, please view
 * the file LICENSE.txt that was distributed with this source code.
 *
 * @author Will Hattingh <w.hattingh@nitecon.com>
 *
 *
 */
namespace ZfcUserLdap\Adapter;

use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\Ldap as AuthAdapter;
use Zend\Ldap\Exception\LdapException;
use Zend\Ldap\Ldap as ZendLdap;

class Ldap
{
	private $config;
	/** @var Zend\Ldap\Ldap */
	protected $ldap;
	/**
	 * Array of server configuration options, active server is
	 * set to the first server that is able to bind successfully
	 *
	 * @var array
	 */
	protected $active_server;
	/**
	 * An array of error messages.
	 *
	 * @var array
	 */
	protected $error = array();
	/**
	 * Log writer
	 *
	 * @var Zend\Log\Logger
	 */
	protected $logger;
	/** @var bool */
	protected $logEnabled;
	public function __construct($config, $logger, $logEnabled)
	{
		$this->config = $config;
		$this->logger = $logger;
		$this->logEnabled = $logEnabled;
	}
	/**
	 *
	 * @param type $msg
	 * @param type $log_level
	 *        	EMERG=0, ALERT=1, CRIT=2, ERR=3, WARN=4, NOTICE=5, INFO=6, DEBUG=7
	 */
	public function log($msg, $priority = 5)
	{
		if($this->logEnabled)
		{
			if(! is_string ( $msg ))
			{
				$this->logger->log ( $priority, var_export ( $msg, true ) );
			}
			else
			{
				$this->logger->log ( $priority, $msg );
			}
		}
	}
	/**
	 *
	 */
	public function bind()
	{
		$options = $this->config;
		/*
		 * We will try to loop through the list of servers
		 * if no active servers are available then we will use the error msg
		 */
		foreach ( $options as $server )
		{
			$this->log ( "Attempting bind with ldap" );
			try
			{
				$this->ldap = new ZendLdap ( $server );
				if ($this->config['username'])
				{
					$this->ldap->bind ($this->config['username'],$this->config['password']);
				}
				else
					$this->ldap->bind ();
				$this->log ( "Bind successful setting active server." );
				$this->active_server = $server;
			}
			catch ( LdapException $exc )
			{
				$this->error[] = $exc->getMessage ();
				continue;
			}
		}
	}
	/**
	 *
	 * @param unknown $username
	 * @return unknown
	 */
	public function findByUsername($username)
	{
		/**
		 * This is an anonymous bind, which may not work in all cases.
		 */
		$this->bind ();
		$entryDN =  $this->active_server['baseDn'];
		$this->log ( __FILE__." : ".__LINE__." : Attempting to get username:  $username entry: $entryDN against the active ldap server" );
		$filter="(sAMAccountName=$username)";
//		$result = ldap_search($this->ldap, $this->active_server['baseDn'],$filter);
		$result = ldap_search($this->ldap, "dc=OMNILINK,dc=COM,dc=AU",$filter);
		 ldap_sort($this->ldap,$result,"sn");
		 $info = ldap_get_entries($ldap, $result);
		try
		{
			$hm = $this->ldap->getEntry ( $entryDN );
			$this->log ( "Raw Ldap Object: " . var_export ( $hm, true ), 7 );
			$this->log ( "Username entry lookup response: " . var_export ( $hm, true ) );
			return $hm;
		}
		catch ( LdapException $exc )
		{
			return $exc->getMessage ();
		}
	}

	/**
	 *
	 * @param unknown $email
	 * @return unknown|boolean
	 */

	public function findByEmail($email)
	{
		$this->bind ();
		$this->log ( "Attempting to search ldap by email for $email against the active ldap server" );
		try
		{
			$hm = $this->ldap->search ( "mail=$email", $this->active_server['baseDn'], ZendLdap::SEARCH_SCOPE_ONE );
			$this->log ( "Raw Ldap Object: " . var_export ( $hm, true ), 7 );
			foreach ( $hm as $item )
			{
				$this->log ( $item );
				return $item;
			}
			return false;
		}
		catch ( LdapException $exc )
		{
			$msg = $exc->getMessage ();
			$this->log ( $msg );
			return $msg;
		}
	}
	/**
	 *
	 * @param unknown $id
	 * @return unknown|boolean
	 */
	public function findById($id)
	{
		$this->bind ();
		$this->log ( "Attempting to search ldap by uidnumber for $id against the active ldap server" );
		try
		{
			$hm = $this->ldap->search ( "uidnumber=$id", $this->active_server['baseDn'], ZendLdap::SEARCH_SCOPE_ONE );
			$this->log ( "Raw Ldap Object: " . var_export ( $hm, true ), 7 );
			foreach ( $hm as $item )
			{
				$this->log ( $item );
				return $item;
			}
			return false;
		}
		catch ( LdapException $exc )
		{
			$msg = $exc->getMessage ();
			$this->log ( $msg );
		}
	}
	/**
	 *
	 * @param string $username
	 * @param string $password
	 * @return boolean|array
	 */
	public function authenticate($username, $password)
	{
		$this->bind ();
		$options = $this->config;
		$auth = new AuthenticationService ();
		$this->log ( "Attempting to authenticate $username" );
		$adapter = new AuthAdapter ( $options, $username, $password );
		$result = $auth->authenticate ( $adapter );
		if($result->isValid ())
		{
			$this->log ( "$username logged in successfully!" );
			$this->ldap = $adapter->getLdap();
			/**
			 * Set ldap->options['username'] and ldap->options['password'] to avoid the need to use
			 * an anonymous loakup later
			 */
			$options = $this->ldap->getOptions();
			$this->config['username'] = $username;
			$this->config['password'] = $password;
			return true;
		}
		else
		{
			$messages = $result->getMessages ();
			$this->log ( "$username AUTHENTICATION FAILED!, error output: " . var_export ( $messages, true ) );

			return $messages;
		}
	}
}
