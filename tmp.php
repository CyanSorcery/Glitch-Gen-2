<?php

//This file converts colors from NES/Gameboy and puts them into formats more easily used by imagecolorallocate
//These arrays have been around for a long time so the format was from a previous project.

/*
//NES colors
$nescol = Array();
$nescol[] = '666666';
$nescol[] = 'ADADAD';
$nescol[] = 'FFFFFF';
$nescol[] = '002A88';
$nescol[] = '155FD9';
$nescol[] = '64B0FF';
$nescol[] = 'C0DFFF';
$nescol[] = '1412A7';
$nescol[] = '4240FF';
$nescol[] = '9290FF';
$nescol[] = 'D3D2FF';
$nescol[] = '3B00A4';
$nescol[] = '7527FE';
$nescol[] = 'C676FF';
$nescol[] = 'E8C8FF';
$nescol[] = '5C007E';
$nescol[] = 'A01ACC';
$nescol[] = 'F26AFF';
$nescol[] = 'FAC2FF';
$nescol[] = '6E0040';
$nescol[] = 'B71E7B';
$nescol[] = 'FF6ECC';
$nescol[] = 'FFC4EA';
$nescol[] = '6C0700';
$nescol[] = 'B53210';
$nescol[] = 'FF8170';
$nescol[] = '561D00';
$nescol[] = '994E00';
$nescol[] = 'EA9E22';
$nescol[] = 'F7D8A5';
$nescol[] = '333500';
$nescol[] = '6B6D00';
$nescol[] = 'BCBE00';
$nescol[] = 'E4E594';
$nescol[] = '0C4800';
$nescol[] = '388700';
$nescol[] = '88D800';
$nescol[] = 'CFEF96';
$nescol[] = '005200';
$nescol[] = '0D9300';
$nescol[] = '5CE430';
$nescol[] = 'BDF4AB';
$nescol[] = '004F08';
$nescol[] = '008F32';
$nescol[] = '34E082';
$nescol[] = '3BF3CC';
$nescol[] = '00404D';
$nescol[] = '007C8D';
$nescol[] = '48CDDE';
$nescol[] = '000000';
$nescol[] = '4F4F4F';
$nescol[] = 'B8B8B8';

$nescol_fin     = [];

foreach ($nescol as $col)
{
    $int        = base_convert($col, 16, 10);

    $r          = ($int >> 16) & 0xFF;
    $g          = ($int >> 8) & 0xFF;
    $b          = ($int) & 0xFF;

    $nescol_fin[]       = ['r' => $r, 'g' => $g, 'b' => $b];
}

file_put_contents("tmp.json", json_encode($nescol_fin));
*/
/*
$palettegrid = Array();
$palettegrid['GB']['GB'] = '9BBC0F7DA4003062300F380F';

$palettegrid['SGB1'] = Array();
$palettegrid['SGB1']['A'] = 'F8E8C8D89048A82820301850';
$palettegrid['SGB1']['B'] = 'D8D8C0C8B070B05010000000';
$palettegrid['SGB1']['C'] = 'F8C0F8E89850983860383898';
$palettegrid['SGB1']['D'] = 'F8F8A8C08048F80000501800';
$palettegrid['SGB1']['E'] = 'F8D8B078C078688840583820';
$palettegrid['SGB1']['F'] = 'D8E8F8E08850A80000004010';
$palettegrid['SGB1']['G'] = '00005000A0E8787800F8F858';
$palettegrid['SGB1']['H'] = 'F8E8E0F8B888804000301800';

$palettegrid['SGB2'] = Array();
$palettegrid['SGB2']['A'] = 'F0C8A0C08848287800000000';
$palettegrid['SGB2']['B'] = 'F8F8F8F8E850F83000500058';
$palettegrid['SGB2']['C'] = 'F8C0F8E888887830E8282898';
$palettegrid['SGB2']['D'] = 'F8F8A000F800F83000000050';
$palettegrid['SGB2']['E'] = 'F8C88090B0E0281060100810';
$palettegrid['SGB2']['F'] = 'D0F8F8F89050A00000180000';
$palettegrid['SGB2']['G'] = '68B838E05040E0B880001800';
$palettegrid['SGB2']['H'] = 'F8F8F8B8B8B8707070000000';

$palettegrid['SGB3'] = Array();
$palettegrid['SGB3']['A'] = 'F8D09870C0C0F86028304860';
$palettegrid['SGB3']['B'] = 'D8D8C0E08020005000001010';
$palettegrid['SGB3']['C'] = 'E0A8C8F8F87800B8F8202058';
$palettegrid['SGB3']['D'] = 'F0F8B8E0A87808C800000000';
$palettegrid['SGB3']['E'] = 'F8F8C0E0B068B07820504870';
$palettegrid['SGB3']['F'] = '7878C8F868F8F8D000404040';
$palettegrid['SGB3']['G'] = '60D850F8F8F8C83038380000';
$palettegrid['SGB3']['H'] = 'E0F8A078C838488818081800';

$palettegrid['SGB4'] = Array();
$palettegrid['SGB4']['A'] = 'F0A86878A8F8D000D0000078';
$palettegrid['SGB4']['B'] = 'F0E8F0E8A060407838180808';
$palettegrid['SGB4']['C'] = 'F8E0E0D8A0D098A0E0080000';
$palettegrid['SGB4']['D'] = 'F8F8B890C8C8486878082048';
$palettegrid['SGB4']['E'] = 'F8D8A8E0A878785888002030';
$palettegrid['SGB4']['F'] = 'B8D0D0D880D88000A0380000';
$palettegrid['SGB4']['G'] = 'B0E018B82058381000281000';
$palettegrid['SGB4']['H'] = 'F8F8C8B8C058808840405028';

$palettegrid['GBC1'] = Array();
$palettegrid['GBC1']['UP'] = 'FFFFFFFFAD63833100000000';
$palettegrid['GBC1']['UPA'] = 'FFFFFFFF8584943A3A000000';
$palettegrid['GBC1']['UPB'] = 'FFE7C5CC9C85846B29000000';

$palettegrid['GBC2'] = Array();
$palettegrid['GBC2']['LEFT'] = 'FFFFFF65A49B0000FE000000';
$palettegrid['GBC2']['LEFTA'] = 'FFFFFF8B8CDE53528C000000';
$palettegrid['GBC2']['LEFTB'] = 'FFFFFFA5A5A5525252000000';

$palettegrid['GBC3'] = Array();
$palettegrid['GBC3']['DOWN'] = 'FFFFA5FE94949394FE000000';
$palettegrid['GBC3']['DOWNA'] = 'FFFFFFFFFF00FE0000000000';
$palettegrid['GBC3']['DOWNB'] = 'FFFFFFFFFF007D4900000000';

$palettegrid['GBC4'] = Array();
$palettegrid['GBC4']['RIGHT'] = 'FFFFFF51FF00FF4200000000';
$palettegrid['GBC4']['RIGHTA'] = 'FFFFFF7BFF300163C6000000';
$palettegrid['GBC4']['RIGHTB'] = '000000008486FFDE00FFFFFF';

$palettegrid['INVERT'] = Array();
$palettegrid['INVERT']['0'] = 'F4E1E1D17676297E7F0A2020';
$palettegrid['INVERT']['1'] = 'F4EBE2D1A37729537F0A1520';
$palettegrid['INVERT']['2'] = 'F4F4E2D1D07729297F0A0A20';
$palettegrid['INVERT']['3'] = 'EBF4E2A6D17753297F150A20';
$palettegrid['INVERT']['4'] = 'E2F4E279D1777E297F200A20';
$palettegrid['INVERT']['5'] = 'E2F4EB77D1A17F2956200A15';
$palettegrid['INVERT']['6'] = 'E2F4F477D1CD7F292B200A0A';
$palettegrid['INVERT']['7'] = 'E2EBF477A8D17F512920150A';
$palettegrid['INVERT']['8'] = 'E2E2F4777CD17F7C2920200A';
$palettegrid['INVERT']['9'] = 'EBE2F49F77D1587F2915200A';
$palettegrid['INVERT']['A'] = 'F4E2F4CB77D12D7F290A200A';
$palettegrid['INVERT']['B'] = 'F4E2EBD177AA297F4F0A2015';

$palettegrid['RAINBOW'] = Array();
$palettegrid['RAINBOW']['0'] = 'F4E1E1D176767F292B200A0A';
$palettegrid['RAINBOW']['1'] = 'F4EBE2D1A3777F512920150A';
$palettegrid['RAINBOW']['2'] = 'F4F4E2D1D0777F7C2920200A';
$palettegrid['RAINBOW']['3'] = 'EBF4E2A6D177587F2915200A';
$palettegrid['RAINBOW']['4'] = 'E2F4E279D1772D7F290A200A';
$palettegrid['RAINBOW']['5'] = 'E2F4EB77D1A1297F4F0A2015';
$palettegrid['RAINBOW']['6'] = 'E2F4F477D1CD297E7F0A2020';
$palettegrid['RAINBOW']['7'] = 'E2EBF477A8D129537F0A1520';
$palettegrid['RAINBOW']['8'] = 'E2E2F4777CD129297F0A0A20';
$palettegrid['RAINBOW']['9'] = 'EBE2F49F77D153297F150A20';
$palettegrid['RAINBOW']['A'] = 'F4E2F4CB77D17E297F200A20';
$palettegrid['RAINBOW']['B'] = 'F4E2EBD177AA7F2956200A15';

$gb_cols        = [];

foreach ($palettegrid as $palgrid)
{
    foreach ($palgrid as $pal)
    {
        $col        = [];

        for ($i = 0; $i < 4; $i++)
        {
            $int      = base_convert(substr($pal, $i * 6, 6), 16, 10);
            
            $col[$i]['r']   = ($int >> 16) & 0xFF;
            $col[$i]['g']   = ($int >> 8) & 0xFF;
            $col[$i]['b']   = ($int) & 0xFF;
        }
        
        $gb_cols[]      = $col;
    }
}

file_put_contents("tmp.json", json_encode($gb_cols));
*/

?>