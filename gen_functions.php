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

function copy_to_surface($surface, $bg_chr, $pal, $x_dst, $y_dst)
{
    //This copies tiles from the background tileset to the surface, applying the palette and any transformation

    //First, figure out the source position
    $x_src      = random_int(0, 15) * 8;
    $y_src      = random_int(0, 15) * 8;

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

?>