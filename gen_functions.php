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

function generate_image()
{
    //Determine what mode we'll be in
    $mode           = array_rand([Modes::NES, Modes::GB, Modes::SNES]);

    //Grab the image we'll be working with
    if ($mode == Modes::SNES)
        $tex_sheet      = imagecreatefrompng('snes.png') or die('Could not read snes.png! Does it exist?');
    else
        $tex_sheet      = imagecreatefrompng('nes.png') or die ('Could not read nes.png! Does it exist?');

    //Calculate the dimensions of the texture page, as well as the amount of tiles in it
    $tex_w          = imagesx($tex_sheet);
    $tex_h          = imagesy($tex_sheet);
    $tex_tile_w     = floor($tex_w / 8);
    $tex_tile_h     = floor($tex_h / 8);

    /*
        Limitation notes:
        To make the code standard across modes, this simply assumes a 128x128 size for background and sprite modes,
        or 256 tiles for each (512 tiles total.) All tiles are assumed to be 8x8, and we don't worry TOO much about
        the sprite limits of each system.
    */

    //Grab tiles for the background tileset we'll be using
    $bg_chr         = imagecreate(128, 128);
    imagepalettecopy($bg_chr, $tex_sheet);
    imagealphablending($bg_chr, false);
    imagefilledrectangle($bg_chr, 0, 0, 128, 128, 0);
    for ($x_cell = 0; $x_cell < 128; $x_cell += 8)
        for ($y_cell = 0; $y_cell < 128; $y_cell += 8)
            imagecopy($bg_chr, $tex_sheet, $x_cell, $y_cell, random_int(0, $tex_tile_w - 1) * 8, random_int(0, $tex_tile_h - 1) * 8, 8, 8);

    //Now grab the sprite tileset
    $spr_chr         = imagecreate(128, 128);
    imagepalettecopy($spr_chr, $tex_sheet);
    imagealphablending($spr_chr, false);
    imagefilledrectangle($spr_chr, 0, 0, 128, 128, 0);
    for ($x_cell = 0; $x_cell < 128; $x_cell += 8)
        for ($y_cell = 0; $y_cell < 128; $y_cell += 8)
            imagecopy($spr_chr, $tex_sheet, $x_cell, $y_cell, random_int(0, $tex_tile_w - 1) * 8, random_int(0, $tex_tile_h - 1) * 8, 8, 8);

    //Remove the texture sheet as we don't need it anymore
    unset($tex_sheet);

    //Create a new image to draw to (we will crop this down later)
    $surface        = imagecreatetruecolor(512, 512);
    imagealphablending($surface, false);

    //Get our palettes
    $palettes       = allocate_palette($surface, $mode);

    //Fill in the background color?
    if ($mode == Modes::NES || $mode == Modes::SNES)
        imagefilledrectangle($surface, 0, 0, 512, 512, $palettes['bg']);
    else if ($mode == Modes::GB)
        imagefilledrectangle($surface, 0, 0, 512, 512, $palettes['pal0'][3]);

    //If this is the NES mode, we need to make an array of palettes that match the 2x2 nature of the NES palette assignment
    if ($mode == Modes::NES)
    {
        $nes_pal_assign     = [];

        for ($x = 0; $x < 32; $x++)
            for ($y = 0; $y < 32; $y++)
                $nes_pal_assign[$x][$y]     = $palettes['bg'.random_int(0, 3)];
    }

    //Load a Nova level
    $level_data         = nova_level_convert();

    //Figure out where we'll position the Nova level
    $v_align        = -32 + ($level_data['Height'] >> 2) + random_int(-6, 6);
    $offset_x       = -random_int(-8, max($level_data['Width'] - 64 + 8, -8));
    $offset_y       = -random_int($v_align, max($level_data['Height'] - 64 + 8, $v_align));

    $norm_min_x     = 0;
    $norm_min_y     = 0;
    $norm_max_x     = $level_data['Width'];
    $norm_max_y     = $level_data['Height'];

    //Draw a background? Allow for chance of no background, unless it's SNES
    if (random_int(0, 10) < 3 || $mode == Modes::SNES)
    {
        $offset_x       = 0;
        $offset_y       = 0;
        
        if ($mode == Modes::SNES)
        {
            $offset_x       = random_int(0, 7);
            $offset_y       = random_int(0, 7);
        }
        $pattern        = get_background_pattern();
        
        if ($mode == Modes::GB)
            $pal        = $palettes['pal0'];

        for ($x_cell = 0; $x_cell < 64; $x_cell++)
            for ($y_cell = 0; $y_cell < 64; $y_cell++)
            {
                //Note: X and Y are swapped here to make adding patterns easier
                $tile_id        = $pattern[$y_cell % 4][$x_cell % 4];

                if ($mode == Modes::SNES)
                    $pal            = $palettes['bg'.($tile_id % 16)];
                else if ($mode == Modes::NES)
                    $pal            = $palettes['bg'.($tile_id % 4)];

                copy_to_surface($surface, $bg_chr, $pal, $offset_x + ($x_cell * 8), $offset_y + ($y_cell * 8), $tile_id);
            }
    }

    //Go over the tiles and draw them
    foreach ($level_data as $id => $level)
    {
        //Skip this if this isn't a level
        if (!is_array($level))
            continue;
        
        //Go over the data and draw each tile to the surface
        for ($x_cell = 0; $x_cell < 64; $x_cell++)
        {
            for ($y_cell = 0; $y_cell < 64; $y_cell++)
            {
                //Pick a palette
                switch ($mode)
                {
                    case Modes::NES:
                    {
                        $pal        = $nes_pal_assign[$x_cell >> 2][$y_cell >> 2];
                        break;
                    }
                    case Modes::SNES:
                    {
                        $pal        = $palettes['bg'.random_int(0, 15)];
                        break;
                    }
                    case Modes::GB:
                    {
                        $pal        = $palettes['pal0'];
                        break;
                    }
                }

                $norm_x         = $x_cell - $offset_x;
                $norm_y         = $y_cell - $offset_y;

                //Draw a Nova tile? If not, draw a random tile?
                $rand_tile      = true;
                if (key_exists($norm_x, $level))
                {
                    if (key_exists($norm_y, $level[$norm_x]))
                    {
                        $rand_tile      = false;
                        $tile_id        = $level[$norm_x][$norm_y];

                        //Pick a more consistent color for this?
                        if ($mode == Modes::SNES)
                            $pal        = $palettes['bg'.($tile_id % 16)];

                        copy_to_surface($surface, $bg_chr, $pal, $x_cell * 8, $y_cell * 8, $tile_id);
                    }
                }
                else if (!point_in_rectangle($norm_x, $norm_y, $norm_min_x, $norm_min_y, $norm_max_x, $norm_max_y) && $id == 'bg' && $rand_tile)
                {
                    copy_to_surface($surface, $bg_chr, $pal, $x_cell * 8, $y_cell * 8);
                }
            }
        }
    }

    //Now that we've got an image generated, let's grab a chunk of it for our screen
    switch ($mode)
    {
        case Modes::SNES:
        {
            $res_w      = 256;
            $res_h      = 224;
            break;
        }
        case Modes::NES:
        {
            $res_w      = 256;
            $res_h      = 240;
            break;
        }
        case Modes::GB:
        {
            $res_w      = 160;
            $res_h      = 144;
            break;
        }
    }

    //Create our "display"
    $display        = imagecreatetruecolor($res_w, $res_h);
    imagealphablending($display, false);

    //Copy to the screen (keep it aligned vertically)
    $x_offset       = random_int(32, 512 - $res_w - 32);
    $y_offset       = min(max($offset_y + ($res_h * 0.5) + random_int(-32, 32), 32), 512 - $res_h + 32);

    //Copy with potential effect
    image_copy_with_effect($mode, $display, $surface, $x_offset, $y_offset, $res_w, $res_h);

    //Get rid of the surface (we don't need it now)
    unset($surface);

    //Time to write some sprites! We don't care too much about sprite limits here
    $max_objs        = random_int(0, ($mode == Modes::SNES ? 8 : 5));

    for ($obj = 0; $obj < $max_objs; $obj++)
    {
        //Figure out the size and position of this object
        $obj_w          = random_int(1, ($mode == Modes::SNES ? 8 : 3)) * 8;
        $obj_h          = random_int(1, ($mode == Modes::SNES ? 8 : 3)) * 8;
        $obj_x          = random_int(-$obj_w + 8, $res_w - 8);
        $obj_y          = random_int(-$obj_h + 8, $res_h - 8);

        //Pick a palette
        switch ($mode)
        {
            case Modes::NES:
            {
                $pal        = $palettes['fg'.random_int(0, 3)];
                break;
            }
            case Modes::SNES:
            {
                $pal        = $palettes['fg'.random_int(0, 15)];
                break;
            }
            case Modes::GB:
            {
                $pal        = $palettes[array_rand(array_flip(['pal1', 'pal2']))];
                break;
            }
        }

        for ($x_cell = 0; $x_cell < $obj_w; $x_cell += 8)
        {
            for ($y_cell = 0; $y_cell < $obj_h; $y_cell += 8)
            {
                copy_to_surface($display, $spr_chr, $pal, $obj_x + $x_cell, $obj_y + $y_cell, random_int(1, 255));
            }
        }
    }

    //Now, upscale the display
    $upscaled_display       = imagecreatetruecolor($res_w * 5, $res_h * 5);
    imagealphablending($display, false);
    imagecopyresized($upscaled_display, $display, 0, 0, 0, 0, $res_w * 5, $res_h * 5, $res_w, $res_h);

    //Get rid of this as we don't need it anymore
    unset($display);

    //Return the generated image
    return $upscaled_display;
}

function lerp($x1, $x2, $t)
{
    return $x1 + ($x2 - $x1) * $t;
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

            if (!isset($entity['W']))
                $entity['W']    = 1;
                
            if (!isset($entity['H']))
                $entity['H']    = 1;

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

function image_copy_with_effect($mode, $display, $surface, $x_offset, $y_offset, $res_w, $res_h)
{
    //Random chance to have no effect, or force no effect on NES
    if (random_int(0, 10) < 4 || $mode == Modes::NES)
        $effect         = 0;
    else if ($mode == Modes::GB)
        $effect         = random_int(0, 2);
    else
        $effect         = random_int(0, 7);

    //Notes: Some of the effects are achieveable on Gameboy, some or not. Keep those at the top of
    //the switch statement so we can reuse code.

    switch ($effect)
    {
        //Just a straight copy, nothing fancy
        case 0:
        {
            imagecopy($display, $surface, 0, 0, $x_offset, $y_offset, $res_w, $res_h);
            break;
        }

        //Water sine wave effect
        case 1:
        {
            //Figure out the strength of each step as well as the overall strength of the wave
            $wave_w         = random_int(1, 16);
            $wave_inc       = M_PI / random_int(3, 32);
            //Point at which the wave will start, allowing for a good chance that it'll fill the whole screen
            $wave_y_start   = $y_offset + random_int(-32, $res_h);

            for ($scanline = 0; $scanline < $res_h; $scanline++)
            {
                if ($scanline > $wave_y_start)
                    imagecopy($display, $surface, 0, $scanline, $x_offset + ($wave_w * sin($wave_inc * $scanline)), $y_offset + $scanline, $res_w, 1);
                else
                    imagecopy($display, $surface, 0, $scanline, $x_offset, $y_offset + $scanline, $res_w, 1);
            }

            break;
        }

        //Dual opposing sine waves
        case 2:
        {
            //Figure out the strength of each step as well as the overall strength of the wave
            $wave_w         = random_int(1, 16);
            $wave_inc       = M_PI / random_int(3, 32);
            //Point at which the wave will start, allowing for a good chance that it'll fill the whole screen
            $wave_y_start   = $y_offset + random_int(-32, $res_h);

            for ($scanline = 0; $scanline < $res_h; $scanline++)
            {
                if ($scanline > $wave_y_start)
                    imagecopy($display, $surface, 0, $scanline, $x_offset + floor($wave_w * sin($wave_inc * $scanline) * ($scanline % 2 == 1 ? 1 : -1)), $y_offset + $scanline, $res_w, 1);
                else
                    imagecopy($display, $surface, 0, $scanline, $x_offset, $y_offset + $scanline, $res_w, 1);
            }

            break;
        }

        //Up and down sine
        case 3:
        {
            //Figure out the strength of each step as well as the overall strength of the wave
            $wave_w         = random_int(4, 16);
            $wave_inc       = M_PI / random_int(16, 32);

            //This one will always cover the entire screen
            for ($scanline = 0; $scanline < $res_h; $scanline++)
                imagecopy($display, $surface, 0, $scanline, $x_offset, $y_offset + $scanline + floor($wave_w * sin($wave_inc * $scanline)), $res_w, 1);

            break;
        }

        //Mosaic
        case 4:
        {
            $block_size     = random_int(2, 16);

            for ($x_cell = 0; $x_cell < $res_w; $x_cell += $block_size)
            {
                for ($y_cell = 0; $y_cell < $res_h; $y_cell += $block_size)
                {
                    $col_arr    = imagecolorsforindex($surface, imagecolorat($surface, $x_offset + $x_cell, $y_offset + $y_cell));
                    $col        = imagecolorallocate($display, $col_arr['red'], $col_arr['green'], $col_arr['blue']);
                    imagefilledrectangle($display, $x_cell, $y_cell, $x_cell + $block_size, $y_cell + $block_size, $col);
                    imagecolordeallocate($display, $col);
                }
            }
            break;
        }

        //Cylinder
        case 5:
        {
            //Fix the center point to prevent out of bounds drawing
            $x_offset           = 256 - ($res_w * 0.5);

            //The top and bottom scale factor
            $edge_scale         = (1 + (random_int(-100, 100) / 100)) * 0.5;
            //The center scale factor
            $center_scale       = (1 + (random_int(-100, 100) / 100)) * 0.5;

            //This one will always cover the entire screen
            for ($scanline = 0; $scanline < $res_h; $scanline++)
            {
                //Figure out the scale factor
                $scale          = lerp($edge_scale, $center_scale, sin(M_PI * 0.5 * abs((($scanline / $res_h) * 2) - 1)));

                imagecopyresized($display, $surface, 0, $scanline, $x_offset - ($res_w * 0.5) * $scale, $y_offset + $scanline, $res_w, 1, $res_w * $scale, 1);
            }

            break;
        }
        

        //Simple rotation
        case 6:
        {
            //Create a temporary rotated image to sample from
            $rot_img        = imagerotate($surface, random_int(0, 360), 0);
            $rot_w          = imagesx($rot_img);
            $rot_h          = imagesy($rot_img);
            //Fix the center point to prevent out of bounds drawing
            $x_offset           = ($rot_w * 0.5) - ($res_w * 0.5);
            $y_offset           = ($rot_h * 0.5) - ($res_h * 0.5);

            for ($scanline = 0; $scanline < $res_h; $scanline++)
            {
                    imagecopy($display, $rot_img, 0, $scanline, $x_offset, $y_offset + $scanline, $res_w, 1);
            }

            //Get rid of the rotated image
            unset($rot_img);

            break;
        }

        //Psuedo Mode 7 floor
        case 7:
        {
            //Point at which the mode 7 starts, with a chance it'll just fill the whole screen
            $trans_start   = $y_offset + random_int(-16, $res_h * 0.75);

            //The top and bottom scale factor
            $bottom_scale         = (1 + (random_int(-100, 100) / 100)) * 0.5;
            //The center scale factor
            $top_scale       = (1 + (random_int(-100, 100) / 100)) * 0.5;

            //Create a temporary rotated image to sample from
            $rot_img        = imagerotate($surface, random_int(0, 360), 0);
            $rot_w          = imagesx($rot_img);
            $rot_h          = imagesy($rot_img);

            for ($scanline = 0; $scanline < $res_h; $scanline++)
            {
                if ($scanline > $trans_start)
                {
                    //Fix the center point to prevent out of bounds drawing
                    $x_offset           = ($rot_w * 0.5) - ($res_w * 0.5);
                    $y_offset           = ($rot_h * 0.5) - ($res_h * 0.5);

                    $scale_factor   = ($scanline - $trans_start) / ($res_h - $trans_start);

                    //Figure out the scale factor
                    $scale          = lerp($bottom_scale, $top_scale, $scale_factor);

                    imagecopyresized($display, $rot_img, 0, $scanline, $x_offset - ($res_w * 0.5) * $scale, $y_offset + $scanline, $res_w, 1, max(1, min(512, $res_w * $scale)), 1);
                }
                else
                    imagecopy($display, $surface, 0, $scanline, $x_offset, $y_offset + $scanline, $res_w, 1);
            }

            //Get rid of the rotated image
            unset($rot_img);

            break;
        }
    }
}

?>