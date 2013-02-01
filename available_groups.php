<?php
/**
 * Show a list of all available groups
 *
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 17 to 20 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1  - Initial version
 */

// Load the application
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT ."/../../www/config.php");
require_once(FS_ROOT ."/../../www/lib/nntp.php");
require_once('nnscripts.php');

/**
 * Display group statistics
 */
class availableGroups
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts; 
    
    /**
     * The database connection
     * @var Nntp
     */
    private $nntp;
    
    /**
     * The cache file
     * @var string
     */
    private $cacheFileName = 'available_groups.cache';
    
    /**
     * The commandline options
     * @var array
     */
    private $options = array();
    
    /**
     * The groups
     * @var array
     */
    private $groups = array();
    
    
    
    /**
     * Constructor
     *
     * @param NNScripts $nnscripts
     * @param DB $db
     */
    public function __construct( NNScripts $nnscripts, Nntp $nntp )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the nntp variable
        $this->nntp = $nntp;
        
        // Check the commandline options
        $this->setOptions();
        
        // get the group_list
        $this->getGroupList();
    }
    
    
    /**
     * Parse the commandline options
     * 
     * @return void
     */
    private function setOptions()
    {
        $shortopts = "";
        $shortopts .= "s:";
        $shortopts .= "u";
        $shortopts .= "h";
        $longopts = array(
            "search:",
            "update-cache",
            "help"
        );
        $options = getopt($shortopts, $longopts);
        
        // Show help?
        if( array_key_exists('h', $options) )
            $this->help();
            
        // Set the options globaly
        $this->options = $options;
    }
    
    
    /**
     * Display help
     * 
     * @return void
     */
    private function help()
    {
        echo "Syntax: php available_groups.php [options]\n";
        echo "\n";
        echo "Options:\n";
        echo "   -s, --search              Search for a specific group (or groups with a wildcard)\n";
        echo "                               example \"alt.binaries.teevee\" or \"alt.binaries.*\"\n";
        echo "                               when using wildcards make sure the put the search string between quotes\n";
        echo "   -u, --update-cache        This switch is used when you want to force update the caches group list\n";
        echo "\n";
        exit(0);
    }
    
    
    /**
     * Get all the groups
     * 
     * @return void
     */
    private function getGroupList()
    {
        // Check for cached versions
        $groups = $this->getCachedVersion();
        if( false === $groups )
        {
            // Get the groups from the nntp server
            $this->nnscripts->display( "Updating groups from server: " );
            $this->nntp->doConnect();
            $data = $this->nntp->getGroups();
            
            $groups = array();
            if( is_array( $data ) )
            {
                foreach( $data AS $row )
                {
                    $groups[] = $row['group'];
                }
            }
            $this->nnscripts->display( "done" . PHP_EOL );
           
            // The the cache
            $this->writeCacheFile( $groups );
        }
        
        $this->groups = $groups;
    }
    
    
    /**
     * Display the groups
     * 
     * @return void
     */
    public function display()
    {
        $this->nnscripts->display( sprintf("Total number of groups: %d", count($this->groups) ) . PHP_EOL );
        
        // Search?
        if( array_key_exists('s', $this->options) )
        {
            $this->search();
        }
        
        // Display the groups
        if( is_array( $this->groups ) && 0 < count($this->groups) )
        {
            // Spacer
            $this->nnscripts->display( PHP_EOL );
            
            // Loop the groups
            foreach( $this->groups AS $row )
            {
                $this->nnscripts->display( $row . PHP_EOL );
            }
        }
        
        // Spacer
        $this->nnscripts->display( PHP_EOL );
    }
    
    
    /**
     * Search
     * 
     * @return void
     */
    private function search()
    {
        // Init
        $ret = array();
        $pattern = '/^'. str_replace(array('.','*'), array('\.','.*?'), $this->options['s']) .'$/i';
        
        // Search
        foreach( $this->groups AS $row )
        {
            if( preg_match( $pattern, $row ) )
            {
                $ret[] = $row;
            }
        }
        $this->groups = $ret;
         
        // Display search
        $this->nnscripts->display( sprintf("Total number of groups found: %d", count($ret) ) . PHP_EOL );
    }
    
    /**
     * Get the cached version
     * 
     * @return array|bool
     */
    private function getCachedVersion()
    {
        // init
        $ret = false;
        
        if( !array_key_exists('u', $this->options) )
        {
            if( file_exists( $this->cacheFileName ) && is_readable( $this->cacheFileName ) )
            {
                // Check cache age (1 day)
                if( filemtime($this->cacheFileName) >= (time() - 86400) )
                {
                    $ret = file_get_contents( $this->cacheFileName );
                    $ret = unserialize( $ret );
                }
            }
        }
        
        // Return
        return $ret;
    }
    
    
    /**
     * Write cache file
     * 
     * @return void
     */
    private function writeCacheFile( $data )
    {
        if( false !== ($f = @fopen( $this->cacheFileName, 'wb') ) )
        {
            fwrite($f, serialize($data));
            fclose($f);
        }
        @chmod( $this->cacheFileName, 0666 );   
    }
}


try
{
    // Init
    $scriptName    = 'Available newsgroups';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();    

    // Load the nntp connection
    $nntp = new nntp(); 
    if( !$nntp )
        throw new Exception("Error loading nntp library");
        
    // Display the available groups
    $groups = new availableGroups( $nnscripts, $nntp );
    $groups->display();
    
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}