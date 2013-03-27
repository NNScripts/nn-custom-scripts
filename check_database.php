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
 * 0.3 - Added settings.ini
 *       Start using nnscript library
 *
 * 0.2 - Added MyISAM check on tables
 *
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");


/**
 * Check and repair database tables
 */
class check_database extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Check and Repair database MyISAM tables';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.3';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display');
    
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
     * Disable the limit setting
     * @var bool
     */
    protected $nolimit = true;
    
    
    
    /**
     * The constructor
     *
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();

        // Show the header
        $this->displayHeader();

        // Set the commandline options
        $options = array();
        $this->setCliOptions( $options, array('display', 'help') );
        
        // Show the settings
        $this->displaySettings();
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
        // Get all the database tables
        $this->getTables();
        
        // Default error
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
                $this->display( sprintf($template, $table) );

                $sql = sprintf("CHECK TABLE `%s` QUICK", $table);
                $result = $this->db->query( $sql );

                if( is_array($result) && 0 < count($result) )
                {
                    $row = $result[0];
                    if( !in_array( $row['Msg_text'], $allowed ) )
                    {
                        $this->display( $row['Msg_text'] .' : Starting repair ' );
                        $sql = sprintf("REPAIR TABLE `%s`", $table);
                        $result = $this->db->query( $sql );
                        $this->display( ': Done' );
                    }
                    else
                    {
                        $this->display( 'OK' );
                    }
                    $this->display( PHP_EOL );
                }
            }
        }
        else
        {
            $this->display('No MyISAM tables found!');
        }
        
        // Spacer
        $this->display( PHP_EOL );
    }
}

// Main application
try
{
    // Load the blacklistReleases class
    $blr = new check_database();
    $blr->checkAndRepair();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
