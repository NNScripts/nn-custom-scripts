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
 * Add your queries from line 72.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Added settings.ini
 *       Start using nnscript library
 * 
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Settings
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/category.php");

//----------------------------------------------------------------------
// The queries
//
// A query result must contain the following fields
//  - releaseID     (The release id)
//  - name          (The release name)
//  - adddate       (The release adddate)
//
//
// Messages:
// The message can contain a {releases} string which will be replaced
// with a number and the text "releases" for example:
// 1 release or 16 releases
// If you want a string between the number and the word releases use
// {releases|bogus} for the word bogus.
//
// Limit:
// The {limit|fieldname} tag automaticly fills the limit.
// The fieldname is used to set the limit to the correct field
// !!! if no fieldname is provided the tag is no replaces !!!
//
// Example query:
// "SELECT DISTINCT r.ID FROM releases r WHERE r.ID=1 {limit|r.adddate}
//
// If a limit is used the message will contain the hours automaticaly
//
$queries = array(

    // Remove all from other > misc with no files in rar and no nfo file
//    array(
//        'message' => 'Removing {releases} from "other > misc" with no files in rar and no nfo file',
//        'query'   => sprintf("SELECT DISTINCT r.ID AS releaseID, r.name, r.adddate
//                              FROM `releases` r
//                              LEFT JOIN `releasenfo` ri ON (ri.releaseID = r.ID)
//                              WHERE r.categoryID = %d
//                              AND r.rarinnerfilecount = 0
//                              AND ri.ID IS NULL
//                              {limit|r.adddate}", Category::CAT_MISC_OTHER)
//    ),

    // Remove all single file (no mkv) releases from Other > Misc
//    array(
//        'message' => 'Removing all single file (no mkv) releases from other > misc',
//        'query'   => sprintf("SELECT DISTINCT rf.releaseID, r.name, r.adddate
//                              FROM `releasefiles` AS rf
//                              INNER JOIN `releases` AS r ON (r.ID = rf.releaseID)
//                              WHERE rf.name NOT REGEXP '\.(mkv)$'
//                              AND r.categoryID = %d
//                              AND r.rarinnerfilecount = 1
//                              {limit|r.adddate}", Category::CAT_MISC_OTHER)
//    ),

);


/**
 * Remove the unwanted releases
 */
class remove_unwanted_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Remove custom unwanted releases';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.2';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display', 'limit', 'remove');
    
    /**
     * The releases object
     * @var Releases
     */
    private $releases;
    
    /**
     * The queries
     * @var array
     */
    protected $queries = array();
    
    
    
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
        $this->setCliOptions( $options, array('display', 'limit', 'remove', 'help') );
        
        // Show the header
        $this->displayHeader();

        // Show the settings
        $this->displaySettings();
        
        // Load the releases class
        $this->releases = new Releases();
    }
    
    
    /**
     * Add the select queries
     * 
     * @param array $queries
     * @param return void
     */
    public function setQueries( array $queries )
    {
        $this->queries = $queries;
    }
    

    /**
     * Run the cleanup
     * 
     * @return void
     */
    public function cleanup()
    {
        // Loop all the queries
        foreach( $this->queries AS $query )
        {
            // Check structure
            if( isset($query['message'], $query['query']) )
            {
                // Check if there is a {limit} variable in the query
                if( preg_match( '/\{limit\|([\.-_a-z0-9]+)\}/i', $query['query'], $matches ) )
                {
                    if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
                    {
                        // Build the replace string
                        $replaceString =  sprintf(' AND %s < "%s" - INTERVAL %s HOUR', $matches[1], $this->settings['now'], $this->settings['limit'] );
                        $query['query'] = str_replace( $matches[0], $replaceString, $query['query'] );
                    }
                }
                
                // Check if there are releases to remove
                $result = $this->db->query( $query['query'] );
                if( is_array($result) && 0 < count($result) )
                {
                    // Update the message
                    if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
                    {
                        $query['message'] .= sprintf(
                            ' (older then %s hour%s)',
                            $this->settings['limit'],
                            ( 1 !== $this->settings['limit'] ? 's' : '' )
                        );
                    }

                    // Replace the number of releases?
                    if( preg_match( '/{releases(\|([\w\s]+))?}/i', $query['message'], $matches ) )
                    {
                        // Build the message
                        $msg = sprintf(
                            "%d %s",
                            count( $result ),
                            ( 3 === count($matches) ? $matches[2] .' ' : '' ) .'release'. ( 1 < count( $result ) ? 's' : '' )
                        );
                        $query['message'] = str_replace( $matches[0], $msg, $query['message'] );
                    }
                    
                    // Display the message
                    $this->display( $query['message'] . PHP_EOL );
                    
                    // Loop all the releases to remove
                    foreach( $result AS $row )
                    {
                        $this->display( sprintf(
                            ' - %s release: %s (added: %s)'. PHP_EOL,
                            ( true === $this->settings['remove'] ? 'Removing' : 'Keeping' ),
                            $row['name'],
                            $row['adddate']
                        ) );
                        
                        if( true === $this->settings['remove'] )
                        {
                            $this->releases->delete( $row['releaseID'] );    
                        }
                    }
                    
                    // Spacer
                    $this->display( PHP_EOL );
                }
            }
        }
    }
}


// Main application
try
{
    // Init
    $sphinx = new Sphinx();
    
    // Load the remove_unwanted_releases class
    $rur = new remove_unwanted_releases();
    
    // Add the queries
    $rur->setQueries( ( ( isset($queries) && is_array($queries) ) ? $queries : array() ) );
    
    // Cleanup
    $rur->cleanup();
    
    // Update sphinx
    $sphinx->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
