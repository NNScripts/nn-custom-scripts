<?php
/**
 * Fix CORE releases named "Keymaker Windows-CORE" in the category "PC > 0day"
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Changed to PDO and prepare statements
 *
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");

// Sphinx library
require_once(WWW_DIR ."/lib/sphinx.php");


/**
 * Fix CORE releases
 */
class fix_core_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Fix CORE releases in "PC >0day"';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.2';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display', 'limit');

    /**
     * The releases object
     * @var Releases
     */
    private $releases;
    
    /**
     * Is there a releases fixed?
     * @var bool
     */
    private $fixed = false;

    /**
     * The query to update the database records
     * @var null|PDOStatement
     */
    private $updateQuery = null;



    /**
     * The constructor
     *
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();
        
        // Set the commandline options
        $options = array();
        $this->setCliOptions( $options, array('limit', 'display', 'help') );

        // Show the header
        $this->displayHeader();

        // Show the settings
        $this->displaySettings();
    }


    /**
     * Fix android releases
     * 
     * @return void
     */
    public function fix()
    {
        // Get the releases to check
        $releases = $this->getReleases();
        foreach( $releases AS $release )
        {
            $this->fixRelease( $release );
        }
        
        // No releases to fix
        if( false === $this->fixed )
        {
            $this->display( "No CORE releases to fix". PHP_EOL );
        }
    }
    
    
    /**
     * Get all the releases to fix
     * 
     * @return array
     */
    protected function getReleases()
    {
        // Create the query
        $sql = "
            SELECT
                r.ID,
                r.name,
                uncompress(rn.nfo) AS nfo
            FROM
                releases r
            INNER JOIN
                releasenfo rn ON (rn.releaseID = r.ID)
            WHERE
                r.searchname = :name
                AND r.categoryID = :category
        ";
                
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
            $sql .= 'AND r.adddate >= :startDate - INTERVAL :limit HOUR';

        // Prepare the query and bind params
        $selectQuery = $this->db->prepare( $sql );
        $selectQuery->bindValue( ':name', ' Keymaker Windows-CORE', PDO::PARAM_STR );
        $selectQuery->bindValue( ':category', 4010, PDO::PARAM_INT );

        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $selectQuery->bindValue( ':startDate', $this->settings['now'], PDO::PARAM_STR );
            $selectQuery->bindValue( ':limit', $this->settings['limit'], PDO::PARAM_INT );
        }                     

        // Execute
        $selectQuery->execute();
        $releases = $selectQuery->fetchAll( PDO::FETCH_ASSOC );

        // Return
        return( ( is_array( $releases ) && 0 < count( $releases ) ) ? $releases : array() );
    }
    
            
    /**
     * Try to fix a single release
     * 
     * @return void
     */
    protected function fixRelease( $release )
    {
        // Init
        $this->fixed = false;
        $regex = '/[\s]([a-z0-9\.\s\+_-])*?\*INCL\.KEYMAKER\*/i';

        // Loop all lines to find the one matching the softwarename
        foreach( explode("\n", $release['nfo']) AS $line )
        {
            if( preg_match( $regex, $line, $matches ) )
            {
                // Update
                $this->fixed = true;
                $title = trim( $matches[0] ) .' - CORE';
                $this->display( sprintf( 'Fixing release: %s'. PHP_EOL, $title ) );

                // Build the prepare sql statement
                if( null === $this->updateQuery ) {
                    $this->updateQuery = $this->db->prepare('
                        UPDATE
                            releases r
                        SET
                            r.name = :name,
                            r.searchname = :searchname
                        WHERE
                            r.id = :id
                    ');
                }

                // Bind the correct parameters 
                $this->updateQuery->bindValue( ':name', str_replace( '.', ' ', $title ), PDO::PARAM_STR );
                $this->updateQuery->bindValue( ':searchname', $title, PDO::PARAM_STR );
                $this->updateQuery->bindValue( ':id', $release['ID'], PDO::PARAM_INT );

                // Execute
                $this->updateQuery->execute();
            }
        }
    }
}

// Main application
try
{
    // Init
    $sphinx = new Sphinx();

    // Load the fix_core_releases class
    $fcr = new fix_core_releases();
    $fcr->fix();

    // Update sphinx
    $sphinx->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
