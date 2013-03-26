<?php
/**
 * Check and repair all database tables
 *
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 26 to 28 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Added MyISAM check on tables
 * 0.1 - Initial version
 */

//----------------------------------------------------------------------
// Settings

// Display settings
define('DISPLAY', true);
//----------------------------------------------------------------------

// changed
// Load the application
define('FL_ROOT', realpath(dirname(__FILE__)));
require_once(FL_ROOT ."/../../../www/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once('nnscripts.php');


/**
 * Check and repair database tables
 */
class checkDatabase
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts;
    
    /**
     * The mysqli database connection
     * @var DB
     */
    private $db;
    
    /**
     * All the database tables
     * @var array
     */
    private $tables = array();

    /**
     * The max table name length
     * @var int
     */
    private $length = 0;



    /**
     * Constructor
     * 
     */
    public function __construct( NNScripts $nnscripts, DB $db ) 
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database connection
        $this->db = $db;

        // Get all the database tables
        $this->getTables();
    }
    
    
    /**
     * Get all the tabels from the database
     * 
     * @return void
     */
    private function getTables()
    {
        $sql = "show tables";
        $tables = $this->db->query( $sql );
        if( is_array($tables) && 0 < count($tables) )
        {
            foreach( $tables AS $table )
            {
                $name = current( $table );
                
                // Check if database table is of myisam type
                // Repair only works on myisam
                $sql = sprintf('SHOW TABLE STATUS WHERE Name = "%s"', $name);
                $tableInfo = $this->db->query( $sql );
                if( is_array($tableInfo) && 1 === count($tableInfo) )
                {
                    $info = current($tableInfo);
                    if( 'myisam' === strtolower( $info['Engine'] ) )
                    {
                        $this->tables[] = $name;
                        $this->length = ( $this->length < mb_strlen( $name ) ? mb_strlen( $name ) : $this->length );
                    }
                }
            }
        }
    }
        
    /**
     * Check and repair all tables
     * 
     * @return void
     */
    public function checkAndRepair()
    {
        $allowed = array(
            'Found row where the auto_increment column has the value 0',
            'Table is already up to date',
            'OK'
        );

        if( 0 < count( $this->tables ) )
        {
            // Build the template string
            $template = sprintf("Checking: %%-%ds : ", $this->length);

            // Loop all the tables
            foreach( $this->tables as $table )
            {
                $this->nnscripts->display( sprintf($template, $table) );

                $sql = sprintf("CHECK TABLE `%s` QUICK", $table);
                $result = $this->db->query( $sql );

                if( is_array($result) && 0 < count($result) )
                {
                    $row = $result[0];
                    if( !in_array( $row['Msg_text'], $allowed ) )
                    {
                        $this->nnscripts->display( $row['Msg_text'] .' : Starting repair ' );
                        $sql = sprintf("REPAIR TABLE `%s`", $table);
                        $result = $this->db->query( $sql );
                        $this->nnscripts->display( ': Done' );
                    }
                    else
                    {
                        $this->nnscripts->display( 'OK' );
                    }
                    $this->nnscripts->display( PHP_EOL );
                }
            }
        }
        else
        {
            $this->nnscripts->display('No MyISAM tables found!');
            return false;
        }
    }
}

try
{
    // Init
    $scriptName    = 'Check and Repair database MyISAM tables';
    $scriptVersion = '0.2';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();
    
    // Load the database
    $db = new DB;
    if( !$db )
        throw new Exception("Error loading database library");

    // Check the database for errors
    $check = new checkDatabase( $nnscripts, $db );
    $check->checkAndRepair();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
