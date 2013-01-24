<?php
/**
 * This script removes releases from non-active categories.
 * 
 * Releases are added to non active categories to allow raw search.
 * If you don't need/use raw search this script can remove the releases
 * from these categories
 * 
 * Warning: the script does not remove releases by default.
 * If you want to remove the releases, change the REMOVE_RELEASES
 * setting to true on line 38.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 41 to 43 to require the correct files.* 
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
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT ."/../../www/config.php");
require_once(WWW_DIR."/lib/releases.php");
require_once('nnscripts.php');


/**
 * Remove releases from non-active categories
 */
class categoryReleases
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
     * A list of all the non active categories
     * @var array
     */
    private $categories;



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
        
        // Get a list of the non-active categories
        $this->getCategories();
    }
    
    
    /**
     * Get a list of all the non-active categories
     * 
     * @return void
     */
    protected function getCategories()
    {
        // Get the categories
        $sql = "SELECT c.ID, c.title, p.title AS parent
                FROM category AS c
                INNER JOIN category AS p ON ( p.ID = c.parentID ) 
                WHERE c.status = 0
                AND c.parentID IS NOT NULL
                ORDER BY p.title ASC, c.title ASC";
        $this->categories = $this->db->query( $sql );
    }
    
    /**
     * Cleanup
     * 
     * @return void
     */
    public function cleanup()
    {       
        // Init
        $removed = false;
        
        // Loop all categories
        foreach( $this->categories AS $category )
        {
            // Get all the releases for this category
            $sql = sprintf("SELECT ID, name
                            FROM releases AS r
                            WHERE r.categoryID = %d", $category['ID']);
                
            // Apply the hour limit
            if( defined('LIMIT') && is_int( LIMIT ) && 0 < LIMIT )
            {
                $sql .= sprintf(' AND r.adddate < NOW() - INTERVAL %d HOUR', LIMIT);
            }
            
            // Execute the query
            $rels = $this->db->query( $sql );
            if( is_array( $rels ) && 0 < count( $rels ) )
            {
                // Get the total number of releases found
                $total = count( $rels );
                
                // Print the title
                $title = ( null !== $category['parent'] ? $category['parent'] .' - ' : '' ) . $category['title'];
                $this->nnscripts->display( sprintf("Processing releases for category: %s (%d releases)", $title, $total ) );

                // Get all the releases to remove
                $counter = 0;
                $ids = array();
                foreach( $rels AS $row )
                {
                    $removed = true;
                    if( true === DISPLAY )
                    {
                        // Remove a single release
                        $this->nnscripts->display( PHP_EOL . " Removing release: ". $row['name'] );
                        if( defined('REMOVE') && true === REMOVE )
                        {
                            $this->releases->delete( $row['ID'] );
                        }
                    }
                    else
                    {
                        // Remove in groups of 100 releases at a time
                        $ids[] = $row['ID'];
                        $counter++;
                        if( 100 == $counter )
                        {
                            // Remove
                            if( defined('REMOVE') && true === REMOVE )
                            {
                                $this->releases->delete( $ids );
                            }                            

                            // Reset
                            $ids = array();
                            $counter = 0;
                        }
                    }
                }

                // Remove the rest of the found releases
                if( 0 < count($ids) )
                {
                    if( defined('REMOVE') && true === REMOVE )
                    {
                        $this->releases->delete( $ids );
                    }
                }

                // Spacer
                $this->nnscripts->display( PHP_EOL . PHP_EOL );
            }
        }
        
        // No releases found, so no releases removed
        if( false === $removed )
        {
            $this->nnscripts->display("No releases found!". PHP_EOL);
        }
        
        // remove broken release parts
        $this->removeBrokenReleaseParts();
    }
    
    /**
     * Remove all the audio, comment, extrafull, files, nfo, subs and video records where the actual release is missing
     * 
     * @return void
     */
    protected function removeBrokenReleaseParts()
    {
        if( defined('REMOVE') && true === REMOVE )
        {
            $this->db->queryDirect("DELETE ra.* FROM releaseaudio ra LEFT JOIN releases r ON (r.ID = ra.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE rc.* FROM releasecomment rc LEFT JOIN releases r ON (r.ID = rc.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE ref.* FROM releaseextrafull ref LEFT JOIN releases r ON (r.ID = ref.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE rf.* FROM releasefiles rf LEFT JOIN releases r ON (r.ID = rf.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE rn.* FROM releasenfo rn LEFT JOIN releases r ON (r.ID = rn.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE rs.* FROM releasesubs rs LEFT JOIN releases r ON (r.ID = rs.releaseID) WHERE r.ID IS NULL");
            $this->db->queryDirect("DELETE rv.* FROM releasevideo rv LEFT JOIN releases r ON (r.ID = rv.releaseID) WHERE r.ID IS NULL");
        }
    }
}

try
{
    // Init
    $scriptName    = 'Remove releases from non-active categories';
    $scriptVersion = '0.1';
    
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
    
    // Load the categoryReleases class
    $cr = new categoryReleases( $nnscripts, $db, $releases );
    $cr->cleanup();
    
    // Update sphinx
    $sphinx->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
