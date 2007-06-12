<?php
/**
 * File containing the ezcGraphLineChartOption class
 *
 * @package Graph
 * @version //autogentag//
 * @copyright Copyright (C) 2005-2007 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 */
/**
 * Class containing the basic options for line charts
 *
 * @property float $lineThickness
 *           Theickness of chart lines
 * @property mixed $fillLines
 *           Status wheather the space between line and axis should get filled.
 *            - FALSE to not fill the space at all.
 *            - (int) Opacity used to fill up the space with the lines color.
 * @property int $symbolSize
 *           Size of symbols in line chart.
 * @property ezcGraphFontOptions $highlightFont
 *           Font configuration for highlight tests
 * @property int $highlightSize
 *           Size of highlight blocks
 * @property bool $highlightLines
 *           If true, it adds lines to highlight the values position on the 
 *           axis.
 *
 * @version //autogentag//
 * @package Graph
 */
class ezcGraphLineChartOptions extends ezcGraphChartOptions
{
    /**
     * Constructor
     * 
     * @param array $options Default option array
     * @return void
     * @ignore
     */
    public function __construct( array $options = array() )
    {
        $this->properties['lineThickness'] = 2;
        $this->properties['fillLines'] = false;
        $this->properties['symbolSize'] = 8;
        $this->properties['highlightFont'] = new ezcGraphFontOptions();
        $this->properties['highlightFontCloned'] = false;
        $this->properties['highlightSize'] = 14;
        $this->properties['highlightLines'] = false;
    
        parent::__construct( $options );
    }

    /**
     * Set an option value
     * 
     * @param string $propertyName 
     * @param mixed $propertyValue 
     * @throws ezcBasePropertyNotFoundException
     *          If a property is not defined in this class
     * @return void
     */
    public function __set( $propertyName, $propertyValue )
    {
        switch ( $propertyName )
        {
            case 'lineThickness':
            case 'symbolSize':
            case 'highlightSize':
                if ( !is_numeric( $propertyValue ) ||
                     ( $propertyValue < 1 ) ) 
                {
                    throw new ezcBaseValueException( $propertyName, $propertyValue, 'int >= 1' );
                }

                $this->properties[$propertyName] = (int) $propertyValue;
                break;
            case 'fillLines':
                if ( ( $propertyValue !== false ) &&
                     !is_numeric( $propertyValue ) ||
                     ( $propertyValue < 0 ) ||
                     ( $propertyValue > 255 ) )
                {
                    throw new ezcBaseValueException( $propertyName, $propertyValue, 'false OR 0 <= int <= 255' );
                }

                $this->properties[$propertyName] = ( 
                    $propertyValue === false
                    ? false
                    : (int) $propertyValue );
                break;
            case 'highlightFont':
                if ( $propertyValue instanceof ezcGraphFontOptions )
                {
                    $this->properties['highlightFont'] = $propertyValue;
                }
                elseif ( is_string( $propertyValue ) )
                {
                    if ( !$this->properties['highlightFontCloned'] )
                    {
                        $this->properties['highlightFont'] = clone $this->font;
                        $this->properties['highlightFontCloned'] = true;
                    }

                    $this->properties['highlightFont']->path = $propertyValue;
                }
                else
                {
                    throw new ezcBaseValueException( $propertyName, $propertyValue, 'ezcGraphFontOptions' );
                }
                break;
                $this->properties['highlightSize'] = max( 1, (int) $propertyValue );
                break;
            case 'highlightLines':
                if ( !is_bool( $propertyValue ) )
                {
                    throw new ezcBaseValueException( $propertyName, $propertyValue, 'bool' );
                }

                $this->properties['highlightLines'] = $propertyValue;
                break;
            default:
                return parent::__set( $propertyName, $propertyValue );
        }
    }
    
    /**
     * __get 
     * 
     * @param mixed $propertyName 
     * @throws ezcBasePropertyNotFoundException
     *          If a the value for the property options is not an instance of
     * @return mixed
     * @ignore
     */
    public function __get( $propertyName )
    {
        switch ( $propertyName )
        {
            case 'highlightFont':
                // Clone font configuration when requested for this element
                if ( !$this->properties['highlightFontCloned'] )
                {
                    $this->properties['highlightFont'] = clone $this->properties['font'];
                    $this->properties['highlightFontCloned'] = true;
                }
                return $this->properties['highlightFont'];
            default:
                return parent::__get( $propertyName );
        }
    }
}

?>
