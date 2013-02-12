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
 * If you want to remove the releases, change the REMOVE
 * setting to true on line 51.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 58 to 60 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.3 - Moved checking of regexes to php.
 *       Mysql regexes are not compatible with php's preg regexes.
 *
 *       Some regexes cannot be converted, which means that checking
 *       the regexes now get's slower. To prevent overloading your
 *       system (by retrieving all releases) the LIMIT setting now
 *       only checks releases added within the last x hours.
 *       For example, when entering 24 the script will only check
 *       releases added within the last 24 hours.
 *
 * 0.2 - Make script php 5.3 compatible
 *
 * 0.1 - Initial version
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

// Show debug messages
define('DEBUG', false);
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
     * The current date + time
     * @var string
     */
    private $now;



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

        // Get a list of the active groups with theire regexes
        $this->buildGroupList();

        // Add the "now" date
        $this->now = date('Y-m-d H:i:s');
    }

    /**
     * Build a list of all the active groups with there regexes
     * 
     * @return void
     */
    protected function buildGroupList()
    {
        // Get a list of all the groups
        $sql = "SELECT g.ID, g.name
                FROM groups AS g
                WHERE g.active = 1
                ORDER BY g.name ASC";
        $groups = $this->db->query( $sql );
        if( is_array( $groups ) && 0 < count( $groups ) )
        {
            // Loop all the active groups and gather the regexes.
            foreach( $groups AS $group )
            {
                // Get the regexes
                $group['regexes'] = $this->getGroupRegexes( $group );
                $this->groups[ $group['ID'] ] = $group;
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
            // Count the number of releases (not flooding memory by retreiving all releases at once)
            $total = $this->getTotalReleasesForGroup( $group['ID'] );

            // Init
            $this->nnscripts->display(
                sprintf(
                    'Checking group %s (%d release%s):'. PHP_EOL,
                    $group['name'],
                    $total,
                    ( 1 === $total ? '' : 's')
                )
            );

            // Retrieve in groups of 100 releases
            $lastId = null;
            while( $total > 0 )
            {
                // Get the releases
                $releases = $this->getReleases( $group['ID'], $lastId, 100 );
                foreach( $releases AS $release )
                {
                    // Process the releases
                    if( false === $this->checkRelease( $group['ID'], $release['name'] ) )
                    {
                        $this->nnscripts->display( sprintf(' - Removing release: %s (added: %s)'. PHP_EOL, $release['name'], $release['adddate'] ) );
                        if( defined('REMOVE') && true === REMOVE )
                        {
                            $this->releases->delete( $release['ID'] );
                        }
                    }

                    // Update the lastId
                    $lastId = $release['ID'];
                }

                // Done, lower the total count
                $total -= 100;
            }
        }
    }


    /**
     * Get the total number of releases for a group.
     *
     * @param int $groupId
     * @return int
     */
    protected function getTotalReleasesForGroup( $groupId )
    {
        $ret = 0;

        // Build the sql
        $sql = sprintf( "SELECT count(1) AS total
                         FROM `releases` r
                         WHERE r.groupID = %d", $groupId );
        if ( defined('LIMIT') && is_int( LIMIT ) && 0 < LIMIT )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %d HOUR', $this->now, LIMIT );
        }

        $result = $this->db->queryOneRow( $sql );
        if( is_array($result) && 1 === count($result) )
        {
            $ret = (int)$result['total'];
        }

        // Return
        return $ret;
    }


    /**
     * Get releases for a group without flooding the memory
     *
     * @param int $groupId
     * @param null|int $lastId
     * @param int $limit
     * @return array
     */
    protected function getReleases( $groupId, $lastId=null, $limit=100 )
    {
        // Init
        $ret = array();

        // Built the query
        $sql = sprintf( "SELECT r.ID, r.name, r.adddate
                         FROM `releases` r
                         WHERE r.groupID = %d", $groupId );

        // Add the lastId
        if( null !== $lastId )
        {
            $sql .= sprintf( " AND r.ID > %d", $lastId );
        }

        // Date limit
        if ( defined('LIMIT') && is_int( LIMIT ) && 0 < LIMIT )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %d HOUR', $this->now, LIMIT );
        }

        // Add the sorting of the records and the record limit
        $sql .= sprintf(" ORDER BY r.ID ASC
                         LIMIT %d", $limit);

        // Run the query and return the results
        $result = $this->db->query( $sql );
        if( is_array($result) && 0 < count($result) )
        {
            $ret = $result;
        }

        // Return
        return $ret;
    }


    /**
     * Check a release for black/white lists matches
     *
     * @param int $groupId
     * @param string $releaseName
     * @return bool
     */
    protected function checkRelease( $groupId, $releaseName )
    {
        // First check for whitelists
        if( array_key_exists('whitelist', $this->groups[ $groupId ]['regexes'] ) )
        {
            foreach( $this->groups[ $groupId ]['regexes']['whitelist'] AS $regex )
            {
                if( !preg_match('/'. $regex .'/i', $releaseName) )
                {
                    // Debug message
                    if( defined('DEBUG') && true === DEBUG )
                    {
                        $this->nnscripts->display( sprintf( 'DEBUG: Release [%s] does not match whitelist regex [%s]'. PHP_EOL, $releaseName, $regex ) );
                    }

                    // Return (no further checking needed)
                    return false;
                }
            }
        }

        // Then check blacklists
        if( array_key_exists('blacklist', $this->groups[ $groupId ]['regexes'] ) )
        {
            foreach( $this->groups[ $groupId ]['regexes']['blacklist'] AS $regex )
            {
                if( preg_match('/'. $regex .'/i', $releaseName, $matches) )
                {
                    // Debug message
                    if( defined('DEBUG') && true === DEBUG )
                    {
                        $this->nnscripts->display( sprintf( 'DEBUG: Release [%s] does matches blacklist regex [%s]'. PHP_EOL, $releaseName, $regex ) );
                        $this->nnscripts->display( sprintf( 'DEBUG: Matches: %s'. PHP_EOL, implode(' | ', $matches ) ) );
                    }
                    // Return (no further checking needed)
                    return false;
                }
            }
        }

        // All ok, release does not need to be removed
        return true;
    }
}


try
{
    // Init
    $scriptName    = 'Remove black or whitelisted releases';
    $scriptVersion = '0.3';
    
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
