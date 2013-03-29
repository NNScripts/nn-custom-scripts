<?php
/**
 * Show a list of all available groups
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.4 - Start using nnscript library
 * 
 * 0.3 - Sorting results
 *       longer cache time (1 week)
 * 
 * 0.2 - Fixed cache path
 * 
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/nntp.php");


/**
 * Display available groups
 */
class available_groups extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Available newsgroups';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.4';
     
    /**
     * Help spacer
     * @var string
     */
    private $spacer = "                          ";
    
    /**
     * The database connection
     * @var Nntp
     */
    private $nntp;
    
    /**
     * The cache file
     * @var string
     */
    private $cacheFileName;
    
    /**
     * The groups
     * @var array
     */
    private $groups = array();
    
    
    /**
     * The constructor
     *
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();

        // Set the commandline options
        $options = array(
            array( 's', 'search', Ulrichsg\Getopt::REQUIRED_ARGUMENT, 'search for a specific group (or groups with a wildcard)'. PHP_EOL . $this->spacer .'example "alt.binaries.teevee" or "alt.binaries.*"'. PHP_EOL . $this->spacer .'when using wildcards make sure the put the search string between quotes'. PHP_EOL ),
            array( 'u', 'update-cache',  Ulrichsg\Getopt::NO_ARGUMENT,      'force update the caches group list' ),
        );
        $this->setCliOptions( $options, array('help') );

        // Show the header
        $this->displayHeader();
        
        // Set the cache file
        $this->cacheFileName = FS_ROOT . DIRECTORY_SEPARATOR . 'available_groups.cache';

        // Init the nntp library
        $this->nntp = new nntp(); 
        if( !$this->nntp )
            throw new Exception("Error loading nntp library");
            
        // get the group_list
        $this->getGroupList();
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
            $this->display( "Updating groups from server: " );
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
            $this->display( "done" . PHP_EOL );

            // Sort
            $this->display( "Sorting results: " );
            usort( $groups, function($a, $b) {
                return strnatcasecmp( $a, $b );
            });
            $this->display( "done" . PHP_EOL );
           
            // The the cache
            $this->writeCacheFile( $groups );
        }
        
        $this->groups = $groups;
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
        
        // Only continue if not forced updated
        if( null === $this->options->getOption('update-cache') )
        {
            if( file_exists( $this->cacheFileName ) && is_readable( $this->cacheFileName ) )
            {
                // Check cache age (1 day)
                if( filemtime($this->cacheFileName) >= (time() - 604800) )
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
    
    
    /**
     * Display the groups
     * 
     * @return void
     */
    public function show()
    {
        $this->display( sprintf("Total number of groups: %d", count($this->groups) ) . PHP_EOL );
        
        // Search?
        if( null !== $this->options->getOption('search') )
        {
            $this->search();
        }
        
        // Display the groups
        if( is_array( $this->groups ) && 0 < count($this->groups) )
        {
            // Spacer
            $this->display( PHP_EOL );

            // Loop the groups
            foreach( $this->groups AS $row )
            {
                $this->display( $row . PHP_EOL );
            }
        }
        
        // Spacer
        $this->display( PHP_EOL );
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
        $search = $this->options->getOptions('search');
        $search = ( array_key_exists( 'search', $search ) ? $search['search'] : $search['s'] );
        $pattern = '/^'. str_replace( array('.','*'), array('\.','.*?'), $search ) .'$/i';
        
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
        $this->display( sprintf("Total number of groups found: %d", count($ret) ) . PHP_EOL );
    }
}

// Main application
try
{
    // Display the available groups
    $groups = new available_groups();
    $groups->show();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}