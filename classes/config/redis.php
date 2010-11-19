<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Resis based configuration reader
 *
 * @package koredis-config
 * @author Ben Haan <benhaan@gmail.com>
 */

class Config_Redis extends Kohana_Config_Reader {
    
    // Configuration group name
    protected $_configuration_group;

    // Redis object
    protected $_redis;
    
    // The awesome constructor
    public function __construct()
    {
        // Check that Redis extension is installed
        if ( ! extension_loaded('redis') === TRUE)
        {
            throw new Kohana_Exception('Redis extension is not loaded');
        }
        
        // Instantiate redis object
        $this->_redis = new Redis;
        
        // Make sure we use the file config loader, since redis won't be available yet :)
        $config = new Kohana_Config_File;
        $config->load('redis');

        // Make sure we can connect to a redis server before we continue
        if ($this->_redis->connect($config->get('host', '127.0.0.1'), $config->get('port', 6379), $config->get('timeout', 0)) === FALSE)
        {
            throw new Kohana_Exception('Error connecting to redis');
        }

        parent::__construct();
    }
    
    /**
     * Loads configuration group from redis
     *
     *    $config->load($name);
     *
     * @param    string    configuration group name
     * @param    array     configuration array
     * @return   $this     clone of the current object
     */
    public function load($group, array $config = NULL)
    {
        if ($this->_redis->sContains('ko::cfg::__group_list', $group) === TRUE)
        {
            $config = array();

            $config_keys = $this->_redis->sMembers('ko:cfg:_'.$group.'_keys');
            
            if (count($config_keys) > 0)
            {
                foreach($config_keys as $key)
                {
                    $config = Arr::merge($config, array($key => $this->_redis->get('ko:cfg:'.$group.'_'.$key));
                }   
            }
        }

        return parent::load($group, $config);
    }

    /**
     * Sets a configuration value in redis and in the configuration array
     *
     *     $config->set($key, $new_value)
     *
     * @param    string    key
     * @param    mixed     value
     * @return   $this
     */
    public function set($key, $value, $ttl = FALSE)
    {

        $group = $this->_configuration_group;

        if ($ttl !== FALSE)
        {
            $this->_redis->setex('ko:cfg:'.$group.'_'.$key, $ttl, $value);
        }
        else
        {
            $this->_redis->set('ko:cfg:'.$group.'_'.$key, $value);
        }

        // If we are adding a new key, we need to make sure it ends up in the list, so it will be loaded next time.
        if ($this->_redis->sContains('ko:cfg:_'.$group.'_keys', $key) === FALSE)
        {
            $this->_redis->sAdd('ko:cfg:_'.$group.'_keys', $key);
        }

        parent::set($key, $value);
    } 

}
