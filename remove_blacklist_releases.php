<?php
/**
 * This script removes releases which do not pass the black/whitelist
 * filters. 
 * 
 * Releases could be renamed with scripts like "update_parsing". When
 * this happens, the new release name may not pass the black/whitelist
 * filters, but they are not removed. This script can remove all
 * releases which do not pass the filters.
 * 
 * Warning: the script does not remove releases by default.
 * If you want to remove the releases, change the REMOVE_RELEASES
 * setting to true on line 41.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 45 to 47 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2  - Make script php 5.3 compatible 
 *
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
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT ."/../../www/config.php");
require_once(WWW_DIR."/lib/releases.php");
require_once('nnscripts.php');


/**
 * Remove releases which do not match black or whitelists
 */
class blacklistReleases
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
     * A list of all the active groups
     * @var array
     */
    private $groups;
    
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
     * @param Releases $releases
     */
    public function __construct( NNScripts $nnscripts, DB $db, Releases $releases )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database variable
        $this->db = $db;
        
        // Set the release variable
        $this->releases = $releases;
        
        // Get a list of the active groups
        $this->getActiveGroups();
    }
    
    
    /**
     * Build a list of all the active groups with there regexes
     * 
     * @return void
     */
    protected function getActiveGroups()
    {
        // Get a list of all the groups
        $sql = "SELECT g.ID, g.name
                FROM groups AS g
                WHERE g.active = 1";
        $groups = $this->db->query( $sql );
        if( is_array( $groups ) && 0 < count( $groups ) )
        {
            // Loop all the active groups and gather the regexes.
            foreach( $groups AS $group )
            {
                // Get the regexes
                $group['regexes'] = $this->getGroupRegexes( $group );
                $this->groups[ $group['name'] ] = $group;
            }
        }
    }
    
    
    /**
     * Get all the black/whitelist regexes for a group
     * 
     * @param array $group
     * @return array
     */
    protected function getGroupRegexes( array $group )
    {
        // Init
        $ret = array();
        
        // Get all the active regexes for a group
        $sql = sprintf("SELECT b.groupname, b.regex, b.optype
                        FROM binaryblacklist AS b
                        WHERE b.msgcol = 1
                        AND b.status = 1
                        AND b.groupname IN ('alt.binaries.*', '%s')", $group['name']);
        $dbRegexes = $this->db->query( $sql );
        if( is_array( $dbRegexes ) && 0 < count( $dbRegexes ) )
        {
            // Build the regexes array
            foreach( $dbRegexes AS $regexRow )
            {
                $ret[ $this->listType[ $regexRow['optype'] ] ][] = $regexRow['regex'];
            }          
        }
        
        // Return
        return $ret;
    }
    
    
    /**
     * Start the cleanup
     * 
     * @return void
     */
    public function cleanup()
    {
        // For 5.3 compatibility, put th database object in a variable
        $db = $this->db;

        foreach( $this->groups AS $group )
        {
            // Init
            $run = false;
            
            // Get all the releases for the current group
            $sql = sprintf("SELECT r.ID, r.name
                            FROM releases AS r
                            WHERE r.groupID = %d AND (", $group['ID']);

            // Add the blacklisted regexes (if matches, the release should be removed (OR))
            if( array_key_exists( 'blacklist', $group['regexes'] ) )
            {
                $run = true;
                $sql .= sprintf( ' (%s)', implode(' OR ', array_map( function($e) use ( $db ) {
                        $e = $db->escapeString( str_replace('\\b', '', $e) );
                        return sprintf( "r.name REGEXP %s", $e );
                    }, $group['regexes']['blacklist'] ) )
                );
            }

            // Add the whitelist regexes (if not matches the release should be removed (AND))
            if( array_key_exists( 'whitelist', $group['regexes'] ) )
            {
                if( true === $run )
                {
                    $sql .= " OR ";
                }
                
                $run = true;
                $sql .= sprintf( ' (%s)',  implode(' AND ', array_map( function($e) use ( $db ) {
                        $e = $db->escapeString( str_replace('\\b', '', $e) );
                        return sprintf( "r.name NOT REGEXP %s", $e );
                    }, $group['regexes']['whitelist'] ) )
                );
            } 
            
            // Check for matches
            if( true === $run )
            {
                // Finish the sql
                $sql .= ')';

                // Limit the number of releases by time
                if( DEFINED('LIMIT') && is_int( LIMIT ) && 0 < LIMIT )
                {
                    $sql .= sprintf(' AND r.adddate < NOW() - INTERVAL %d HOUR', LIMIT);
                }
                
                // Cleanup
                $matches = $this->db->query( $sql );
                if( is_array($matches) && 0 < count($matches) )
                {
                    $total = count( $matches );
                    $this->nnscripts->display( sprintf("%d %s found for group %s:". PHP_EOL, $total, (1 === $total ? 'match' : 'matches'), $group['name']) );

                    foreach( $matches AS $match )
                    {
                        $this->nnscripts->display( sprintf("  Removing release %s". PHP_EOL, $match['name']) );

                        // Remove release
                        if( defined('REMOVE') && true === REMOVE )
                        {
                            $this->releases->delete( $match['ID'] );
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
    $scriptName    = 'Remove black or whitelisted releases';
    $scriptVersion = '0.2';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();
    
    // Load the application part
    $releases = new Releases;
    if( !$releases )
        throw new Exception("Error loading releases library");
    
    $db = new DB;
    if( !$db )
        throw new Exception("Error loading database library");
        
    $sphinx = new Sphinx();
    
    // Load the blacklistReleases class
    $blr = new blacklistReleases( $nnscripts, $db, $releases );
    $blr->cleanup();
    
    // Update sphinx
    $sphinx->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
