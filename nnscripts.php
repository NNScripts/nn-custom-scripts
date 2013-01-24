<?php
/**
 * This class contains the functions used by the other NNScripts
 * 
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1  - Initial version
 */
 
class NNScripts
{
    /**
     * The script name
     * @var string
     */
    private $scriptName;
    
    /**
     * The script version
     * @var string
     */
    private $version;
    
    /**
     * Should output be displayed?
     * @var bool
     */
    private $display = false;
    
    /**
     * The query limit in hours
     * @var bool|int
     */
    private $limit = null;
    
    /**
     * Shoud data be removed?
     * @var null|bool
     */
    private $remove = null;



    /**
     * NNScripts constructor
     * 
     * @param string $scriptname
     * @param string $version
     */
    public function __construct( $scriptName, $version )
    {
        // Check correct php version
        $this->checkPHPVersion();

        // Set the script name and version
        $this->scriptName = $scriptName;
        $this->version = $version;
        
        // Set the display setting
        $this->display = ( !defined('DISPLAY') || true === DISPLAY ? true : false );
        
        // Query Limit
        if( defined('LIMIT') )
        {
            $this->limit = ( ( is_int(LIMIT) && 0 < LIMIT ) ? LIMIT : false );
        }
        
        // Should data be removed?
        if( defined('REMOVE') )
        {
            $this->remove = ( ( true === REMOVE ) ? true : false );
        }
    }
    
    
    /**
     * Destructor
     * 
     */
    public function __destruct()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->display )
        {
            echo '</pre>';
        }
    }


    /**
     * Check php version to be 5.4 or greater
     * 
     * @return void
     */
    protected function checkPHPVersion()
    {
        // Init
        $current  = phpversion();
        $required = '5.4.0';
        
        if( 0 > strnatcmp( $current, $required ) )
        {
            throw new Exception( sprintf( 'NNScripts'. PHP_EOL .'Error: PHP version %s is required but version %s is found.', $required, $current ) );
        }
    }

       
    /**
     * Display a message
     * 
     * The function will also try to detect a browser call and convert
     * new-lines to <br />'s
     * 
     * @param string $message
     * @return void
     */
    public function display( $message )
    {
        if( true === $this->display )
        {
            // Convert for webbrowser?
            if( isset( $_SERVER["HTTP_HOST"] ) )
            {
                $message = nl2br( $message );
            }
            
            // Display
            echo $message;
        }
    }       


    /**
     * Display a page start
     * Only usefull when viewing with a webbrowser
     * 
     * @return void
     */
    public function displayPageStart()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->display )
        {
            echo '<pre style="font-size: 11px; line-height: 7px;">';
        }
    }       


    /**
     * Display the header line and settings info
     * 
     * @return void
     */
    public function displayHeader()
    {
        // Display the page start in case of an html page
        $this->displayPageStart();
        
        // Display the script header line
        $this->display( sprintf( "%s - version %s". PHP_EOL, $this->scriptName, $this->version ) );
        
        // Display settings?
        if( null !== $this->limit || null !== $this->remove )
        {
            $this->display( PHP_EOL .'Settings:' );
            
            // Display the "limit" settings
            if( null !== $this->limit )
            {
                $line = 'no limit';
                if( is_int($this->limit) )
                {
                    $line = sprintf( "%d hour%s", $this->limit, ( 1 < $this->limit ? 's' : '' ) );
                }
                $this->display( PHP_EOL . sprintf( "- limit : %s", $line ) );
            }
        
            // Display the "remove" setting
            if( null !== $this->remove )
            {
                $line = "Data will be removed.";
                if( true !== $this->remove )
                {
                    $line = 'No data is removed from the database!'. PHP_EOL .'          Change the "REMOVE" setting if you want to remove releases or parts';
                }
                $this->display( PHP_EOL . sprintf( "- remove: %s", $line ) );
            }
        
            // Spacer
            $this->display( PHP_EOL );
        }
        
        // End spacer
        $this->display( PHP_EOL );
    }
}
