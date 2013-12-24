<?php
/**
 * Fix mallformed android releases
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.3 - Changed to PDO and prepare statements
 *
 * 0.2 - Changed query to catch more android releases
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
 * Remove releases which do not match black or whitelists
 */
class fix_android_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Fix malformed android releases';
    
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
        // Get a list of the active groups with theire regexes
        $releases = $this->getReleases();
        foreach( $releases AS $release )
        {
            $this->fixRelease( $release );
        }
        
        // No releases to fix
        if( false === $this->fixed )
        {
            $this->display( "No android releases to fix". PHP_EOL );
        }
    }
    
    
    /**
     * Get all the releases to fix
     * 
     * @return array
     */
    protected function getReleases()
    {
        $sql = "
            SELECT
                r.ID, r.name,
                REPLACE(rf.name, '.apk', '') AS filename
            FROM
                releases r
            LEFT JOIN
                releasefiles rf ON (rf.releaseID = r.ID)
            WHERE
            rf.name REGEXP  '\.apk$'
            AND r.name REGEXP '^[v]?[0-9]+([\\s\\.][0-9]+)+(\\-(Game|Pro))?[\\-\\.]AnDrOiD'
        ";

        // Add limits
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
            $sql .= 'AND r.adddate >= :startDate - INTERVAL :limit HOUR';

        // Prepare the query
        $selectQuery = $this->db->prepare( $sql );

        // Bind the parameters
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $selectQuery->bindValue( ':startDate', $this->settings['now'], PDO::PARAM_STR );
            $selectQuery->bindValue( ':limit', $this->settings['limit'], PDO::PARAM_INT );
        }

        // Execute
        $selectQuery->execute();
        $releases = $selectQuery->fetchAll(PDO::FETCH_ASSOC);

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
        
        // Build the check regex
        $regex = sprintf( '/%s$/i', preg_quote( $release['name'] ) );
        if( preg_match( $regex, $release['filename'] ) )
        {
            // Get the real filename
            $filename = preg_split( sprintf('/([%s])/', preg_quote('\\') ), $release['filename'] );
            $filename = end( $filename );

            // Update
            $this->fixed = true;
            $this->display( sprintf( 'Fixing release: %s'. PHP_EOL, $filename ) );

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
            $name = str_replace( '.', ' ', $filename );
            $this->updateQuery->bindValue( ':name', $name, PDO::PARAM_STR );
            $this->updateQuery->bindValue( ':searchname', $filename, PDO::PARAM_STR );
            $this->updateQuery->bindValue( ':id', $release['ID'], PDO::PARAM_INT );

            // Execute
            $this->updateQuery->execute();
        }
    }
}

// Main application
try
{
    // Init
    $sphinx = new Sphinx();

    // Load the blacklistReleases class
    $blr = new fix_android_releases();
    $blr->fix();

    // Update sphinx
    $sphinx->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
