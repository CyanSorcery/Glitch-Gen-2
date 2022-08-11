<?php
ini_set('display_errors', 1);

/*
    Glitched Image Generator, v2
    
    Generates a glitched image based on old video game consoles.
*/

//Get all the relevant data
require('gen_functions.php');

//Determine what mode we'll be in
//$mode           = array_rand([Modes::NES, Modes::GB, Modes::SNES]);
$mode       = Modes::GB;

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

//If this is the NES mode, we need to make an array of palettes that match the 2x2 nature of the NES palette assignment
if ($mode == Modes::NES)
{
    $nes_pal_assign     = [];

    for ($x = 0; $x < 32; $x++)
        for ($y = 0; $y < 32; $y++)
            $nes_pal_assign[$x][$y]     = $palettes['bg'.random_int(0, 3)];
}

//Fill in the background layer with tiles
//Temporarily force this to true, will load Nova levels later
if (true)
{
    //Randomly spam tiles to the background layer
    for ($x_cell = 0; $x_cell < 512; $x_cell += 8)
    {
        for ($y_cell = 0; $y_cell < 512; $y_cell += 8)
        {
            //Pick a palette
            switch ($mode)
            {
                case Modes::NES:
                {
                    $pal        = $nes_pal_assign[$x_cell >> 4][$y_cell >> 4];
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
            copy_to_surface($surface, $bg_chr, $pal, $x_cell, $y_cell);
        }
    }
}

//Temp write the surface to disk
imagepng($surface, 'tmp.png');

?>