<?php
/**
 * Test is a string/release name is matched by one of the black/white
 * list regexes.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 26 to 29 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1 - Initial version
 */

//----------------------------------------------------------------------
// Settings

// Display settings
define('DISPLAY', true);
//----------------------------------------------------------------------

// Load the application
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT ."/../../www/config.php");
require_once(WWW_DIR."/lib/releases.php");
require_once('nnscripts.php');


/**
 * Test is a string is catched by one of the black/white list regexes
 */
class testBlacklist
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts;
    
    /**
     * The database object
     * @var DB
     */
    private $db;
    
    /**
     * The commandline options
     * @var array
     */
    private $options = array();
    
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
     * Constructor
     * 
     * @param NNScripts $nnscripts
     * @param DB $db
     */
    public function __construct( NNScripts $nnscripts, DB $db )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database variable
        $this->db = $db;
        
        // Set the commandline options
        $this->setOptions();

        // Get a list of the active groups with theire regexes
        $this->getGroupRegexes();
    }


    /**
     * Parse the commandline options
     * 
     * @return void
     */
    protected function setOptions()
    {
        $shortopts = "hs:g:";
        $longopts = array("help", "string:", "group:");
        $optionsRaw = getopt($shortopts, $longopts);
        $options = array();
        
        // Show help?
        if( array_key_exists('h', $optionsRaw ) )
            $this->help();
            
        // Build the options array
        foreach( $optionsRaw AS $key => $value )
        {
            switch( $key )
            {
                case 'g':
                case 'group':
                        $options['group'] = preg_replace( '/^a\.b\./i', 'alt.binaries.', strtolower( $value ) );
                        break;
                case 's':
                case 'string':
                        $options['string'] = $value;
                        break;
            }
        }
            
        // Validate
        if( !array_key_exists('string', $options) )
        {
            throw new Exception('Error: test string must be provided!');
        }
            
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
        echo "   -s, --string       The string to perform the tests on\n";
        echo "   -g, --group        This group to test against.\n";
        echo "                        By default all groups are tested\n";
        echo "\n";
        exit(0);
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
        if( array_key_exists('group', $this->options) )
        {
            $groups = array(
                "'". $this->options['group'] ."'"
            );
            $sql .= sprintf( " AND b.groupname IN (%s)", implode(',', $groups) );
        }
        
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
     * Start the testing
     * 
     * @return void
     */
    public function test()
    {
        // Header
        $this->nnscripts->display( sprintf( 'Testing on string: %s'. PHP_EOL, $this->options['string'] ) );
        
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
                            $this->displayResult( $group, $id, $regex, $type, $result );
                        }
                    }
                }
            }
        }
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
                if( !preg_match_all( $regex, $this->options['string'] ) )
                {
                    return true;
                }
                break;
                
            case 'blacklist':
                if( preg_match_all( $regex, $this->options['string'], $matches ) )
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
        
        $this->nnscripts->display( PHP_EOL . $template );
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


try
{
    // Init
    $scriptName    = 'Test black and whitelists';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();
    
    $db = new DB;
    if( !$db )
        throw new Exception("Error loading database library");
        
    // Load the blacklistReleases class
    $blr = new testBlacklist( $nnscripts, $db );
    $blr->test();
    
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}