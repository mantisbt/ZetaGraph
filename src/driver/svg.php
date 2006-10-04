<?php
/**
 * File containing the ezcGraphSVGDriver class
 *
 * @package Graph
 * @version //autogentag//
 * @copyright Copyright (C) 2005, 2006 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 */
/**
 * Extension of the basic Driver package to utilize the SVGlib.
 *
 * @package Graph
 */

class ezcGraphSvgDriver extends ezcGraphDriver
{

    /**
     * DOM tree of the svg document
     * 
     * @var DOMDocument
     */
    protected $dom;

    /**
     * DOMElement containing all svg style definitions
     * 
     * @var DOMElement
     */
    protected $defs;

    /**
     * DOMElement containing all svg objects
     * 
     * @var DOMElement
     */
    protected $elements;

    /**
     * List of strings to draw
     * array ( array(
     *          'text' => array( 'strings' ),
     *          'options' => ezcGraphFontOptions,
     *      )
     * 
     * @var array
     */
    protected $strings = array();

    /**
     * List of already created gradients
     * 
     * @var array
     */
    protected $drawnGradients = array();

    /**
     * Numeric unique element id
     * 
     * @var int
     */
    protected $elementID = 0;

    /**
     * Constructor
     * 
     * @param array $options Default option array
     * @return void
     * @ignore
     */
    public function __construct( array $options = array() )
    {
        $this->options = new ezcGraphSvgDriverOptions( $options );
    }

    /**
     * Creates the DOM object to insert SVG nodes in.
     *
     * If the DOM document does not exists it will be created or loaded 
     * according to the settings.
     * 
     * @return void
     */
    protected function createDocument()
    {
        if ( $this->dom === null )
        {
            if ( $this->options->templateDocument !== false )
            {
                $this->dom = new DOMDocument();
// @TODO: Add                $this->dom->format
                $this->dom->load( $this->options->templateDocument );

                $this->defs = $this->dom->getElementsByTagName( 'defs' )->item( 0 );
                $svg = $this->dom->getElementsByTagName( 'svg' )->item( 0 );
            }
            else
            {
                $this->dom = new DOMDocument();
                $svg = $this->dom->createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
                $this->dom->appendChild( $svg );

                $svg->setAttribute( 'width', $this->options->width );
                $svg->setAttribute( 'height', $this->options->height );
                $svg->setAttribute( 'version', '1.0' );
                $svg->setAttribute( 'id', $this->options->idPrefix );

                $this->defs = $this->dom->createElement( 'defs' );
                $this->defs = $svg->appendChild( $this->defs );
            }

            if ( $this->options->insertIntoGroup !== false )
            {
                // getElementById only works for Documents validated against a certain 
                // schema, so that the use of XPath should be faster in most cases.
                $xpath = new DomXPath( $this->dom );
                $this->elements = $xpath->query( '//*[@id = \'' . $this->options->insertIntoGroup . '\']' )->item( 0 );
                if ( !$this->elements )
                {
                    throw new ezcGraphSvgDriverInvalidIdException( $this->options->insertIntoGroup );
                }
            }
            else
            {
                $this->elements = $this->dom->createElement( 'g' );
                $this->elements->setAttribute( 'id', $this->options->idPrefix . 'Chart' );
                $this->elements->setAttribute( 'color-rendering', $this->options->colorRendering );
                $this->elements->setAttribute( 'shape-rendering', $this->options->shapeRendering );
                $this->elements->setAttribute( 'text-rendering', $this->options->textRendering );
                $this->elements = $svg->appendChild( $this->elements );
            }
        }
    }

    /**
     * Return gradient URL
     *
     * Creates the definitions needed for a gradient, if a proper gradient does
     * not yet exists. In each case a URL referencing the correct gradient will
     * be returned.
     * 
     * @param ezcGraphColor $color Gradient
     * @return string Gradient URL
     */
    protected function getGradientUrl( ezcGraphColor $color )
    {
        switch ( true )
        {
            case ( $color instanceof ezcGraphLinearGradient ):
                if ( !in_array( $color->__toString(), $this->drawnGradients, true ) )
                {
                    $gradient = $this->dom->createElement( 'linearGradient' );
                    $gradient->setAttribute( 'id', 'Definition_' . $color->__toString() );
                    $this->defs->appendChild( $gradient );

                    // Start of linear gradient
                    $stop = $this->dom->createElement( 'stop' );
                    $stop->setAttribute( 'offset', 0 );
                    $stop->setAttribute( 'style', sprintf( 'stop-color: #%02x%02x%02x; stop-opacity: %.2f;',
                        $color->startColor->red,
                        $color->startColor->green,
                        $color->startColor->blue,
                        1 - ( $color->startColor->alpha / 255 )
                        )
                    );
                    $gradient->appendChild( $stop );

                    // End of linear gradient
                    $stop = $this->dom->createElement( 'stop' );
                    $stop->setAttribute( 'offset', 1 );
                    $stop->setAttribute( 'style', sprintf( 'stop-color: #%02x%02x%02x; stop-opacity: %.2f;',
                        $color->endColor->red,
                        $color->endColor->green,
                        $color->endColor->blue,
                        1 - ( $color->endColor->alpha / 255 )
                        )
                    );
                    $gradient->appendChild( $stop );

                    $gradient = $this->dom->createElement( 'linearGradient' );
                    $gradient->setAttribute( 'id', $color->__toString() );
                    $gradient->setAttribute( 'x1', $color->startPoint->x );
                    $gradient->setAttribute( 'y1', $color->startPoint->y );
                    $gradient->setAttribute( 'x2', $color->endPoint->x );
                    $gradient->setAttribute( 'y2', $color->endPoint->y );
                    $gradient->setAttribute( 'gradientUnits', 'userSpaceOnUse' );
                    $gradient->setAttributeNS( 
                        'http://www.w3.org/1999/xlink', 
                        'xlink:href',
                        '#Definition_' . $color->__toString()
                    );
                    $this->defs->appendChild( $gradient );

                    $this->drawnGradients[] = $color->__toString();
                }

                return sprintf( 'url(#%s)',
                    $color->__toString()
                );
            case ( $color instanceof ezcGraphRadialGradient ):
                if ( !in_array( $color->__toString(), $this->drawnGradients, true ) )
                {
                    $gradient = $this->dom->createElement( 'linearGradient' );
                    $gradient->setAttribute( 'id', 'Definition_' . $color->__toString() );
                    $this->defs->appendChild( $gradient );

                    // Start of linear gradient
                    $stop = $this->dom->createElement( 'stop' );
                    $stop->setAttribute( 'offset', 0 );
                    $stop->setAttribute( 'style', sprintf( 'stop-color: #%02x%02x%02x; stop-opacity: %.2f;',
                        $color->startColor->red,
                        $color->startColor->green,
                        $color->startColor->blue,
                        1 - ( $color->startColor->alpha / 255 )
                        )
                    );
                    $gradient->appendChild( $stop );

                    // End of linear gradient
                    $stop = $this->dom->createElement( 'stop' );
                    $stop->setAttribute( 'offset', 1 );
                    $stop->setAttribute( 'style', sprintf( 'stop-color: #%02x%02x%02x; stop-opacity: %.2f;',
                        $color->endColor->red,
                        $color->endColor->green,
                        $color->endColor->blue,
                        1 - ( $color->endColor->alpha / 255 )
                        )
                    );
                    $gradient->appendChild( $stop );

                    $gradient = $this->dom->createElement( 'radialGradient' );
                    $gradient->setAttribute( 'id', $color->__toString() );
                    $gradient->setAttribute( 'cx', $color->center->x );
                    $gradient->setAttribute( 'cy', $color->center->y );
                    $gradient->setAttribute( 'fx', $color->center->x );
                    $gradient->setAttribute( 'fy', $color->center->y );
                    $gradient->setAttribute( 'r', max( $color->height, $color->width ) );
                    $gradient->setAttribute( 'gradientUnits', 'userSpaceOnUse' );
                    $gradient->setAttributeNS( 
                        'http://www.w3.org/1999/xlink', 
                        'xlink:href',
                        '#Definition_' . $color->__toString()
                    );
                    $this->defs->appendChild( $gradient );

                    $this->drawnGradients[] = $color->__toString();
                }

                return sprintf( 'url(#%s)',
                    $color->__toString()
                );
            default:
                return false;
        }

    }

    /**
     * Get SVG style definition
     *
     * Returns a string with SVG style definitions created from color, 
     * fillstatus and line thickness.
     * 
     * @param ezcGraphColor $color Color
     * @param mixed $filled Filled
     * @param float $thickness Line thickness.
     * @return string Formatstring
     */
    protected function getStyle( ezcGraphColor $color, $filled = true, $thickness = 1 )
    {
        if ( $filled )
        {
            if ( $url = $this->getGradientUrl( $color ) )
            {
                return sprintf( 'fill: %s; stroke: none;', $url );
            }
            else
            {
                return sprintf( 'fill: #%02x%02x%02x; fill-opacity: %.2f; stroke: none;',
                    $color->red,
                    $color->green,
                    $color->blue,
                    1 - ( $color->alpha / 255 )
                );
            }
        }
        else
        {
            if ( $url = $this->getGradientUrl( $color ) )
            {
                return sprintf( 'fill: none; stroke: %s;', $url );
            }
            else
            {
                return sprintf( 'fill: none; stroke: #%02x%02x%02x; stroke-width: %d; stroke-opacity: %.2f; stroke-linecap: %s; stroke-linejoin: %s;',
                    $color->red,
                    $color->green,
                    $color->blue,
                    $thickness,
                    1 - ( $color->alpha / 255 ),
                    $this->options->strokeLineCap,
                    $this->options->strokeLineJoin
                );
            }
        }
    }

    /**
     * Draws a single polygon. 
     * 
     * @param array $points Point array
     * @param ezcGraphColor $color Polygon color
     * @param mixed $filled Filled
     * @param float $thickness Line thickness
     * @return void
     */
    public function drawPolygon( array $points, ezcGraphColor $color, $filled = true, $thickness = 1 )
    {
        $this->createDocument();

        $lastPoint = end( $points );
        $pointString = sprintf( ' M %.4f,%.4f', 
            $lastPoint->x + $this->options->graphOffset->x, 
            $lastPoint->y + $this->options->graphOffset->y
        );

        foreach ( $points as $point )
        {
            $pointString .= sprintf( ' L %.4f,%.4f', 
                $point->x + $this->options->graphOffset->x,
                $point->y + $this->options->graphOffset->y
            );
        }
        $pointString .= ' z ';

        $path = $this->dom->createElement( 'path' );
        $path->setAttribute( 'd', $pointString );

        $path->setAttribute(
            'style',
            $this->getStyle( $color, $filled, $thickness )
        );
        $path->setAttribute( 'id', $id = ( $this->options->idPrefix . 'Polygon_' . ++$this->elementID ) );
        $this->elements->appendChild( $path );

        return $id;
    }
    
    /**
     * Draws a line 
     * 
     * @param ezcGraphCoordinate $start Start point
     * @param ezcGraphCoordinate $end End point
     * @param ezcGraphColor $color Line color
     * @param float $thickness Line thickness
     * @return void
     */
    public function drawLine( ezcGraphCoordinate $start, ezcGraphCoordinate $end, ezcGraphColor $color, $thickness = 1 )
    {
        $this->createDocument();  
        
        $pointString = sprintf( ' M %.4f,%.4f L %.4f,%.4f', 
            $start->x + $this->options->graphOffset->x, 
            $start->y + $this->options->graphOffset->y,
            $end->x + $this->options->graphOffset->x, 
            $end->y + $this->options->graphOffset->y
        );

        $path = $this->dom->createElement( 'path' );
        $path->setAttribute( 'd', $pointString );
        $path->setAttribute(
            'style', 
            $this->getStyle( $color, false, $thickness )
        );

        $path->setAttribute( 'id', $id = ( $this->options->idPrefix . 'Line_' . ++$this->elementID ) );
        $this->elements->appendChild( $path );

        return $id;
    }

    /**
     * Test if string fits in a box with given font size
     *
     * This method splits the text up into tokens and tries to wrap the text
     * in an optimal way to fit in the Box defined by width and height. We 
     * can't really know how big the SVG renderer will display the font, so 
     * that we can just guess here. Additionally there is no method to 
     * calculate the text width of a string with the font used by the SVG 
     * renderer, so that we assume some character width for calculating the 
     * text width.
     * 
     * If the text fits into the box an array with lines is returned, which 
     * can be used to render the text later:
     *  array(
     *      // Lines
     *      array( 'word', 'word', .. ),
     *  )
     * Otherwise the function will return false.
     *
     * @param string $string Text
     * @param ezcGraphCoordinate $position Topleft position of the text box
     * @param float $width Width of textbox
     * @param float $height Height of textbox
     * @param int $size Fontsize
     * @return mixed Array with lines or false on failure
     */
    protected function testFitStringInTextBox( $string, ezcGraphCoordinate $position, $width, $height, $size )
    {
        // Tokenize String
        $tokens = preg_split( '/\s+/', $string );
        
        $lines = array( array() );
        $line = 0;
        foreach ( $tokens as $token )
        {
            // Add token to tested line
            $selectedLine = $lines[$line];
            $selectedLine[] = $token;

            // Assume characters have the same width as height
            $strWidth = $this->getTextWidth( implode( ' ', $selectedLine ), $size );

            // Check if line is too long
            if ( $strWidth > $width )
            {
                if ( count( $selectedLine ) == 1 )
                {
                    // Return false if one single word does not fit into one line
                    return false;
                }
                else
                {
                    // Put word in next line instead and reduce available height by used space
                    $lines[++$line][] = $token;
                    $height -= $size * ( 1 + $this->options->lineSpacing );
                }
            }
            else
            {
                // Everything is ok - put token in this line
                $lines[$line][] = $token;
            }
            
            // Return false if text exceeds vertical limit
            if ( $size > $height )
            {
                return false;
            }
        }

        // Check width of last line
        $strWidth = $this->getTextWidth( implode( ' ', $selectedLine ), $size );
        if ( $strWidth > $width )
        {
            return false;
        }

        // It seems to fit - return line array
        return $lines;
    }

    /**
     * Writes text in a box of desired size
     * 
     * @param string $string Text
     * @param ezcGraphCoordinate $position Top left position
     * @param float $width Width of text box
     * @param float $height Height of text box
     * @param int $align Alignement of text
     * @return void
     */
    public function drawTextBox( $string, ezcGraphCoordinate $position, $width, $height, $align )
    {
        $padding = $this->options->font->padding + ( $this->options->font->border !== false ? $this->options->font->borderWidth : 0 );

        $width -= $padding * 2;
        $height -= $padding * 2;
        $position->x += $padding;
        $position->y += $padding;

        // Try to get a font size for the text to fit into the box
        $maxSize = min( $height, $this->options->font->maxFontSize );
        $result = false;
        for ( $size = $maxSize; $size >= $this->options->font->minFontSize; --$size )
        {
            $result = $this->testFitStringInTextBox( $string, $position, $width, $height, $size );
            if ( $result !== false )
            {
                break;
            }
        }
        
        if ( !is_array( $result ) )
        {
            throw new ezcGraphFontRenderingException( $string, $this->options->font->minFontSize, $width, $height );
        }

        $this->options->font->minimalUsedFont = $size;
        $this->strings[] = array(
            'text' => $result,
            'id' => $id = ( $this->options->idPrefix . 'TextBox_' . ++$this->elementID ),
            'position' => $position,
            'width' => $width,
            'height' => $height,
            'align' => $align,
            'font' => $this->options->font,
        );

        return $id;
    }

    /**
     * Guess text width for string
     *
     * The is no way to know the font or fontsize used by the SVG renderer to
     * render the string. We assume some character width defined in the SVG 
     * driver options, tu guess the length of a string. We discern between
     * numeric an non numeric strings, because we often use only numeric 
     * strings to display chart data and numbers tend to be a bit wider then
     * characters.
     * 
     * @param mixed $string 
     * @param mixed $size 
     * @access protected
     * @return void
     */
    protected function getTextWidth( $string, $size )
    {
        if ( is_numeric( $string ) )
        {
            return $size * strlen( $string ) * $this->options->assumedNumericCharacterWidth;
        }
        else
        {
            return $size * strlen( $string ) * $this->options->assumedTextCharacterWidth;
        }
    }

    /**
     * Draw all collected texts
     *
     * The texts are collected and their maximum possible font size is 
     * calculated. This function finally draws the texts on the image, this
     * delayed drawing has two reasons:
     *
     * 1) This way the text strings are always on top of the image, what 
     *    results in better readable texts
     * 2) The maximum possible font size can be calculated for a set of texts
     *    with the same font configuration. Strings belonging to one chart 
     *    element normally have the same font configuration, so that all texts
     *    belonging to one element will have the same font size.
     * 
     * @access protected
     * @return void
     */
    protected function drawAllTexts()
    {
        foreach ( $this->strings as $text )
        {
            $size = $text['font']->minimalUsedFont;
            $font = $text['font']->name;

            $completeHeight = count( $text['text'] ) * $size + ( count( $text['text'] ) - 1 ) * $this->options->lineSpacing;

            // Calculate y offset for vertical alignement
            switch ( true )
            {
                case ( $text['align'] & ezcGraph::BOTTOM ):
                    $yOffset = $text['height'] - $completeHeight;
                    break;
                case ( $text['align'] & ezcGraph::MIDDLE ):
                    $yOffset = ( $text['height'] - $completeHeight ) / 2;
                    break;
                case ( $text['align'] & ezcGraph::TOP ):
                default:
                    $yOffset = 0;
                    break;
            }

            $padding = $text['font']->padding + $text['font']->borderWidth / 2;
            if ( $this->options->font->minimizeBorder === true )
            {
                // Calculate maximum width of text rows
                $width = false;
                foreach ( $text['text'] as $line )
                {
                    $string = implode( ' ', $line );
                    if ( ( $strWidth = $this->getTextWidth( $string, $size ) ) > $width )
                    {
                        $width = $strWidth;
                    }
                }

                switch ( true )
                {
                    case ( $text['align'] & ezcGraph::LEFT ):
                        $xOffset = 0;
                        break;
                    case ( $text['align'] & ezcGraph::CENTER ):
                        $xOffset = ( $text['width'] - $width ) / 2;
                        break;
                    case ( $text['align'] & ezcGraph::RIGHT ):
                        $xOffset = $text['width'] - $width;
                        break;
                }

                $borderPolygonArray = array(
                    new ezcGraphCoordinate(
                        $text['position']->x - $padding + $xOffset,
                        $text['position']->y - $padding + $yOffset
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x + $padding * 2 + $xOffset + $width,
                        $text['position']->y - $padding + $yOffset
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x + $padding * 2 + $xOffset + $width,
                        $text['position']->y + $padding * 2 + $yOffset + $completeHeight
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x - $padding + $xOffset,
                        $text['position']->y + $padding * 2 + $yOffset + $completeHeight
                    ),
                );
            }
            else
            {
                $borderPolygonArray = array(
                    new ezcGraphCoordinate(
                        $text['position']->x - $padding,
                        $text['position']->y - $padding
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x + $padding * 2 + $text['width'],
                        $text['position']->y - $padding
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x + $padding * 2 + $text['width'],
                        $text['position']->y + $padding * 2 + $text['height']
                    ),
                    new ezcGraphCoordinate(
                        $text['position']->x - $padding,
                        $text['position']->y + $padding * 2 + $text['height']
                    ),
                );
            }

            if ( $text['font']->background !== false )
            {
                $this->drawPolygon( 
                    $borderPolygonArray, 
                    $text['font']->background,
                    true
                );
            }

            if ( $text['font']->border !== false )
            {
                $this->drawPolygon( 
                    $borderPolygonArray, 
                    $text['font']->border,
                    false,
                    $text['font']->borderWidth
                );
            }

            // Render text with evaluated font size
            foreach ( $text['text'] as $line )
            {
                $string = implode( ' ', $line );
                $text['position']->y += $size;

                switch ( true )
                {
                    case ( $text['align'] & ezcGraph::LEFT ):
                        $position = new ezcGraphCoordinate(
                            $text['position']->x, 
                            $text['position']->y + $yOffset
                        );
                        break;
                    case ( $text['align'] & ezcGraph::RIGHT ):
                        $position = new ezcGraphCoordinate(
                            $text['position']->x + ( $text['width'] - $this->getTextWidth( $string, $size ) ),
                            $text['position']->y + $yOffset
                        );
                        break;
                    case ( $text['align'] & ezcGraph::CENTER ):
                        $position = new ezcGraphCoordinate(
                            $text['position']->x + ( ( $text['width'] - $this->getTextWidth( $string, $size ) ) / 2 ),
                            $text['position']->y + $yOffset
                        );
                        break;
                }

                // Optionally draw text shadow
                if ( $text['font']->textShadow === true )
                {
                    $textNode = $this->dom->createElement( 'text', $string );
                    $textNode->setAttribute( 'id', $text['id'] );
                    $textNode->setAttribute( 'x', $position->x + $this->options->graphOffset->x + $text['font']->textShadowOffset );
                    $textNode->setAttribute( 'text-length', $this->getTextWidth( $string, $size ) . 'px' );
                    $textNode->setAttribute( 'y', $position->y + $this->options->graphOffset->y + $text['font']->textShadowOffset );
                    $textNode->setAttribute( 
                        'style', 
                        sprintf(
                            'font-size: %dpx; font-family: %s; fill: #%02x%02x%02x; fill-opacity: %.2f; stroke: none;',
                            $size,
                            $text['font']->name,
                            $text['font']->textShadowColor->red,
                            $text['font']->textShadowColor->green,
                            $text['font']->textShadowColor->blue,
                            1 - ( $text['font']->textShadowColor->alpha / 255 )
                        )
                    );
                    $this->elements->appendChild( $textNode );
                }
                
                // Finally draw text
                $textNode = $this->dom->createElement( 'text', $string );
                $textNode->setAttribute( 'id', $text['id'] );
                $textNode->setAttribute( 'x', $position->x + $this->options->graphOffset->x );
                $textNode->setAttribute( 'text-length', $this->getTextWidth( $string, $size ) . 'px' );
                $textNode->setAttribute( 'y', $position->y + $this->options->graphOffset->y );
                $textNode->setAttribute( 
                    'style', 
                    sprintf(
                        'font-size: %dpx; font-family: %s; fill: #%02x%02x%02x; fill-opacity: %.2f; stroke: none;',
                        $size,
                        $text['font']->name,
                        $text['font']->color->red,
                        $text['font']->color->green,
                        $text['font']->color->blue,
                        1 - ( $text['font']->color->alpha / 255 )
                    )
                );
                $this->elements->appendChild( $textNode );

                $text['position']->y += $size * $this->options->lineSpacing;
            }
        }
    }

    /**
     * Draws a sector of cirlce
     * 
     * @param ezcGraphCoordinate $center Center of circle
     * @param mixed $width Width
     * @param mixed $height Height
     * @param mixed $startAngle Start angle of circle sector
     * @param mixed $endAngle End angle of circle sector
     * @param ezcGraphColor $color Color
     * @param mixed $filled Filled
     * @return void
     */
    public function drawCircleSector( ezcGraphCoordinate $center, $width, $height, $startAngle, $endAngle, ezcGraphColor $color, $filled = true )
    {
        $this->createDocument();  

        // Normalize angles
        if ( $startAngle > $endAngle )
        {
            $tmp = $startAngle;
            $startAngle = $endAngle;
            $endAngle = $tmp;
        }
        
        // We need the radius
        $width /= 2;
        $height /= 2;

        $Xstart = $center->x + $this->options->graphOffset->x + $width * cos( ( -$startAngle / 180 ) * M_PI );
        $Ystart = $center->y + $this->options->graphOffset->y + $height * sin( ( $startAngle / 180 ) * M_PI );
        $Xend = $center->x + $this->options->graphOffset->x + $width * cos( ( -( $endAngle ) / 180 ) * M_PI );
        $Yend = $center->y + $this->options->graphOffset->y + $height * sin( ( ( $endAngle ) / 180 ) * M_PI );
        
        $arc = $this->dom->createElement( 'path' );
        $arc->setAttribute( 'd', sprintf( 'M %.2f,%.2f L %.2f,%.2f A %.2f,%.2f 0 %d,1 %.2f,%.2f z',
            // Middle
            $center->x + $this->options->graphOffset->x, $center->y + $this->options->graphOffset->y,
            // Startpoint
            $Xstart, $Ystart,
            // Radius
            $width, $height,
            // SVG-Stuff
            ( $endAngle - $startAngle ) > 180,
            // Endpoint
            $Xend, $Yend
            )
        );

        $arc->setAttribute(
            'style', 
            $this->getStyle( $color, $filled, 1 )
        );
        
        $arc->setAttribute( 'id', $id = ( $this->options->idPrefix . 'CircleSector_' . ++$this->elementID ) );
        $this->elements->appendChild( $arc );
        
        return $id;
    }

    /**
     * Draws a circular arc
     * 
     * @param ezcGraphCoordinate $center Center of ellipse
     * @param integer $width Width of ellipse
     * @param integer $height Height of ellipse
     * @param integer $size Height of border
     * @param float $startAngle Starting angle of circle sector
     * @param float $endAngle Ending angle of circle sector
     * @param ezcGraphColor $color Color of Border
     * @return void
     */
    public function drawCircularArc( ezcGraphCoordinate $center, $width, $height, $size, $startAngle, $endAngle, ezcGraphColor $color, $filled = true )
    {
        $this->createDocument();  

        // Normalize angles
        if ( $startAngle > $endAngle )
        {
            $tmp = $startAngle;
            $startAngle = $endAngle;
            $endAngle = $tmp;
        }
        
        if ( ( $endAngle - $startAngle > 180 ) ||
             ( ( $startAngle % 180 != 0) && ( $endAngle % 180 != 0) && ( ( $startAngle % 360 > 180 ) XOR ( $endAngle % 360 > 180 ) ) ) )
        {
            // Border crosses he 180 degrees border
            $intersection = floor( $endAngle / 180 ) * 180;
            while ( $intersection >= $endAngle )
            {
                $intersection -= 180;
            }

            $this->drawCircularArc( $center, $width, $height, $size, $startAngle, $intersection, $color, $filled );
            $this->drawCircularArc( $center, $width, $height, $size, $intersection, $endAngle, $color, $filled );
            return;
        }

        // We need the radius
        $width /= 2;
        $height /= 2;

        $Xstart = $center->x + $this->options->graphOffset->x + $width * cos( -( $startAngle / 180 ) * M_PI );
        $Ystart = $center->y + $this->options->graphOffset->y + $height * sin( ( $startAngle / 180 ) * M_PI );
        $Xend = $center->x + $this->options->graphOffset->x + $width * cos( ( -( $endAngle ) / 180 ) * M_PI );
        $Yend = $center->y + $this->options->graphOffset->y + $height * sin( ( ( $endAngle ) / 180 ) * M_PI );
        
        if ( $filled === true )
        {
            $arc = $this->dom->createElement( 'path' );
            $arc->setAttribute( 'd', sprintf( 'M %.2f,%.2f A %.2f,%.2f 0 %d,0 %.2f,%.2f L %.2f,%.2f A %.2f,%2f 0 %d,1 %.2f,%.2f z',
                // Endpoint low
                $Xend, $Yend + $size,
                // Radius
                $width, $height,
                // SVG-Stuff
                ( $endAngle - $startAngle ) > 180,
                // Startpoint low
                $Xstart, $Ystart + $size,
                // Startpoint
                $Xstart, $Ystart,
                // Radius
                $width, $height,
                // SVG-Stuff
                ( $endAngle - $startAngle ) > 180,
                // Endpoint
                $Xend, $Yend
                )
            );
        }
        else
        {
            $arc = $this->dom->createElement( 'path' );
            $arc->setAttribute( 'd', sprintf( 'M %.2f,%.2f  A %.2f,%.2f 0 %d,1 %.2f,%.2f',
                // Startpoint
                $Xstart, $Ystart,
                // Radius
                $width, $height,
                // SVG-Stuff
                ( $endAngle - $startAngle ) > 180,
                // Endpoint
                $Xend, $Yend
                )
            );
        }

        $arc->setAttribute(
            'style', 
            $this->getStyle( $color, $filled )
        );

        $arc->setAttribute( 'id', $id = ( $this->options->idPrefix . 'CircularArc_' . ++$this->elementID ) );
        $this->elements->appendChild( $arc );

        if ( ( $this->options->shadeCircularArc !== false ) &&
             $filled )
        {
            $gradient = new ezcGraphLinearGradient(
                new ezcGraphCoordinate(
                    $center->x - $width,
                    $center->y
                ),
                new ezcGraphCoordinate(
                    $center->x + $width,
                    $center->y
                ),
                ezcGraphColor::fromHex( '#FFFFFF' )->transparent( $this->options->shadeCircularArc * 1.5 ),
                ezcGraphColor::fromHex( '#000000' )->transparent( $this->options->shadeCircularArc )
            );

            $arc = $this->dom->createElement( 'path' );
            $arc->setAttribute( 'd', sprintf( 'M %.2f,%.2f A %.2f,%.2f 0 %d,0 %.2f,%.2f L %.2f,%.2f A %.2f,%2f 0 %d,1 %.2f,%.2f z',
                // Endpoint low
                $Xend, $Yend + $size,
                // Radius
                $width, $height,
                // SVG-Stuff
                ( $endAngle - $startAngle ) > 180,
                // Startpoint low
                $Xstart, $Ystart + $size,
                // Startpoint
                $Xstart, $Ystart,
                // Radius
                $width, $height,
                // SVG-Stuff
                ( $endAngle - $startAngle ) > 180,
                // Endpoint
                $Xend, $Yend
                )
            );
        
            $arc->setAttribute(
                'style', 
                $this->getStyle( $gradient, $filled )
            );

            $this->elements->appendChild( $arc );
        }

        return $id;
    }

    /**
     * Draw circle 
     * 
     * @param ezcGraphCoordinate $center Center of ellipse
     * @param mixed $width Width of ellipse
     * @param mixed $height height of ellipse
     * @param ezcGraphColor $color Color
     * @param mixed $filled Filled
     * @return void
     */
    public function drawCircle( ezcGraphCoordinate $center, $width, $height, ezcGraphColor $color, $filled = true )
    {
        $this->createDocument();  
        
        $ellipse = $this->dom->createElement( 'ellipse' );
        $ellipse->setAttribute( 'cx', $center->x + $this->options->graphOffset->x );
        $ellipse->setAttribute( 'cy', $center->y + $this->options->graphOffset->y );
        $ellipse->setAttribute( 'rx', $width / 2 );
        $ellipse->setAttribute( 'ry', $height / 2 );

        $ellipse->setAttribute(
            'style', 
            $this->getStyle( $color, $filled, 1 )
        );
        
        $ellipse->setAttribute( 'id', $id = ( $this->options->idPrefix . 'Circle_' . ++$this->elementID ) );
        $this->elements->appendChild( $ellipse );

        return $id;
    }

    /**
     * Draw an image 
     *
     * The image will be inlined in the SVG document using data URL scheme. For
     * this the mime type and base64 encoded file content will be merged to 
     * URL.
     * 
     * @param mixed $file Image file
     * @param ezcGraphCoordinate $position Top left position
     * @param mixed $width Width of image in destination image
     * @param mixed $height Height of image in destination image
     * @return void
     */
    public function drawImage( $file, ezcGraphCoordinate $position, $width, $height )
    {
        $this->createDocument();

        $data = getimagesize( $file );
        $image = $this->dom->createElement( 'image' );

        $image->setAttribute( 'x', $position->x + $this->options->graphOffset->x );
        $image->setAttribute( 'y', $position->y + $this->options->graphOffset->y );
        $image->setAttribute( 'width', $width . 'px' );
        $image->setAttribute( 'height', $height . 'px' );
        $image->setAttributeNS( 
            'http://www.w3.org/1999/xlink', 
            'xlink:href', 
            sprintf( 'data:%s;base64,%s',
                $data['mime'],
                base64_encode( file_get_contents( $file ) )
            )
        );

        $this->elements->appendChild( $image );
        $image->setAttribute( 'id', $id = ( $this->options->idPrefix . 'Image_' . ++$this->elementID ) );

        return $id;
    }

    /**
     * Finally save image
     * 
     * @param string $file Destination filename
     * @return void
     */
    public function render ( $file )
    {
        $this->createDocument();  
        $this->drawAllTexts();
        $this->dom->save( $file );
    }
}

?>
