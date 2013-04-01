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
 * 0.3 - Added the option to clear active categories when a parent
 *       category is inactive
 * 
 * 0.2 - Start using nnscript library
 *
 * 0.1  - Initial version
 */
 
/**
 * @todo
 * <Tigggger>: hi, noticed something in the remove_category_releases.php
 * <Tigggger>: If the parent category is set to inactive, but the child categories are left alone (as normally no need to change them all to inactive)
 * <Tigggger>: the script doesn't remove releases from them, not a biggie changed all mine childs to inactive now, just thought you'd like to know
 * <Tigggger>: thanks again for the good work 
 * <cj>: ok, can you create an issue on github, then I will truy to look at it this weekend.
 * <cj>: I had mine all changed to "inactive", so I didn't notice, but it sounds fair that all child categories should be removed when a parent is inactive
 * <cj>: or at least it should be an option
 * <cj>: if you don't have a github account (needed to create the issue), it's not a big problem. I have created a todo for myself 
 */
//----------------------------------------------------------------------
// Load the application
define( 'FS_ROOT', realpath( dirname(__FILE__) ) );

// nnscripts includes
require_once(FS_ROOT ."/lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/releases.php");


/**
 * Remove releases from non-active categories
 */
class remove_category_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Remove releases from non-active categories';
    
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
     * A list of all the non active categories
     * @var array
     */
    private $categories;    
    
    
    
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
        if( !$this->releases )
             throw new Exception("Error loading releases library");
             
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
     * remove releases from non-active groups
     * 
     * @return void
     */
    public function removeReleases()
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
            if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
            {
                $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
            }
            
            // Execute the query
            $rels = $this->db->query( $sql );
            if( is_array( $rels ) && 0 < count( $rels ) )
            {
                // Get the total number of releases found
                $total = count( $rels );
                
                // Print the title
                $title = ( null !== $category['parent'] ? $category['parent'] .' - ' : '' ) . $category['title'];
                $this->display( sprintf("Processing releases for category: %s (%d releases)", $title, $total ) );

                // Get all the releases to remove
                $counter = 0;
                $ids = array();
                foreach( $rels AS $row )
                {
                    $removed = true;
                    if( true === $this->settings['display'] )
                    {
                        // Remove a single release
                        $this->display( PHP_EOL . sprintf(" - %s release: %s", 
                            ( true === $this->settings['remove'] ? 'Removing' : 'Keeping' ),
                            $row['name'] ) );
                        if( true === $this->settings['remove'] )
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
                            if( true === $this->settings['remove'] )
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
                    if( true === $this->settings['remove'] )
                    {
                        $this->releases->delete( $ids );
                    }
                }

                // Spacer
                $this->display( PHP_EOL . PHP_EOL );
            }
        }
        
        // No releases found, so no releases removed
        if( false === $removed )
        {
            $this->display("No releases found!". PHP_EOL);
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


// Main application
try
{
    // Load Sphinx
    $sphinx = new Sphinx();
    
    // Display the available groups
    $stats = new remove_category_releases();
    $stats->removeReleases();
    
    // Update sphinx
    $sphinx->update();
    
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}