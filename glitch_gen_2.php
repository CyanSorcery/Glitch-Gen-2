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
$mode       = Modes::SNES;

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
if (random_int(0, 10) < 4 || $mode == Modes::SNES)
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
$y_offset       = min(max($v_align + random_int(-32, 32), 32), 512 - $res_h + 32);

//Copy with potential effect
image_copy_with_effect($mode, $display, $surface, $x_offset, $y_offset, $res_w, $res_h);

//Tmp write the big surface to disk
imagepng($surface, 'surf.png');

//Get rid of the surface (we don't need it now)
unset($surface);

//Temp write the surface to disk
imagepng($display, 'tmp.png');

?>