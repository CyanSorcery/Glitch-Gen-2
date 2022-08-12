<?php
ini_set('display_errors', 1);
/*
    gen_functions.php

    Various functions for the glitched image generator.
*/

abstract class Modes {
    const NES       = 0;
    const GB        = 1;
    const SNES      = 2;
}

function point_in_rectangle($x, $y, $min_x, $min_y, $max_x, $max_y)
{
    return ($x <= $max_x && $x >= $min_x && $y <= $max_y && $y >= $min_y);
}

function allocate_palette($image, $mode)
{
    //Allocate our palette to the image. In the event that the color is NULL, color is not drawn.

    //Get the color data
    $colors     = json_decode(file_get_contents('colors.json'), true) or die('Could not read color.json!');

    //First, get the BG color (The BG color is not used in GB mode.)
    $fin_colors     = [];
    switch ($mode)
    {
        case Modes::NES:
        {
            $col        = $colors['nes'][array_rand($colors['nes'])];
            $fin_colors['bg']   = imagecolorallocate($image, $col['r'], $col['g'], $col['b']);
            break;
        }
        case Modes::SNES:
        {
            $fin_colors['bg']   = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            break;
        }
    }

    //Now, fill in the rest of the colors
    switch ($mode)
    {
        case Modes::NES:
        {
            for ($i = 0; $i < 4; $i++)
            {
                $fin_colors['bg'.$i][0]     = null;
                for ($sub = 1; $sub < 4; $sub++)
                {
                    $col                        = $colors['nes'][array_rand($colors['nes'])];
                    $fin_colors['bg'.$i][$sub]  = imagecolorallocate($image, $col['r'], $col['g'], $col['b']);
                }
            }
            for ($i = 0; $i < 4; $i++)
            {
                $fin_colors['fg'.$i][0]     = null;
                for ($sub = 1; $sub < 4; $sub++)
                {
                    $col                        = $colors['nes'][array_rand($colors['nes'])];
                    $fin_colors['fg'.$i][$sub]  = imagecolorallocate($image, $col['r'], $col['g'], $col['b']);
                }
            }
            break;
        }
        case Modes::GB:
        {
            for ($pal = 0; $pal < 3; $pal++)
            {
                $col        = $colors['gb'][array_rand($colors['gb'])];
                for ($i = 0; $i < 4; $i++)
                {
                    $fin_colors['pal'.$pal][$i]     = imagecolorallocate($image, $col[$i]['r'], $col[$i]['g'], $col[$i]['b']);
                }
            }
            break;
        }
        case Modes::SNES:
        {
            for ($bg_i = 0; $bg_i < 16; $bg_i++)
            {
                $fin_colors['bg'.$bg_i][0]      = null;
                for ($pal = 1; $pal < 16; $pal++)
                {
                    $fin_colors['bg'.$bg_i][$pal]   = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                }
            }
            for ($fg_i = 0; $fg_i < 16; $fg_i++)
            {
                $fin_colors['fg'.$fg_i][0]      = null;
                for ($pal = 1; $pal < 16; $pal++)
                {
                    $fin_colors['fg'.$fg_i][$pal]   = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                }
            }
            break;
        }
    }

    //Return the resulting palette
    return $fin_colors;
}

function copy_to_surface($surface, $bg_chr, $pal, $x_dst, $y_dst, $tile_id = null)
{
    //This copies tiles from the background tileset to the surface, applying the palette and any transformation

    if ($tile_id == null)
        $tile_id        = random_int(0, 255);

    //First, figure out the source position
    $x_src      = ($tile_id % 16) * 8;
    $y_src      = floor($tile_id / 16) * 8;

    for ($x = 0; $x < 8; $x++)
    {
        for ($y = 0; $y < 8; $y++)
        {
            //What color index is here? Compare it to the palette given
            $col_at     = $pal[imagecolorat($bg_chr, $x_src + $x, $y_src + $y)];

            //If the color is not null, draw it to the destination surface
            if ($col_at != null)
                imagesetpixel($surface, $x_dst + $x, $y_dst + $y, $col_at);
        }
    }
}

function nova_level_convert()
{
    //Pick a random level
    $files 				= scandir('nova_levels');
    $filepath			= '';
    while (strpos($filepath, '.json') === false)
        $filepath			= $files[array_rand($files)];

    //Converts a Nova the Squirrel level to an array of tiles we can read from
    $level_json         = json_decode(file_get_contents("nova_levels/$filepath"), true) or die("Could not read Nova level! Attempted to read $filepath");

    //Figure out the dimensions of our level data (in 8x8 tiles)
    $tile_mult_x    = ceil($level_json['Meta']['TileWidth'] / 8);
    $tile_mult_y    = ceil($level_json['Meta']['TileHeight'] / 8);
    $level_w        = $level_json['Meta']['Width'] * $tile_mult_x;
    $level_h        = $level_json['Meta']['Height'] * $tile_mult_y;

    //Set up our level data array. Tiles that aren't used wont be specified
    $level_data     = [
        'fg'        => [],
        'bg'        => [],
        'spr'       => [],
        'Width'     => $level_w,
        'Height'    => $level_h
    ];
    
    $level_data['Width']        = $level_w;
    $level_data['Height']       = $level_h;
    
    //For holding the tile IDs of each type of entity
    $tile_ids       = [
        'fg' => [],
        'bg' => [],
        'spr' => []
    ];
    
    //Go through the level data and assign it to our grid
    foreach ($level_json['Layers'] as $layer)
    {
        switch ($layer['Name'])
        {
            case 'Foreground':
            {
                $key        = 'fg';
                break;
            }
            case 'Sprites':
            {
                $key        = 'spr';
                break;
            }
            //Not a supported layer so skip it
            default:
                continue;
        }

        //Go through the data and assign it to our grid
        foreach ($layer['Data'] as $entity)
        {
            //Is this entity in our tile table? If so, look it up
            if (key_exists($entity['Id'], $tile_ids[$key]))
                $tile_data      = $tile_ids[$key][$entity['Id']];
            else
            {
                $tile_data      = [];
                for ($x = 0; $x < $tile_mult_x; $x++)
                    for ($y = 0; $y < $tile_mult_y; $y++)
                        $tile_data[$x][$y]      = random_int(1, 255);

                $tile_ids[$key][$entity['Id']]    = $tile_data;
            }

            //Figure out where we're putting this in our data array
            $base_x     = $entity['X'] * $tile_mult_x;
            $base_y     = $entity['Y'] * $tile_mult_y;
            $e_max_x    = $base_x + ($entity['W'] * $tile_mult_x);
            $e_max_y    = $base_y + ($entity['H'] * $tile_mult_y);

            //Finally, put it in
            for ($x_cell = $base_x; $x_cell < $e_max_x; $x_cell += $tile_mult_x)
                for ($y_cell = $base_y; $y_cell < $e_max_y; $y_cell += $tile_mult_y)
                    for ($x = 0; $x < $tile_mult_x; $x++)
                        for ($y = 0; $y < $tile_mult_y; $y++)
                            $level_data[$key][$x_cell + $x][$y_cell + $y]   = $tile_data[$x][$y];
        }
    }

    //Return the level data
    return $level_data;
}

function get_background_pattern()
{
    //Get a random background pattern to use for the background layer.
    //tmp
    $pattern        = random_int(0, 18);

    $tile_1         = random_int(0, 255);
    $tile_2         = random_int(0, 255);

    //Just do a random but repeating pattern?
    if (mt_rand(0, 10) < 3)
    {
        //Prep the array
        $tiles          = array_flip([$tile_1, $tile_2]);
        return [
            [array_rand($tiles), array_rand($tiles), array_rand($tiles), array_rand($tiles)],
            [array_rand($tiles), array_rand($tiles), array_rand($tiles), array_rand($tiles)],
            [array_rand($tiles), array_rand($tiles), array_rand($tiles), array_rand($tiles)],
            [array_rand($tiles), array_rand($tiles), array_rand($tiles), array_rand($tiles)]
        ];
    }

    switch ($pattern)
    {
        //Simple background
        case 0:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1]
            ];
        
        //Vertical stripes
        case 1:
            return [
                [$tile_1, $tile_2, $tile_1, $tile_2],
                [$tile_1, $tile_2, $tile_1, $tile_2],
                [$tile_1, $tile_2, $tile_1, $tile_2],
                [$tile_1, $tile_2, $tile_1, $tile_2]
            ];
        //Vertical stripes (big)
        case 2:
            return [
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_2]
            ];
        
        //Horizontal stripes
        case 3:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_2, $tile_2, $tile_2, $tile_2],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_2, $tile_2, $tile_2, $tile_2]
            ];

        //Horizontal stripes (big)
        case 4:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_2, $tile_2, $tile_2, $tile_2],
                [$tile_2, $tile_2, $tile_2, $tile_2]
            ];
        
        //Small vertical stripe
        case 5:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_1, $tile_2]
            ];
        
        //Small horizontal stripe
        case 6:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_2, $tile_2, $tile_2, $tile_2]
            ];
        
        //Checkerboard
        case 7:
            return [
                [$tile_2, $tile_2, $tile_1, $tile_1],
                [$tile_2, $tile_2, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_2]
            ];
        
        //Diagonal lines this way /
        case 8:
            return [
                [$tile_1, $tile_2, $tile_2, $tile_1],
                [$tile_2, $tile_2, $tile_1, $tile_1],
                [$tile_2, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_2]
            ];
        
        //Diagonal lines this way \
        case 9:
            return [
                [$tile_1, $tile_2, $tile_2, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_2, $tile_1, $tile_1, $tile_2],
                [$tile_2, $tile_2, $tile_1, $tile_1]
            ];
        
        //Diagonal line small this way /
        case 10:
            return [
                [$tile_1, $tile_2, $tile_1, $tile_1],
                [$tile_2, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_1]
            ];
        
        //Diagonal line small this way \
        case 11:
            return [
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_2, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_2, $tile_1, $tile_1]
            ];
        
        //Filled Circles
        case 12:
            return [
                [$tile_1, $tile_2, $tile_2, $tile_1],
                [$tile_2, $tile_2, $tile_2, $tile_2],
                [$tile_2, $tile_2, $tile_2, $tile_2],
                [$tile_1, $tile_2, $tile_2, $tile_1]
            ];
        
        //Decorative
        case 13:
            return [
                [$tile_2, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_2, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_2, $tile_1, $tile_1, $tile_2]
            ];
        
        //Decorative 2
        case 14:
            return [
                [$tile_2, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_1, $tile_2, $tile_1, $tile_1],
                [$tile_2, $tile_1, $tile_1, $tile_2]
            ];

        //Zigzags (vertical)
        case 15:
            return [
                [$tile_1, $tile_2, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_1, $tile_1, $tile_1, $tile_2],
                [$tile_1, $tile_1, $tile_2, $tile_1]
            ];
        
        //Zigzags (horizontal)
        case 16:
            return [
                [$tile_1, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_1, $tile_2, $tile_1, $tile_2],
                [$tile_2, $tile_1, $tile_1, $tile_1]
            ];
        
        //Zigzags thick (vertical)
        case 17:
            return [
                [$tile_2, $tile_2, $tile_1, $tile_1],
                [$tile_1, $tile_2, $tile_2, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_2],
                [$tile_1, $tile_2, $tile_2, $tile_1]
            ];
        
        //Zigzags thick (horizontal)
        case 18:
            return [
                [$tile_2, $tile_1, $tile_1, $tile_1],
                [$tile_1, $tile_1, $tile_2, $tile_1],
                [$tile_1, $tile_2, $tile_2, $tile_2],
                [$tile_2, $tile_2, $tile_1, $tile_2]
            ];
    }
}

?>