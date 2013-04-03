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
 * 0.3 - Added group size statistics
 *
 * 0.2 - Start using nnscript library
 * 
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");


/**
 * Display group statistics
 */
class group_stats extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Group Statistics';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.3';
    
    /**
     * The group statistics
     * @var array
     */
    private $stats = array();
    
    /**
     * The total size of all groups together
     * @var int
     */
    private $totalSize = 0;

    /**
     * Max lengths
     * @var array
     */
    private $length = array(
        'group'       => 0,
        'lastUpdated' => 0,
        'releases'    => 0,
        'size'        => 0,
        'oldest'      => 0
    );
    
    
    
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

                // Add the number of releases and size
                $releases = $this->getNumberOfReleases( $id );
 
                // Releases
                $this->stats[ $group['name'] ]['releases'] = $releases['total'];
                if( strlen( $releases['total'] ) > $this->length['releases'] )
                    $this->length['releases'] = strlen( $releases['total'] );

                // Size
                $size = $this->formatBytes( $releases['size'] );
                $this->totalSize += (int)$releases['size'];
                $this->stats[ $group['name'] ]['size'] = $size;
                if( strlen( $size ) > $this->length['size'] )
                    $this->length['size'] = strlen( $size );
                
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
     * @return array
     */
    private function getNumberOfReleases( $groupId )
    {
        // Init
        $ret = array(
            'total' => 0,
            'size' => 0
        );
       
        // Get the release count
        $sql = sprintf("SELECT count(1) AS total, SUM( r.size ) AS size
                        FROM releases r
                        WHERE r.groupID = %s", $this->db->escapeString( $groupId ));
        $count = $this->db->query( $sql );
        if( is_array($count) && 1 === count($count) )
        {
            $row = $count[0];
            $ret = array(
                'total' => (int)$row['total'],
                'size'  => (int)$row['size']
            );
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
    public function show()
    {
        $line = sprintf( " %%-%ds | %%%ds | %%%ds | %%-%ds | %%%ds",
            ( 5 < $this->length['group'] ? $this->length['group'] : 5 ),
            ( 8 < $this->length['releases'] ? $this->length['releases'] : 8 ),
            ( 4 < $this->length['size'] ? $this->length['size'] : 4 ),
            ( 12 < $this->length['lastUpdated'] ? $this->length['lastUpdated'] : 12 ),
            ( 15 < $this->length['oldest'] ? $this->length['oldest'] : 15 )
        );

        // Build the spacer
        $patterns = array('/\%(\d+)/', '/\%-/');
        $replacements = array('%-${1}', "%'--");
        $spacer = '-'. preg_replace( $patterns, $replacements, trim($line));
        $spacer = str_replace(' | ', '-+-', $spacer);
       
        // Display the headers
        $this->display( sprintf( $line, 'Group', 'Releases', 'Size', 'Last updated', 'Oldest release' ) . PHP_EOL );
        $this->display( sprintf( $spacer, '-', '-', '-', '-', '-' ) .'-'. PHP_EOL );
        
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
            $this->display(
                sprintf(
                    $line,
                    $group,
                    trim( $stats['releases'] ),
                    trim( $stats['size'] ),
                    trim( $stats['lastUpdated'] ),
                    trim( $oldest )
                ) . PHP_EOL
            );
            $releases += (int)$stats['releases'];
        }

        // Spacer
        $this->display( sprintf( $spacer, '-', '-', '-', '-', '-' ) .'-'. PHP_EOL . PHP_EOL );

        // Total releases
        $totalSize = $this->formatBytes( $this->totalSize );

        $len = ( strlen( $releases ) > strlen( $totalSize ) ? strlen( $releases ) : strlen( $totalSize ) );
        $totalReleasesLine = sprintf( " Total Releases : %%%ds", $len );
        $totalSizeLine     = sprintf( " Total Size     : %%%ds", $len );

        $this->display( sprintf( $totalReleasesLine, $releases ) . PHP_EOL );
        $this->display( sprintf( $totalSizeLine, $totalSize ) . PHP_EOL . PHP_EOL );
    }


    /**
     * Format the output
     * 
     * @return string
     */
    private function formatBytes($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
        return sprintf( "%.{$decimals}f", $bytes / pow(1024, $factor) ) .' '. @$sz[$factor];
    }
}


// Main application
try
{
    // Display the available groups
    $stats = new group_stats();
    $stats->show();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
