<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/announcements.php';
$item=['enabled'=>true,'image'=>'uploads/announcements/test.jpg','start'=>'2026-10-12','end'=>'2026-10-14'];
$cases=[
    [announcement_is_active($item,'2026-10-11'),false,'Before start'],
    [announcement_is_active($item,'2026-10-12'),true,'Start inclusive'],
    [announcement_is_active($item,'2026-10-14'),true,'End inclusive'],
    [announcement_is_active($item,'2026-10-15'),false,'After end'],
    [announcement_is_active(array_replace($item,['enabled'=>false]),'2026-10-13'),false,'Disabled'],
    [announcement_is_active(array_replace($item,['image'=>'']),'2026-10-13'),false,'Missing image'],
    [announcement_is_active(array_replace($item,['start'=>'2026-02-30']),'2026-10-13'),false,'Invalid calendar date'],
    [announcement_is_active(array_replace($item,['start'=>'2026-10-15']),'2026-10-13'),false,'Reversed dates'],
];
foreach ($cases as [$actual,$expected,$label]) if ($actual!==$expected) throw new RuntimeException($label);
echo "Announcement scheduling: inclusive boundaries, disabled/missing image and invalid dates passed.\n";
