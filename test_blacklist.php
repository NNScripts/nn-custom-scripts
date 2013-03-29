<?php
/**
 * Test is a string/release name is matched by one of the black/white
 * list regexes.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Start using nnscript library
 * 
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/releases.php");


/**
 * Test white and blacklists
 */
class test_blacklist extends NNscripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Test black and whitelists';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.2';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display');
    
    /**
     * Help spacer
     * @var string
     */
    private $spacer = "                          ";
    
    /**
     * A list of all the active regexes
     * @var array
     */
    private $regexes;
    
    /**
     * The known list types
     * @var array
     */
    private $listType = array(
        '1' => 'blacklist',
        '2' => 'whitelist'
    );
    
    
    
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
            array( 's', 'string', Ulrichsg\Getopt::REQUIRED_ARGUMENT, 'The string to perform the tests on' ),
            array( 'g', 'group', Ulrichsg\Getopt::REQUIRED_ARGUMENT, 'The group to test against.'. PHP_EOL . $this->spacer .'By default all groups are tested' ),
        );
        $this->setCliOptions( $options, array('help') );
        
        // Show the header
        $this->displayHeader();

        // Show the settings
        $this->displaySettings();
    }
    
    
    /**
     * Test the black and white lists
     * 
     * @return void
     */
    public function test()
    {
        // Is a string provided?
        if( null === $this->options->getOption('string') )
        {
            global $argv;
            $arguments = $argv;
            $scriptName = array_shift($arguments);
            $this->display( "Error: test string must be provided!". PHP_EOL . sprintf( "Use php %s -h for help.", $scriptName ) . PHP_EOL );
            die();
        }
        
        // Get a list of the active groups with theire regexes
        $this->getGroupRegexes();
        
        // Header
        $this->display( sprintf( 'Testing on string: %s'. PHP_EOL, $this->options->getOption('string') ) );
        
        // Init
        $found = false;
        
        // Loop all the groups
        foreach( $this->regexes AS $group => $gRegexes )
        {
            // Loop the list types
            foreach( $this->listType AS $type )
            {
                // Test if listType exists
                if( array_key_exists( $type, $gRegexes ) )
                {
                    // Loop all the regexes
                    foreach( $gRegexes[ $type ] AS $id => $regex )
                    {
                        // Test a single regex
                        $result = $this->testSingleRegex( $regex, $type );
                        if( false !== $result )
                        {
                            // Display result
                            $found = true;
                            $this->displayResult( $group, $id, $regex, $type, $result );
                        }
                    }
                }
            }
        }
        
        if( false === $found )
        {
            $this->display( PHP_EOL .'No match found!' . PHP_EOL);
        }
        $this->display( PHP_EOL );
    }
    
    
    /**
     * Get all the black/whitelist regexes for a group
     * 
     * @param array $group
     * @return array
     */
    protected function getGroupRegexes()
    {
        // Init
        $ret = array();
        
        // Get all the active regexes for a group
        $sql = "SELECT b.ID, b.groupname, b.regex, b.optype
                FROM binaryblacklist AS b
                WHERE b.msgcol = 1
                AND b.status = 1";
                
        // Add a group?
        if( null !== $this->options->getOption('group') )
        {
            $group = preg_replace( '/^a\.b\./i', 'alt.binaries.', $this->options->getOption('group') );
            $sql .= sprintf( " AND b.groupname = '%s'", $group );
        }

        // Execute the query
        $dbRegexes = $this->db->query( $sql );
        if( is_array( $dbRegexes ) && 0 < count( $dbRegexes ) )
        {
            // Build the regexes array
            foreach( $dbRegexes AS $row )
            {
                // Add the regex
                $ret[ $row['groupname'] ][ $this->listType[ $row['optype'] ] ][ $row['ID'] ] = $row['regex'];
            }          
        }
        
        // Return
        $this->regexes = $ret;
    }
    
    
    /**
     * Test a single regex
     * 
     * @return bool|string
     */
    protected function testSingleRegex( $regex, $type )
    {
        // Build the regex
        $regex = sprintf('/%s/i', $regex);
        
        // Try to match
        switch( $type )
        {
            case 'whitelist':
                if( !preg_match_all( $regex, $this->options->getOption('string') ) )
                {
                    return true;
                }
                break;
                
            case 'blacklist':
                if( preg_match_all( $regex, $this->options->getOption('string'), $matches ) )
                {
                    return $this->buildMatches( $matches );
                }
                break;
        }
        
        // Failed, return false
        return false;
    }


    /**
     * Display the result
     * 
     * @return void
     */
    protected function displayResult( $group, $id, $regex, $type, $result )
    {
        // Template
        $template = sprintf( 'Group    : %s'. PHP_EOL
                            .'Reason   : %s'. PHP_EOL
                            .'Regex ID : %d'. PHP_EOL
                            .'Regex    : %s'. PHP_EOL,
            $group,
            ( 'blacklist' === $type ? 'matches blacklist regex' : 'does not match whitelist regex' ),
            $id,
            $regex
        );
        
        if( 'blacklist' === $type )
        {
            $template .= sprintf('Matches  : %s'. PHP_EOL, $result);
        }
        
        $this->display( PHP_EOL . $template );
    }

    
    /**
     * Build the match string
     * 
     * @param array $matches
     * @param bool $last
     * @return string
     */
    protected function buildMatches( $matches, $last=true )
    {
        // Init
        $ret = '';
        $comma = '';
        
        // Loop the found matches
        if( is_array( $matches ) && 0 < count( $matches ) )
        {
            foreach( $matches AS $row )
            {
                if( is_array( $row ) )
                {
                    $row = $this->buildMatches( $row, false );
                }
                
                // Add the row
                $row = trim( $row );
                if( '' !== $row )
                {
                    $ret .= $comma . $row;
                    $comma = '|';
                }
            }
        }
        
        // Build the correct string
        if( true === $last )
        {
            $ret = implode(' | ', array_unique( explode('|', $ret) ) );
        }
        
        // Return
        return $ret;
    }
}


// Main application
try
{
    // Load the test_blacklist class
    $tb = new test_blacklist();
    $tb->test();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}