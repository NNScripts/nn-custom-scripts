<?php
/**
 * This releases with unwanted files (like flac or wav)
 * or releases without NFO and no files in the rar archive.
 * 
 * To make this script work you need to create your own queries!
 * each query must return the following 3 fields:
 * - releaseID      The ID of the release to remove
 * - name           The name of the release to remove
 * - adddate        The date the release was added to the database
 * 
 * !!! WARNING !!! !!! WARNING !!! !!! WARNING !!! !!! WARNING !!!
 * ---------------------------------------------------------------
 *  CREATING THE WRONG QUERIES CAN RESULT IN UNWANTED DATA LOSS.
 *  ALWAYS CREATE A BACKUP BEFORE TRYING OR TEST AGAINS ANOTHER
 *  DATABASE.
 * ---------------------------------------------------------------
 * !!! WARNING !!! !!! WARNING !!! !!! WARNING !!! !!! WARNING !!!
 * 
 * All queries need to be added to the "$queries" array.
 * The script will loop and execute all the queries.
 * Add your queries from line 59.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 52 to 56 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1  - Initial version
 */

//----------------------------------------------------------------------
// Settings

// Display settings
define('DISPLAY', true);

// Time limit on releases to remove in hours
// example: 24 for 1 day old releases (based on add date)
// false or 0 to disable
define('LIMIT', 24);

// Should the releases be removed?
define('REMOVE', false);
//----------------------------------------------------------------------

// Load the application
define('FL_ROOT', realpath(dirname(__FILE__)));
require_once(FL_ROOT ."/../../../www/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/category.php");
require_once('nnscripts.php');

//----------------------------------------------------------------------
// The queries
//
// The message can contain a {releases} string which will be replaced
// with a numbers and the text "releases" for example
// 1 releases or 16 releases
//
// If a limit is used the message will contain the hours automaticaly
//
$queries = array(
    // Remove all releases with "flac" or "wav" files, or dvd releases (vob, ifo)
    array(
        'message' => 'Removing {releases} with "flac, wav" files or dvd releases (vob, ifo)',
        'query'   => sprintf("SELECT DISTINCT rf.releaseID, r.name, r.adddate
                      FROM `releasefiles` AS rf
                      INNER JOIN `releases` AS r ON (r.ID = rf.releaseID)
                      WHERE rf.name REGEXP '\.(flac|wav|vob|ifo)$'
                      %s", ( (defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT) ? sprintf('AND r.adddate < NOW() - INTERVAL %d HOUR', LIMIT) : '' ) )
    ),
    
    // Remove all from other > misc with no files in rar and no nfo file
    array(
        'message' => 'Removing {releases} from "other > misc" with no files in rar and no nfo file',
        'query'   => sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.adddate
                      FROM `releases` r
                      LEFT JOIN `releasenfo` ri ON (ri.releaseID = r.ID)
                      WHERE r.categoryID = ". Category::CAT_MISC_OTHER ."
                      AND r.rarinnerfilecount = 0
                      AND ri.ID IS NULL
                      %s", ( (defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT) ? sprintf('AND r.adddate < NOW() - INTERVAL %d HOUR', LIMIT) : '' ) )
    ),
);
//----------------------------------------------------------------------

/**
 * Remove custom unwanted releases
 */
class removeUnwantedReleases
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
     * The releases object
     * @var Releases
     */
    private $releases;
    
    /**
     * The queries to run
     * @var array
     */
    private $queries;



    /**
     * The constructor
     * 
     */
    public function __construct( NNScripts $nnscripts, DB $db, Releases $releases, array $queries )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database variable
        $this->db = $db;
        
        // Set the release variable
        $this->releases = $releases;
        
        // Set the queries
        $this->queries = $queries;
    }


    /**
     * Remove the unwanted releases based on the custom queries
     * 
     * @return void
     */
    public function cleanup()
    {
        // Loop all the queries
        foreach( $this->queries AS $query )
        {
            // Check structure
            if( isset($query['message']) && isset($query['query']) )
            {
                // Check if there are releases to remove
                $result = $this->db->query( $query['query'] );
                if( is_array($result) && 0 < count($result) )
                {
                    // Update the message
                    if( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT )
                    {
                        $query['message'] .= ' (older than '. LIMIT .' hour';
                        if( 1 < LIMIT )
                        {
                            $query['message'] .= 's';
                        }
                        $query['message'] .= ')';
                    }

                    // Replace the number of releases?
                    if( false !== strpos( $query['message'], '{releases}' ) )
                    {
                        $line = sprintf("%d %s", count($result), 'release'. ( 1 < count($result) ? 's' : '' ) );
                        $query['message'] = str_replace('{releases}', $line, $query['message']);
                    }
                    
                    // Display the message
                    $this->nnscripts->display( $query['message'] . PHP_EOL );
                    
                    // Loop all the releases to remove
                    foreach( $result AS $row )
                    {
                        $this->nnscripts->display( sprintf( "  Removing release %s (added: %s)". PHP_EOL, $row['name'], $row['adddate'] ) );
                        if( defined('REMOVE') && true === REMOVE )
                        {
                            $this->releases->delete( $row['releaseID'] );
                        }
                    }
                    
                    // Spacer
                    $this->nnscripts->display( PHP_EOL );
                }
            }
        }
    }
}

try
{
    // Init
    $scriptName    = 'Remove custom unwanted releases';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();  
    
    // Load the sphinx libary
    $sphinx = new Sphinx();
    
    // Load the releases library
    $releases = new Releases;
    if( !$releases )
        throw new Exception("Error loading releases library");
    
    // Load the database libary
    $db = new DB; 
    if( !$db )
        throw new Exception("Error loading database library");

    // Remove the unwanted releases
    $stats = new removeUnwantedReleases( $nnscripts, $db, $releases, $queries );
    $stats->cleanup();
    
    // Update sphinx
    $sphinx->update();
    
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
