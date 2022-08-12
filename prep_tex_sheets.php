<?php
ini_set('display_errors', 1);

/*
    prep_tex_sheets.php

    This file reads the texture sheets and prepares usable graphics with all extra/empty space removed,
    as well as reduce the color count to be more easily usable by the image generation routine.
*/

function tile_to_hex($image, $x_pos, $y_pos)
{
    //Converts a palette-based image tile to a hex string.
    $hex_str        = '';
    $x_max          = $x_pos + 8;
    $y_max          = $y_pos + 8;

    for ($x = $x_pos; $x < $x_max; $x++)
        for ($y = $y_pos; $y < $y_max; $y++)
            $hex_str    .= base_convert(imagecolorat($image, $x, $y), 10, 16);

    return $hex_str;
}

function draw_hex_sprite($image, $pal, $tile_hex, $x_pos, $y_pos)
{
    //Convert a hex string into a sprite and draw it to the image
    $chars          = strlen($tile_hex);
    $x              = $x_pos;
    $y              = $y_pos;
    $x_max          = $x_pos + 8;

    for ($i = 0; $i < $chars; $i++)
    {
        $ind        = base_convert(substr($tile_hex, $i, 1), 16, 10);
        
        if ($ind > 0)
            imagesetpixel($image, $x, $y, $pal[$ind]);
        $x++;

        if ($x >= $x_max)
        {
            $x      = $x_pos;
            $y++;
        }
    }
}

//Scan the directory for all texture pages
$page_pngs      = scandir('./tex/');

if ($page_pngs === false)
    die('Texture page directory is empty!');

//Go through each one and figure out which ones are PNGs
$tex_pages      = [];
foreach ($page_pngs as $tex_file)
{
    //Is this a PNG? If not, skip it
    if (mime_content_type("./tex/$tex_file") != 'image/png')
    {
        print("File is not image, skipping...\r\n");
        continue;
    }

    try
    {
        print("Reading file $tex_file...\r\n");
        $tex_pages[]        = imagecreatefrompng("./tex/$tex_file");
    }
    catch (Exception $e)
    {
        die($e);
    }
}

if (empty($tex_pages))
    die('No valid PNGs were found!');

//Arrays which will hold each 8x8 tile from the texture pages
$nes_gb_tiles           = [];
$snes_tiles             = [];

//We should now have some textures to work with, so let's work with them!
foreach ($tex_pages as $page)
{
    //Get the image dimensions
    $page_w         = imagesx($page);
    $page_h         = imagesy($page);

    //If this is not true color, convert it to true color so we can palette it properly later
    if (!imageistruecolor($page))
    {
        print("Image was not true color, converting to true color...\r\nIdeally, all images should be true color.\r\n");
        imagepalettetotruecolor($page);
    }
    
    //Make the image grayscale
    imagefilter($page, IMG_FILTER_GRAYSCALE);

    //Make a copy of it
    $page_hd        = imagecreatetruecolor($page_w, $page_h);
    imagealphablending($page_hd, false);
    imagesavealpha($page_hd, true);
    imagesavealpha($page, true);

    imagecopymerge($page_hd, $page, 0, 0, 0, 0, $page_w, $page_h, 100);

    //Allow for transparency
    imagecolortransparent($page, imagecolorat($page, $page_w - 1, $page_h - 1));
    imagecolortransparent($page_hd, imagecolorat($page_hd, $page_w - 1, $page_h - 1));

    //Convert the texture pages to low color mode
    imagetruecolortopalette($page, false, 3);
    imagetruecolortopalette($page_hd, false, 15);

    //Go over the images and convert the image data to a hex string
    print("Converting data to NES/GameBoy tiles...\r\n");

    for ($x_cell = 0; $x_cell < $page_w; $x_cell += 8)
    {
        for ($y_cell = 0; $y_cell < $page_h; $y_cell += 8)
        {
            $nes_gb_tiles[]     = tile_to_hex($page, $x_cell, $y_cell);
        }

        //Progress indicator
        echo chr(27) . "[0G";
        $percentage         = round(($x_cell / $page_w) * 10);
        echo '[' . str_repeat('=', $percentage) . str_repeat(' ', 10 - $percentage) . '] ' . ($percentage * 10) . '%';
    }
    print("\r\n");

    print("Converting data to SNES tiles...\r\n");

    for ($x_cell = 0; $x_cell < $page_w; $x_cell += 8)
    {
        for ($y_cell = 0; $y_cell < $page_h; $y_cell += 8)
        {
            $snes_tiles[]     = tile_to_hex($page_hd, $x_cell, $y_cell);;
        }

        //Progress indicator
        echo chr(27) . "[0G";
        $percentage         = round(($x_cell / $page_w) * 10);
        echo '[' . str_repeat('=', $percentage) . str_repeat(' ', 10 - $percentage) . '] ' . ($percentage * 10) . '%';
    }
    print("\r\n");

    //Remove the image resources from ram
    unset($page);
    unset($page_hd);
}

//Remove duplicates from the array
$nes_gb_tiles       = array_keys(array_flip($nes_gb_tiles));
$snes_tiles         = array_keys(array_flip($snes_tiles));

//Now we should have nice arrays of tiles, so let's write them out! We'll do the NES one first
$max_width          = 2048;

//First, determine the height of the image
$max_height         = ceil(count($nes_gb_tiles) / ($max_width / 8)) * 8;

//Create a new image to hold this data
$nes_tile_set       = imagecreate($max_width, $max_height);

//Assign the colors
$nes_colors     = [
    imagecolorallocate($nes_tile_set, 0, 0, 0),
    imagecolorallocate($nes_tile_set, 64, 64, 64),
    imagecolorallocate($nes_tile_set, 128, 128, 128),
    imagecolorallocate($nes_tile_set, 192, 192, 192)
];

imagecolortransparent($nes_tile_set, $nes_colors[0]);

print("Finalizing the NES tileset...\r\n");

//Write the NES tile data
$x_cell     = 0;
$y_cell     = 0;

foreach ($nes_gb_tiles as $tile)
{
    draw_hex_sprite($nes_tile_set, $nes_colors, $tile, $x_cell, $y_cell);
    $x_cell     += 8;

    if ($x_cell >= $max_width)
    {
        $x_cell     = 0;
        $y_cell     += 8;
        
        //Progress indicator
        echo chr(27) . "[0G";
        $percentage         = round(($y_cell / $max_height) * 10);
        echo '[' . str_repeat('=', $percentage) . str_repeat(' ', 10 - $percentage) . '] ' . ($percentage * 10) . '%';
    }
}

//Finally, write the NES image
imagepng($nes_tile_set, './nes.png');
unset($nes_tile_set);

//Now, let's do the SNES one

//First, determine the height of the image
$max_height         = ceil(count($snes_tiles) / ($max_width / 8)) * 8;

//Create a new image to hold this data
$snes_tile_set       = imagecreate($max_width, $max_height);

//Assign the colors
$snes_colors     = [
    imagecolorallocate($snes_tile_set, 0, 0, 0),
    imagecolorallocate($snes_tile_set, 16, 16, 16),
    imagecolorallocate($snes_tile_set, 32, 32, 32),
    imagecolorallocate($snes_tile_set, 48, 48, 48),
    imagecolorallocate($snes_tile_set, 64, 64, 64),
    imagecolorallocate($snes_tile_set, 80, 80, 80),
    imagecolorallocate($snes_tile_set, 96, 96, 96),
    imagecolorallocate($snes_tile_set, 112, 112, 112),
    imagecolorallocate($snes_tile_set, 128, 128, 128),
    imagecolorallocate($snes_tile_set, 144, 144, 144),
    imagecolorallocate($snes_tile_set, 160, 160, 160),
    imagecolorallocate($snes_tile_set, 176, 176, 176),
    imagecolorallocate($snes_tile_set, 192, 192, 192),
    imagecolorallocate($snes_tile_set, 208, 208, 208),
    imagecolorallocate($snes_tile_set, 224, 224, 224),
    imagecolorallocate($snes_tile_set, 240, 240, 240),
];

imagecolortransparent($snes_tile_set, $snes_colors[0]);

print("\r\n");
print("Finalizing the SNES tileset...\r\n");

//Write the NES tile data
$x_cell     = 0;
$y_cell     = 0;

foreach ($snes_tiles as $tile)
{
    draw_hex_sprite($snes_tile_set , $snes_colors, $tile, $x_cell, $y_cell);
    $x_cell     += 8;

    if ($x_cell >= $max_width)
    {
        $x_cell     = 0;
        $y_cell     += 8;
        
        //Progress indicator
        echo chr(27) . "[0G";
        $percentage         = round(($y_cell / $max_height) * 10);
        echo '[' . str_repeat('=', $percentage) . str_repeat(' ', 10 - $percentage) . '] ' . ($percentage * 10) . '%';
    }
}

//Finally, write the NES image
imagepng($snes_tile_set, './snes.png');
unset($snes_tile_set);

print("\r\n");
print("Done.\r\n");

?>