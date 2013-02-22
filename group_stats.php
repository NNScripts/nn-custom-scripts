<?php
/**
 * Display group statistics
 *
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. If used from another location, change
 * lines 26 to 28 to require the correct files.
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
//---------------------------------------------------------------------- 

// Load the application
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT ."/../../www/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once('nnscripts.php');

/**
 * Display group statistics
 */
class groupStats
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts; 
    
    /**
     * The database connection
     * @var DB
     */
    private $db;
    
    /**
     * The group statistics
     * @var array
     */
    private $stats = array();
    
    /**
     * Max lengths
     * @var array
     */
    private $length = array(
        'group'       => 0,
        'lastUpdated' => 0,
        'releases'    => 0,
        'oldest'      => 0
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
        
        // Gather the stats
        $this->gatherStats();
    }
    
    
    /**
     * Gather the newznab group statistics
     * 
     * @return void
     */
    private function gatherStats()
    {
        // First get all the active groups
        $groups = $this->getActiveGroups();
        if( is_array( $groups ) && 0 < count( $groups ) )
        {
            foreach( $groups AS $id => $group )
            {
                // Update group name
                $group['name'] = str_replace('alt.binaries', 'a.b', $group['name']);
                
                // Update group max length
                $this->length['group'] = ( strlen( $group['name'] ) > $this->length['group'] ? strlen( $group['name'] ) : $this->length['group'] );
                
                // Add the group to the stats
                $this->stats[ $group['name'] ] = array(
                    'lastUpdated' => $group['lastUpdated']
                );
                if( strlen( $group['lastUpdated'] ) > $this->length['lastUpdated'] )
                    $this->length['lastUpdated'] = strlen( $group['lastUpdated'] );

                // Add the number of releases
                $releases = $this->getNumberOfReleases( $id );
                $this->stats[ $group['name'] ]['releases'] = $releases;
                if( strlen( $releases ) > $this->length['releases'] )
                    $this->length['releases'] = strlen( $releases );
                
                // Add the date of the oldest release
                $oldest = $this->getOldestRelease( $id );
                $this->stats[ $group['name'] ]['oldest'] = $oldest;
                if( strlen( $oldest ) > $this->length['oldest'] )
                    $this->length['oldest'] = strlen( $oldest );
            }
        }
    }
    
    
    /**
     * Get all the active groups
     * 
     * @return array
     */
    private function getActiveGroups()
    {
        // init
        $ret = array();
        
        // Get all the active groups
        $sql = "SELECT g.ID, g.name, g.last_updated
                FROM groups AS g
                WHERE g.active = 1
                ORDER BY g.name ASC";
        $groups = $this->db->query( $sql );
        if( is_array($groups) && 0 < count($groups) )
        {
            foreach( $groups AS $row )
            {
                $ret[ $row['ID'] ] = array(
                    'name'        => $row['name'],
                    'lastUpdated' => $row['last_updated']
                );
            }
        }
        
        // Return
        return $ret;
    }


    /**
     * Get the total number of releases
     *
     * @param int $groupId
     * @return int
     */
    private function getNumberOfReleases( $groupId )
    {
        // Init
        $ret = 0;
       
        // Get the release count
        $sql = sprintf("SELECT count(1) AS total
                        FROM releases
                        WHERE groupID = %s", $this->db->escapeString( $groupId ));
        $count = $this->db->query( $sql );
        if( is_array($count) && 1 === count($count) )
        {
            $row = $count[0];
            $ret = (int)$row['total'];
        }
        
        // Return
        return $ret;
    }


    /**
     * Get the total number of releases
     *
     * @param int $groupId
     * @return string
     */
    private function getOldestRelease( $groupId )
    {
        // Init
        $ret = '0';
       
        // Get the release count
        $sql = sprintf("SELECT r.postdate
                        FROM releases AS r
                        WHERE r.groupID = %s
                        ORDER BY postdate ASC
                        LIMIT 1", $this->db->escapeString( $groupId ));
        $oldest = $this->db->query( $sql );
        if( is_array($oldest) && 0 < count($oldest) )
        {
            $row = $oldest[0];
            $postDate = new DateTime( $row['postdate'] );
            $now = new DateTime();
            $interval = $postDate->diff( $now );
            $ret = $interval->format( '%a days %h hours' );
        }
        
        // Return
        return $ret;
    }    


    /**
     * Display the NewzNab Stats
     * 
     * @return void
     */
    public function display()
    {
        $line = sprintf( " %%-%ds | %%%ds | %%-%ds | %%%ds",
            ( 5 < $this->length['group'] ? $this->length['group'] : 5 ),
            ( 8 < $this->length['releases'] ? $this->length['releases'] : 8 ),
            ( 12 < $this->length['lastUpdated'] ? $this->length['lastUpdated'] : 12 ),
            ( 15 < $this->length['oldest'] ? $this->length['oldest'] : 15 )
        );

        // Build the spacer
        $patterns = array('/\%(\d+)/', '/\%-/');
        $replacements = array('%-${1}', "%'--");
        $spacer = '-'. preg_replace( $patterns, $replacements, trim($line));
        $spacer = str_replace(' | ', '-+-', $spacer);
       
        // Display the headers
        $this->nnscripts->display( sprintf( $line, 'Group', 'Releases', 'Last updated', 'Oldest release' ) . PHP_EOL );
        $this->nnscripts->display( sprintf( $spacer, '-', '-', '-', '-' ) .'-'. PHP_EOL );
        
        // loop the stats
        $releases = 0;
        foreach( $this->stats AS $group => $stats )
        {
            $oldest = '';
            if( "0" !== $stats['oldest'] )
            {
                $oldest = explode(' ', $stats['oldest']);
                $oldest = sprintf("%s %s %2s %s", $oldest[0], $oldest[1], $oldest[2], $oldest[3]);
            }
            $this->nnscripts->display( sprintf( $line, $group, trim($stats['releases']), trim($stats['lastUpdated']), trim($oldest) ) . PHP_EOL );
            $releases += (int)$stats['releases'];
        }

        // Spacer
        $this->nnscripts->display( sprintf( $spacer, '-', '-', '-', '-' ) .'-'. PHP_EOL );

        // Total releases
        $right = -3 + (( 5 < $this->length['group'] ? $this->length['group'] : 5 ) + ( 8 < $this->length['releases'] ? $this->length['releases'] : 8 ));
        $totalLine = sprintf( " Total %%%ds", $right );
        $this->nnscripts->display( sprintf( $totalLine, $releases ) . PHP_EOL . PHP_EOL );
    }
}

try
{
    // Init
    $scriptName    = 'Group Statistics';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();    

    // Load the database
    $db = new DB; 
    if( !$db )
        throw new Exception("Error loading database library");

    // Display the stats
    $stats = new groupStats( $nnscripts, $db );
    $stats->display();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
